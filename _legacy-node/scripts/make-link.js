/**
 * สร้างลิงก์ + ไฟล์ QR จากเครื่อง (ไม่ต้องเรียก CRM)
 *   npm run link -- 6274719000173346428
 */
import QRCode from 'qrcode';
import { loadConfig } from '../src/config.js';
import { createToken } from '../src/token.js';

const dealId = process.argv[2];
if (!dealId) {
  console.error('Usage: npm run link -- <deal_id>');
  process.exit(1);
}
const cfg = loadConfig();
if (cfg.trackingSecret.length < 16) {
  console.error('ตั้ง TRACKING_SECRET ใน .env ก่อน');
  process.exit(1);
}
const url = `${cfg.publicBaseUrl}/t/${createToken(dealId, cfg.trackingSecret)}`;
const file = `qr-${dealId}.png`;
await QRCode.toFile(file, url, { width: 600, margin: 2 });
console.log(url);
console.log(`QR saved: ${file}`);
