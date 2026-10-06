/**
 * แปลงข้อมูล Deal จาก CRM -> ข้อมูลที่ "อนุญาต" ให้ลูกค้าเห็น (whitelist)
 * แล้ว render เป็นหน้า HTML (ไม่มีมูลค่าดีล, ชื่อแรงงาน, โน้ตภายใน)
 */

const STATUS = {
  'ดำเนินการแล้ว': 'done',
  'กำลังดำเนินการ': 'active',
  'ดำเนินการล่าช้า': 'delayed',
  'รอดำเนินการ': 'pending',
};
const STATUS_LABEL = {
  done: 'เสร็จแล้ว',
  active: 'กำลังดำเนินการ',
  delayed: 'ล่าช้า',
  pending: 'รอดำเนินการ',
};

export function toPublicView(deal, { showDelayReason = true } = {}) {
  const rows = Array.isArray(deal.Tracking_System) ? deal.Tracking_System : [];
  const steps = rows
    .map((r, i) => ({
      order: Number(r.LinkingModule5_Serial_Number) || i + 1,
      title: r.Job_Status && r.Job_Status !== '-None-' ? r.Job_Status : 'ขั้นตอนที่ ' + (i + 1),
      status: STATUS[r.Tracking_Status] || 'pending',
      // หมายเหตุ: Expected_Date ใน CRM คือ "วันเริ่ม" ที่วางแผนของขั้นตอนนี้
      // (ขั้นถัดไปเริ่ม = Expected_Date + Estimated_Days)
      expectedDate: r.Expected_Date || null,
      estimatedDays: Number(r.Estimated_Days) || null,
      actualDate: r.Actual_Date || null,
      delayReason: showDelayReason ? r.Delay_Reason || null : null,
    }))
    .sort((a, b) => a.order - b.order);

  const done = steps.filter((s) => s.status === 'done').length;
  const current =
    steps.find((s) => s.status === 'active' || s.status === 'delayed') ||
    steps.find((s) => s.status === 'pending') ||
    null;
  if (current) current.isCurrent = true;

  // วันคาดว่าจะเสร็จ = วันเริ่มของขั้นสุดท้าย + จำนวนวันของขั้นนั้น
  // (ตรงกับ Start_Date + "ประมาณการวันทั้งหมด" ใน CRM)
  let estimatedFinish = null;
  const last = [...steps].filter((s) => s.expectedDate).sort((a, b) => a.expectedDate.localeCompare(b.expectedDate)).pop();
  if (last) estimatedFinish = addDays(last.expectedDate, last.estimatedDays || 0);
  else if (deal.Start_Date && Number(deal.field47)) estimatedFinish = addDays(deal.Start_Date, Number(deal.field47));

  return {
    jobCode: deal.M5L_Job_Code || deal.Deal_Name || '',
    refNo: deal.M5L_Job_Code && deal.Deal_Name && deal.Deal_Name !== deal.M5L_Job_Code ? deal.Deal_Name : '',
    customer: deal.Account_Name?.name || '',
    services: Array.isArray(deal.MOU_Service) ? deal.MOU_Service : [],
    stage: deal.Stage || '',
    startDate: deal.Start_Date || null,
    estimatedFinish,
    updatedAt: deal.Modified_Time || null,
    total: steps.length,
    done,
    percent: steps.length ? Math.round((done / steps.length) * 100) : 0,
    allDone: steps.length > 0 && done === steps.length,
    current,
    steps,
  };
}

// ---------- helpers ----------
export function addDays(isoDate, days) {
  const d = new Date(`${isoDate}T00:00:00Z`);
  if (isNaN(d)) return null;
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}
export const esc = (v) =>
  String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const dateFmt = new Intl.DateTimeFormat('th-TH', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });
const dateTimeFmt = new Intl.DateTimeFormat('th-TH', {
  day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Bangkok',
});
export function thDate(iso) {
  if (!iso) return '-';
  const d = new Date(/^\d{4}-\d{2}-\d{2}$/.test(iso) ? `${iso}T00:00:00Z` : iso);
  return isNaN(d) ? '-' : dateFmt.format(d);
}
function thDateTime(iso) {
  if (!iso) return '-';
  const d = new Date(iso);
  return isNaN(d) ? '-' : dateTimeFmt.format(d);
}

// ---------- layout ----------
const CSS = `
:root{--bg:#f4f6f9;--card:#fff;--ink:#17202b;--muted:#5f6b7a;--line:#e3e8ef;
--brand:#0f4c81;--done:#14804a;--done-bg:#e6f4ec;--active:#1f63d6;--active-bg:#e8effc;
--delay:#b4540a;--delay-bg:#fdf0e3;--pend:#8a95a3;--pend-bg:#eef1f5}
@media (prefers-color-scheme:dark){:root{--bg:#0f141a;--card:#18202a;--ink:#e8edf3;--muted:#9aa6b4;--line:#2a3441;
--brand:#6fa8dc;--done:#4cc38a;--done-bg:#15301f;--active:#7aa7ff;--active-bg:#172a4a;
--delay:#f0a35e;--delay-bg:#3a2612;--pend:#7d8896;--pend-bg:#232c37}}
*{box-sizing:border-box}html,body{margin:0}
body{background:var(--bg);color:var(--ink);font:15px/1.55 "IBM Plex Sans Thai","Sarabun",system-ui,sans-serif;-webkit-font-smoothing:antialiased}
.wrap{max-width:640px;margin:0 auto;padding:16px 16px 40px}
.brand{display:flex;align-items:center;gap:8px;color:var(--brand);font-weight:600;font-size:14px;margin:4px 2px 14px}
.brand i{width:10px;height:10px;border-radius:3px;background:var(--brand);display:inline-block}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px}
.card+.card{margin-top:12px}
.label{color:var(--muted);font-size:13px}
h1{font-size:22px;margin:2px 0 4px;letter-spacing:.2px}
.cust{color:var(--muted);margin:0 0 10px}
.chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{font-size:12.5px;padding:3px 10px;border-radius:999px;background:var(--pend-bg);color:var(--ink)}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px}
.grid b{display:block;font-size:15px}
.bar{height:8px;border-radius:99px;background:var(--pend-bg);overflow:hidden;margin:10px 0 6px}
.bar span{display:block;height:100%;background:var(--done);border-radius:99px}
.now{display:flex;gap:10px;align-items:flex-start}
.now .dot{flex:none;margin-top:6px}
.now b{font-size:16px}
h2{font-size:15px;margin:0 0 12px}
ol{list-style:none;margin:0;padding:0}
li{position:relative;display:flex;gap:12px;padding:0 0 18px}
li:last-child{padding-bottom:0}
li:not(:last-child)::before{content:"";position:absolute;left:11px;top:26px;bottom:2px;width:2px;background:var(--line)}
li.done:not(:last-child)::before{background:var(--done)}
.dot{width:24px;height:24px;border-radius:50%;flex:none;display:grid;place-items:center;font-size:12px;font-weight:700;
background:var(--pend-bg);color:var(--pend);border:2px solid var(--pend)}
.done .dot{background:var(--done);border-color:var(--done);color:#fff}
.active .dot{background:var(--active-bg);border-color:var(--active);color:var(--active)}
.delayed .dot{background:var(--delay-bg);border-color:var(--delay);color:var(--delay)}
.cur .dot{box-shadow:0 0 0 4px var(--active-bg)}
.body{min-width:0;flex:1}
.t{font-weight:600}
.pending .t{color:var(--muted);font-weight:500}
.meta{font-size:13px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;font-size:12px;font-weight:600;padding:1px 8px;border-radius:6px;margin-left:6px;vertical-align:1px}
.b-done{background:var(--done-bg);color:var(--done)}.b-active{background:var(--active-bg);color:var(--active)}
.b-delayed{background:var(--delay-bg);color:var(--delay)}.b-pending{background:var(--pend-bg);color:var(--pend)}
.reason{margin-top:6px;font-size:13px;background:var(--delay-bg);color:var(--ink);padding:8px 10px;border-radius:8px}
.contact{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.btn{flex:1;min-width:140px;text-align:center;text-decoration:none;padding:11px 12px;border-radius:10px;font-weight:600;
border:1px solid var(--line);color:var(--ink);background:var(--card);white-space:nowrap}
.btn.line{background:#06c755;border-color:#06c755;color:#fff}
.foot{color:var(--muted);font-size:12.5px;text-align:center;margin-top:16px}
.empty{text-align:center;padding:36px 18px}
@media (max-width:420px){.grid{grid-template-columns:1fr 1fr}.grid div:last-child{grid-column:span 2}}
`;

function layout(title, inner, company) {
  return `<!doctype html><html lang="th"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">
<title>${esc(title)}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>${CSS}</style></head><body><main class="wrap">
<div class="brand"><i></i>${esc(company.name)} · ติดตามสถานะงาน</div>
${inner}
</main></body></html>`;
}

function contactBlock(company) {
  const btns = [];
  if (company.phone) btns.push(`<a class="btn" href="tel:${esc(company.phone.replace(/[^\d+]/g, ''))}">โทร ${esc(company.phone)}</a>`);
  if (company.lineUrl) btns.push(`<a class="btn line" href="${esc(company.lineUrl)}" target="_blank" rel="noopener">ติดต่อทาง LINE</a>`);
  if (!btns.length) return '';
  return `<section class="card"><h2>มีคำถามเกี่ยวกับงานนี้?</h2><div class="label">แจ้งเลขที่งานกับเจ้าหน้าที่เพื่อความรวดเร็ว</div><div class="contact">${btns.join('')}</div></section>`;
}

/** "เริ่มประมาณ 25 ก.ย. 2569 · ใช้เวลาราว 2 วัน" */
function planText(s) {
  if (!s || !s.expectedDate) return '';
  return `เริ่มประมาณ ${thDate(s.expectedDate)}${s.estimatedDays ? ` · ใช้เวลาราว ${s.estimatedDays} วัน` : ''}`;
}

export function renderTracking(v, company) {
  const steps = v.steps
    .map((s, i) => {
      const dateLine =
        s.status === 'done'
          ? `เสร็จเมื่อ ${thDate(s.actualDate || s.expectedDate)}`
          : planText(s);
      return `<li class="${s.status}${s.isCurrent ? ' cur' : ''}">
<span class="dot">${s.status === 'done' ? '✓' : s.status === 'delayed' ? '!' : i + 1}</span>
<div class="body"><div class="t">${esc(s.title)}${s.status !== 'pending' ? `<span class="badge b-${s.status}">${STATUS_LABEL[s.status]}</span>` : ''}</div>
${dateLine ? `<div class="meta">${dateLine}</div>` : ''}
${s.delayReason ? `<div class="reason">หมายเหตุ: ${esc(s.delayReason)}</div>` : ''}</div></li>`;
    })
    .join('');

  const cur = v.allDone
    ? `<div class="now"><span class="dot" style="background:var(--done);border-color:var(--done);color:#fff">✓</span><div><div class="label">สถานะล่าสุด</div><b>ดำเนินการครบทุกขั้นตอนแล้ว</b></div></div>`
    : v.current
      ? `<div class="now"><span class="dot" style="${v.current.status === 'delayed' ? 'border-color:var(--delay);color:var(--delay);background:var(--delay-bg)' : 'border-color:var(--active);color:var(--active);background:var(--active-bg)'}">•</span>
<div><div class="label">ขั้นตอนปัจจุบัน</div><b>${esc(v.current.title)}</b>
${planText(v.current) ? `<div class="meta">${planText(v.current)}</div>` : ''}</div></div>`
      : `<div class="label">สถานะ: ${esc(v.stage || '-')}</div>`;

  const inner = `
<section class="card">
  <div class="label">เลขที่งาน</div>
  <h1>${esc(v.jobCode)}</h1>
  ${v.refNo ? `<div class="label">เลขอ้างอิงใบงาน ${esc(v.refNo)}</div>` : ''}
  <p class="cust">${esc(v.customer)}</p>
  ${v.services.length ? `<div class="chips">${v.services.map((s) => `<span class="chip">${esc(s)}</span>`).join('')}</div>` : ''}
  <div class="grid">
    <div><span class="label">เริ่มดำเนินการ</span><b>${thDate(v.startDate)}</b></div>
    <div><span class="label">คาดว่าจะเสร็จ</span><b>${thDate(v.estimatedFinish)}</b></div>
    <div><span class="label">ความคืบหน้า</span><b>${v.done}/${v.total} ขั้นตอน</b></div>
  </div>
  <div class="bar" role="progressbar" aria-valuenow="${v.percent}" aria-valuemin="0" aria-valuemax="100"><span style="width:${v.percent}%"></span></div>
</section>
<section class="card">${cur}</section>
<section class="card"><h2>ขั้นตอนการดำเนินงาน</h2>${v.total ? `<ol>${steps}</ol>` : '<div class="label">เจ้าหน้าที่กำลังจัดเตรียมแผนการดำเนินงาน</div>'}</section>
${contactBlock(company)}
<div class="foot">อัปเดตล่าสุด ${thDateTime(v.updatedAt)}<br>ข้อมูลอาจมีการเปลี่ยนแปลงตามขั้นตอนของหน่วยงานราชการ</div>`;
  return layout(`สถานะงาน ${v.jobCode}`, inner, company);
}

export function renderMessage(title, message, company) {
  return layout(title, `<section class="card empty"><h1>${esc(title)}</h1><p class="cust">${esc(message)}</p></section>${contactBlock(company)}`, company);
}
