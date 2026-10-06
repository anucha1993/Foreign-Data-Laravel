import express from 'express';
import crypto from 'node:crypto';
import QRCode from 'qrcode';
import { fileURLToPath } from 'node:url';
import { loadConfig, assertConfig } from './config.js';
import { ZohoAuth } from './zohoAuth.js';
import { ZohoCrm } from './zohoCrm.js';
import { createToken, verifyToken } from './token.js';
import { toPublicView, renderTracking, renderMessage } from './view.js';
import { ForeignData } from './foreign/data.js';
import { mountForeign } from './foreign/routes.js';

/** rate limit แบบง่ายในหน่วยความจำ (ต่อ IP) */
function rateLimit({ windowMs = 60000, max = 60 } = {}) {
  const hits = new Map();
  setInterval(() => hits.clear(), windowMs).unref();
  return (req, res, next) => {
    const n = (hits.get(req.ip) || 0) + 1;
    hits.set(req.ip, n);
    if (n > max) return res.status(429).send('Too many requests');
    next();
  };
}

function safeEqual(a, b) {
  const x = Buffer.from(String(a || ''));
  const y = Buffer.from(String(b || ''));
  return x.length === y.length && crypto.timingSafeEqual(x, y);
}

export function createApp(cfg, crm, { foreign = null } = {}) {
  const app = express();
  app.set('trust proxy', true);
  app.disable('x-powered-by');

  app.use((req, res, next) => {
    res.set({
      'X-Robots-Tag': 'noindex, nofollow',
      'Referrer-Policy': 'no-referrer',
      'X-Content-Type-Options': 'nosniff',
      'X-Frame-Options': 'DENY',
    });
    next();
  });

  const linkFor = (dealId) => `${cfg.publicBaseUrl}/t/${createToken(dealId, cfg.trackingSecret)}`;

  /** โหลด Deal และตรวจว่าอนุญาตให้แสดงหรือไม่ */
  async function loadVisibleDeal(dealId) {
    const deal = await crm.getDeal(dealId);
    if (!deal) return { error: 'notfound' };
    if (cfg.allowedPipelines.length && deal.Pipeline && !cfg.allowedPipelines.includes(deal.Pipeline)) {
      return { error: 'notfound' };
    }
    if (cfg.hiddenStages.includes(deal.Stage)) return { error: 'hidden' };
    return { deal };
  }

  app.get('/healthz', (req, res) => res.json({ ok: true }));

  // ---------- หน้าลูกค้า ----------
  const publicLimiter = rateLimit({ max: 60 });

  app.get('/t/:token', publicLimiter, async (req, res) => {
    res.set('Cache-Control', 'private, no-store');
    const dealId = verifyToken(req.params.token, cfg.trackingSecret);
    if (!dealId) {
      return res.status(404).send(renderMessage('ไม่พบข้อมูล', 'ลิงก์ไม่ถูกต้องหรือถูกยกเลิกแล้ว กรุณาติดต่อเจ้าหน้าที่', cfg.company));
    }
    try {
      const { deal, error } = await loadVisibleDeal(dealId);
      if (error === 'hidden') {
        return res.status(410).send(renderMessage('ไม่สามารถแสดงข้อมูลได้', 'งานนี้ปิดการติดตามแล้ว กรุณาติดต่อเจ้าหน้าที่', cfg.company));
      }
      if (error) {
        return res.status(404).send(renderMessage('ไม่พบข้อมูล', 'ไม่พบงานนี้ในระบบ กรุณาติดต่อเจ้าหน้าที่', cfg.company));
      }
      const view = toPublicView(deal, { showDelayReason: cfg.showDelayReason });
      res.send(renderTracking(view, cfg.company));
    } catch (e) {
      console.error('[tracking]', dealId, e.message);
      res.status(503).send(renderMessage('ระบบขัดข้องชั่วคราว', 'กรุณาลองใหม่อีกครั้งในอีกสักครู่', cfg.company));
    }
  });

  // QR ของลิงก์ (ต้องมี token ที่ถูกต้องเท่านั้น) — ใช้แนบเอกสาร/อีเมล
  app.get('/qr/:token.png', publicLimiter, async (req, res) => {
    const dealId = verifyToken(req.params.token, cfg.trackingSecret);
    if (!dealId) return res.status(404).end();
    const png = await QRCode.toBuffer(linkFor(dealId), { width: Number(req.query.size) || 480, margin: 2, errorCorrectionLevel: 'M' });
    res.set({ 'Content-Type': 'image/png', 'Cache-Control': 'public, max-age=86400' }).send(png);
  });

  // ---------- API ภายใน (เรียกจากปุ่มใน CRM) ----------
  const requireAdmin = (req, res, next) => {
    if (!safeEqual(req.get('X-Admin-Key'), cfg.adminApiKey)) return res.status(401).json({ error: 'unauthorized' });
    next();
  };

  app.get('/api/admin/link', rateLimit({ max: 120 }), requireAdmin, async (req, res) => {
    const dealId = String(req.query.deal_id || '').trim();
    if (!/^\d{5,25}$/.test(dealId)) return res.status(400).json({ error: 'invalid deal_id' });
    try {
      if (req.query.check !== 'false') {
        const deal = await crm.getDeal(dealId);
        if (!deal) return res.status(404).json({ error: 'deal not found' });
      }
      const token = createToken(dealId, cfg.trackingSecret);
      const url = linkFor(dealId);
      res.json({
        deal_id: dealId,
        url,
        qr_png_url: `${cfg.publicBaseUrl}/qr/${token}.png`,
        qr_data_url: await QRCode.toDataURL(url, { width: 480, margin: 2 }),
      });
    } catch (e) {
      console.error('[admin/link]', e.message);
      res.status(502).json({ error: 'crm error' });
    }
  });

  if (foreign) mountForeign(app, cfg, foreign);

  app.use((req, res) => res.status(404).send(renderMessage('ไม่พบหน้านี้', 'กรุณาสแกน QR Code จากเอกสารอีกครั้ง', cfg.company)));
  return app;
}

// ---------- start ----------
if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const cfg = loadConfig();
  assertConfig(cfg);
  const auth = new ZohoAuth(cfg.zoho);
  const crm = new ZohoCrm(auth, { apiDomain: cfg.zoho.apiDomain, apiVersion: cfg.zoho.apiVersion, cacheTtlMs: cfg.cacheTtlMs });
  const foreign = cfg.foreign.enabled
    ? new ForeignData(crm, auth, { workdriveDownloadUrl: cfg.foreign.workdriveDownloadUrl, cacheTtlMs: cfg.cacheTtlMs })
    : null;
  createApp(cfg, crm, { foreign }).listen(cfg.port, () => {
    console.log(`Tracking server on :${cfg.port}  (${cfg.publicBaseUrl})`);
  });
}
