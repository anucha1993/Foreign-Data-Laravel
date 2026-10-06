import crypto from 'node:crypto';

/**
 * ลิงก์ลูกค้า = <dealId>.<signature>
 * signature = HMAC-SHA256(TRACKING_SECRET, "deal:<dealId>") แบบ hex ตัวพิมพ์เล็ก 32 ตัวแรก
 *
 * ใช้รูปแบบ hex เพื่อให้ Zoho Deluge สร้างลิงก์เองได้ด้วย:
 *   zoho.encryption.hmacsha256(secret, "deal:" + dealId, "hex").subString(0,32)
 *
 * - ไม่ต้องสร้างฟิลด์ token ใน CRM
 * - เดา/แก้เลข Deal เพื่อดูของลูกค้ารายอื่นไม่ได้ เพราะลายเซ็นจะไม่ตรง
 * - เปลี่ยน TRACKING_SECRET = ยกเลิกลิงก์เก่าทั้งหมด
 * - ยังรับลิงก์รุ่นแรก (base64url 22 ตัว) ได้ เผื่อมีที่สร้างไปแล้ว
 */
function hmac(dealId, secret) {
  return crypto.createHmac('sha256', secret).update(`deal:${dealId}`).digest();
}
const sigHex = (id, secret) => hmac(id, secret).toString('hex').slice(0, 32);
const sigB64 = (id, secret) => hmac(id, secret).subarray(0, 16).toString('base64url');

export function createToken(dealId, secret) {
  const id = String(dealId).trim();
  if (!/^\d{5,25}$/.test(id)) throw new Error('Invalid deal id');
  return `${id}.${sigHex(id, secret)}`;
}

function safeEq(a, b) {
  const x = Buffer.from(a);
  const y = Buffer.from(b);
  return x.length === y.length && crypto.timingSafeEqual(x, y);
}

/** คืนค่า dealId ถ้า token ถูกต้อง ไม่งั้นคืน null */
export function verifyToken(token, secret) {
  if (typeof token !== 'string' || token.length > 80) return null;
  const hex = /^(\d{5,25})\.([0-9a-fA-F]{32})$/.exec(token);
  if (hex) return safeEq(sigHex(hex[1], secret), hex[2].toLowerCase()) ? hex[1] : null;
  const b64 = /^(\d{5,25})\.([A-Za-z0-9_-]{22})$/.exec(token);
  if (b64) return safeEq(sigB64(b64[1], secret), b64[2]) ? b64[1] : null;
  return null;
}
