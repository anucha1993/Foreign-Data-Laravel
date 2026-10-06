import test from 'node:test';
import assert from 'node:assert/strict';
import { createToken, verifyToken } from '../src/token.js';
import { toPublicView } from '../src/view.js';
import { ZohoAuth } from '../src/zohoAuth.js';
import { ZohoCrm } from '../src/zohoCrm.js';
import { createApp } from '../src/server.js';
import { loadConfig } from '../src/config.js';
import { deal } from './fixture.js';

const SECRET = 'test-secret-0123456789abcdef';

test('token: roundtrip, tamper, wrong secret', () => {
  const t = createToken(deal.id, SECRET);
  assert.equal(verifyToken(t, SECRET), deal.id);
  const [id, sig] = t.split('.');
  assert.equal(verifyToken(`${BigInt(id) + 1n}.${sig}`, SECRET), null);
  assert.equal(verifyToken(t, 'other-secret-xxxxxxxxxxxx'), null);
  assert.equal(verifyToken('abc', SECRET), null);
});

test('token: matches Zoho Deluge hmacsha256 (doc example) and Deluge-built link', async () => {
  const crypto = await import('node:crypto');
  // ตัวอย่างจากเอกสาร Zoho Deluge: hmacsha256("sk_5Ow7B_4eC39H", "...", "hex")
  const h = crypto.createHmac('sha256', 'sk_5Ow7B_4eC39H').update('{Order ID: 0932, Currency: USD, Amount: 2500}').digest('hex');
  assert.equal(h, '13721963e7ac439f5a2e63c261856393647f3f51b493beebf5919af4bc3de19c');
  // จำลองสิ่งที่ Deluge ทำ: hex.toLowerCase().subString(0,32)
  const delugeSig = crypto.createHmac('sha256', SECRET).update('deal:' + deal.id).digest('hex').toLowerCase().substring(0, 32);
  assert.equal(verifyToken(`${deal.id}.${delugeSig}`, SECRET), deal.id);
  assert.equal(verifyToken(`${deal.id}.${delugeSig.toUpperCase()}`, SECRET), deal.id);
  assert.equal(createToken(deal.id, SECRET), `${deal.id}.${delugeSig}`);
  // ลิงก์รุ่นแรก (base64url) ยังใช้ได้
  const old = crypto.createHmac('sha256', SECRET).update('deal:' + deal.id).digest().subarray(0, 16).toString('base64url');
  assert.equal(verifyToken(`${deal.id}.${old}`, SECRET), deal.id);
});

test('view: sorts, progress, current step, whitelist', () => {
  const v = toPublicView(deal);
  assert.deepEqual(v.steps.map((s) => s.order), [1, 2, 3, 4, 5]);
  assert.equal(v.done, 1);
  assert.equal(v.percent, 20);
  assert.equal(v.current.title, 'ยื่นเอกสารแจ้งออก(JE) Online');
  // ต้องตรงกับ Start_Date + ประมาณการวันทั้งหมด (24 ก.ย. + 20 วัน)
  assert.equal(v.estimatedFinish, '2026-10-14');
  assert.equal(v.refNo, 'IO2026-2182');
  const json = JSON.stringify(v);
  assert.ok(!json.includes('99999') && !json.includes('SECRETNAME'));
  assert.equal(toPublicView(deal, { showDelayReason: false }).steps[2].delayReason, null);
});

test('view: finish date matches CRM on RM2026-1034 (22 steps, 58 days)', () => {
  // วันเริ่มขั้นสุดท้าย 19 พ.ย. + 2 วัน = 21 พ.ย. = 24 ก.ย. + 58 วัน
  const v = toPublicView({ Start_Date: '2026-09-24', field47: 58, Tracking_System: [
    { LinkingModule5_Serial_Number: '1', Expected_Date: '2026-09-24', Estimated_Days: 1, Tracking_Status: 'ดำเนินการแล้ว' },
    { LinkingModule5_Serial_Number: '22', Expected_Date: '2026-11-19', Estimated_Days: 2, Tracking_Status: 'รอดำเนินการ' },
  ] });
  assert.equal(v.estimatedFinish, '2026-11-21');
});

test('auth: caches token and dedupes concurrent refresh', async () => {
  let calls = 0;
  const fetchImpl = async (url, opts) => {
    calls++;
    assert.match(url, /\/oauth\/v2\/token$/);
    assert.match(String(opts.body), /grant_type=refresh_token/);
    return { ok: true, status: 200, json: async () => ({ access_token: 'AT' + calls, expires_in: 3600, api_domain: 'https://www.zohoapis.com' }) };
  };
  const a = new ZohoAuth({ clientId: 'c', clientSecret: 's', refreshToken: 'r', accountsUrl: 'https://accounts.zoho.com' }, { fetchImpl });
  const [x, y] = await Promise.all([a.getAccessToken(), a.getAccessToken()]);
  assert.equal(x, 'AT1'); assert.equal(y, 'AT1');
  assert.equal(await a.getAccessToken(), 'AT1');
  assert.equal(calls, 1);
});

test('auth: surfaces Zoho error returned with HTTP 200', async () => {
  const a = new ZohoAuth({ accountsUrl: 'x' }, { fetchImpl: async () => ({ ok: true, status: 200, json: async () => ({ error: 'invalid_client' }) }) });
  await assert.rejects(a.getAccessToken(), /invalid_client/);
});

test('crm: retries once on 401 with fresh token', async () => {
  let tokenN = 0; const seen = [];
  const auth = new ZohoAuth({ accountsUrl: 'A' }, { fetchImpl: async () => ({ ok: true, json: async () => ({ access_token: 'T' + ++tokenN, expires_in: 3600 }) }) });
  const fetchImpl = async (url, opts) => {
    seen.push(opts.headers.Authorization);
    if (seen.length === 1) return { ok: false, status: 401, json: async () => ({ code: 'INVALID_TOKEN' }) };
    return { ok: true, status: 200, json: async () => ({ data: [deal] }) };
  };
  const crm = new ZohoCrm(auth, { apiDomain: 'https://www.zohoapis.com' }, { fetchImpl });
  const d = await crm.getDeal(deal.id);
  assert.equal(d.id, deal.id);
  assert.deepEqual(seen, ['Zoho-oauthtoken T1', 'Zoho-oauthtoken T2']);
});

async function withServer(crm, fn, env = {}) {
  const cfg = loadConfig({ TRACKING_SECRET: SECRET, ADMIN_API_KEY: 'admin-key-123456', ALLOWED_PIPELINES: 'Tracking', HIDDEN_STAGES: 'ยกเลิก', PUBLIC_BASE_URL: 'https://track.example.com', CONTACT_PHONE: '02-000-0000', CONTACT_LINE_URL: 'https://line.me/R/ti/p/@x', ...env });
  const srv = createApp(cfg, crm).listen(0);
  const base = `http://127.0.0.1:${srv.address().port}`;
  try { await fn(base); } finally { srv.close(); }
}
const fakeCrm = (d) => ({ getDeal: async (id) => (id === d.id ? d : null) });

test('http: page renders, escapes, hides secrets', async () => {
  await withServer(fakeCrm(deal), async (base) => {
    const r = await fetch(`${base}/t/${createToken(deal.id, SECRET)}`);
    assert.equal(r.status, 200);
    const html = await r.text();
    assert.ok(html.includes('IO-26-20176'));
    assert.ok(html.includes('&lt;script&gt;') && !html.includes('<script>'));
    assert.ok(!html.includes('SECRETNAME') && !html.includes('99999'));
    assert.equal(r.headers.get('x-robots-tag'), 'noindex, nofollow');
  });
});

test('http: bad token 404, hidden stage 410, wrong pipeline 404', async () => {
  await withServer(fakeCrm({ ...deal, Stage: 'ยกเลิก' }), async (base) => {
    assert.equal((await fetch(`${base}/t/${deal.id}.AAAAAAAAAAAAAAAAAAAAAA`)).status, 404);
    assert.equal((await fetch(`${base}/t/${createToken(deal.id, SECRET)}`)).status, 410);
  });
  await withServer(fakeCrm({ ...deal, Pipeline: 'Sales' }), async (base) => {
    assert.equal((await fetch(`${base}/t/${createToken(deal.id, SECRET)}`)).status, 404);
  });
});

test('http: admin link requires key and returns QR', async () => {
  await withServer(fakeCrm(deal), async (base) => {
    assert.equal((await fetch(`${base}/api/admin/link?deal_id=${deal.id}`)).status, 401);
    const r = await fetch(`${base}/api/admin/link?deal_id=${deal.id}`, { headers: { 'X-Admin-Key': 'admin-key-123456' } });
    const j = await r.json();
    assert.equal(r.status, 200);
    assert.ok(j.url.startsWith('https://track.example.com/t/' + deal.id + '.'));
    assert.ok(j.qr_data_url.startsWith('data:image/png;base64,'));
    const png = await fetch(j.qr_png_url.replace('https://track.example.com', base));
    assert.equal(png.headers.get('content-type'), 'image/png');
  });
});
