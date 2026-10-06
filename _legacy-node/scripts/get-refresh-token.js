/**
 * แลก Grant Code (จาก Self Client) เป็น Refresh Token ครั้งเดียว
 *
 * 1) https://api-console.zoho.com -> Self Client -> Generate Code
 *    Scope (คัดลอกทั้งบรรทัด):
 *    ZohoCRM.modules.deals.READ,ZohoCRM.modules.custom.READ,ZohoCRM.settings.fields.READ,WorkDrive.files.READ
 *    - deals.READ            : ระบบ Tracking
 *    - custom.READ           : โมดูล Foreign_Data (ระบบข้อมูลแรงงาน)
 *    - settings.fields.READ  : ชื่อฟิลด์ภาษาไทย
 *    - WorkDrive.files.READ  : เปิดไฟล์เอกสารจาก WorkDrive
 * 2) npm run zoho:token -- <grant_code>
 * 3) นำ refresh_token ที่ได้ไปใส่ ZOHO_REFRESH_TOKEN ใน .env
 */
import 'dotenv/config';

const code = process.argv[2];
const { ZOHO_CLIENT_ID, ZOHO_CLIENT_SECRET } = process.env;
const accounts = (process.env.ZOHO_ACCOUNTS_URL || 'https://accounts.zoho.com').replace(/\/+$/, '');

if (!code) {
  console.error('Usage: npm run zoho:token -- <grant_code>');
  process.exit(1);
}
if (!ZOHO_CLIENT_ID || !ZOHO_CLIENT_SECRET) {
  console.error('ใส่ ZOHO_CLIENT_ID และ ZOHO_CLIENT_SECRET ใน .env ก่อน');
  process.exit(1);
}

const res = await fetch(`${accounts}/oauth/v2/token`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    grant_type: 'authorization_code',
    client_id: ZOHO_CLIENT_ID,
    client_secret: ZOHO_CLIENT_SECRET,
    code,
  }),
});
const json = await res.json();

if (json.error || !json.refresh_token) {
  console.error('ไม่สำเร็จ:', json);
  console.error('ตรวจว่า grant code ยังไม่หมดอายุ (ปกติ 3-10 นาที) และ ZOHO_ACCOUNTS_URL ตรงกับ DC');
  process.exit(1);
}

console.log('\nสำเร็จ! ใส่ค่านี้ใน .env:\n');
console.log(`ZOHO_REFRESH_TOKEN=${json.refresh_token}`);
if (json.api_domain) console.log(`ZOHO_API_DOMAIN=${json.api_domain}`);
console.log('\n(access token จะถูกสร้างใหม่อัตโนมัติจาก refresh token ไม่ต้องเก็บ)');
