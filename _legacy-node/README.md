# TFG Project Tracking (QR)

หน้าเว็บให้ลูกค้าสแกน QR แล้วดู timeline งาน โดยดึงข้อมูลสดจาก Zoho CRM
(Deal + subform **Tracking System** ที่ทีมใช้อยู่แล้ว) ทีมไม่ต้องกรอกข้อมูลซ้ำ

```
ทีมอัปเดต Tracking System ใน Deal  →  เว็บนี้ดึงผ่าน API  →  ลูกค้าสแกน QR ดู timeline
```

## ข้อมูลที่ลูกค้าเห็น (whitelist)
เลขที่งาน (M5L Job Code), ชื่อบริษัทลูกค้า, ประเภทใบงาน, วันเริ่มงาน, วันคาดว่าจะเสร็จ,
ความคืบหน้า และแต่ละขั้นตอน (สถานะงาน, สถานะการดำเนินการ, วันประมาณการ, วันเสร็จจริง, เหตุผลที่ล่าช้า)

**ไม่แสดง:** มูลค่าดีล, ค่าใช้จ่าย, ชื่อ/ข้อมูลแรงงาน, โน้ตฝ่ายขาย และฟิลด์อื่นทั้งหมด

## 1. ตั้งค่า Zoho OAuth (ทำครั้งเดียว)
1. เข้า https://api-console.zoho.com → **Self Client** → คัดลอก Client ID / Secret ใส่ `.env`
2. แท็บ **Generate Code** → Scope: `ZohoCRM.modules.deals.READ` → Time: 10 นาที → Create
3. รันคำสั่ง (ภายใน 10 นาที):
   ```bash
   cp .env.example .env      # แล้วใส่ Client ID/Secret
   npm install
   npm run zoho:token -- 1000.xxxxxxxx.xxxxxxxx
   ```
4. นำ `ZOHO_REFRESH_TOKEN` ที่ได้ใส่ใน `.env`

แอปจะใช้ refresh token สร้าง access token ใหม่อัตโนมัติ (แคช ~1 ชม., refresh ก่อนหมดอายุ,
ถ้าโดน 401 จะ refresh แล้วลองใหม่ให้เอง) ไม่ต้องเก็บ access token

> Org นี้อยู่ Data Center **US (.com)** ค่า default ใน `.env.example` จึงถูกต้องแล้ว

## 2. ตั้งค่าอื่นใน `.env`
| ตัวแปร | ความหมาย |
|---|---|
| `PUBLIC_BASE_URL` | โดเมนที่ลูกค้าเข้า เช่น `https://track.thefirstgoodmangroup.com` |
| `TRACKING_SECRET` | คีย์เซ็นลิงก์ (`openssl rand -hex 32`) **เปลี่ยน = ลิงก์เก่าใช้ไม่ได้ทั้งหมด** |
| `ADMIN_API_KEY` | คีย์สำหรับปุ่มใน CRM เรียกสร้างลิงก์ |
| `ALLOWED_PIPELINES` | แสดงเฉพาะ Pipeline นี้ (default `Tracking`) |
| `HIDDEN_STAGES` | Stage ที่ปิดการแสดงผล (default `ยกเลิก,ไม่ดำเนินการ`) |
| `SHOW_DELAY_REASON` | ให้ลูกค้าเห็นเหตุผลที่ล่าช้าไหม |
| `CONTACT_PHONE`, `CONTACT_LINE_URL` | ปุ่มติดต่อด้านล่างหน้า |

## 3. รัน
```bash
npm start            # http://localhost:3000
npm test             # ทดสอบ
npm run link -- 6274719000173346428   # สร้างลิงก์ + ไฟล์ QR จากเครื่อง
```
Docker: `docker build -t tfg-tracking . && docker run --env-file .env -p 3000:3000 tfg-tracking`

Deploy ได้ทุกที่ที่รัน Node 20+ ได้ เช่น Zoho Catalyst AppSail, Render, Railway, Cloud Run หรือ VPS
(ต้องเป็น HTTPS)

## 4. ให้ Zoho CRM สร้างลิงก์ + QR เอง
ดู `deluge/generate_tracking_link.dg` — Zoho คำนวณลายเซ็นเองด้วย
`zoho.encryption.hmacsha256(secret, "deal:"+dealId, "hex")` ตัด 32 ตัวแรก (ตรงกับ `src/token.js`)

1. สร้าง Org Variable `tfg_tracking_secret` (= `TRACKING_SECRET`) และ `tfg_tracking_base_url`
2. สร้างฟิลด์ URL ใน Deals: `Tracking_URL`, `Tracking_QR`
3. ตั้ง Workflow (Pipeline = Tracking และ Tracking URL ว่าง) ให้เรียก `automation.tfg_tracking_qr`
   หรือใช้ปุ่ม `button.tfg_tracking_link`

ผลลัพธ์: Deal มีลิงก์และ URL รูป QR ใช้ใน Email Template ได้ และแนบไฟล์ QR ใน Attachments
(การแนบไฟล์ต้อง deploy server ขึ้นโดเมนจริงก่อน)

## Endpoints
| Path | ใช้ทำอะไร |
|---|---|
| `GET /t/:token` | หน้า timeline สำหรับลูกค้า |
| `GET /qr/:token.png?size=480` | รูป QR ของลิงก์ |
| `GET /api/admin/link?deal_id=…` (header `X-Admin-Key`) | สร้างลิงก์ + QR |
| `GET /healthz` | health check |

## ความปลอดภัย
- ลิงก์ = `dealId.ลายเซ็น HMAC` เดาหรือแก้เลขเพื่อดูงานของลูกค้ารายอื่นไม่ได้
- ใส่ `noindex` กันไม่ให้ Google เก็บหน้า, จำกัด request ต่อ IP, แคช CRM 60 วินาที
- Credential ทั้งหมดอยู่ใน ENV ฝั่ง server เท่านั้น ห้าม commit ไฟล์ `.env`
- ยกเลิกลิงก์รายงาน: ย้าย Deal ไป Stage ใน `HIDDEN_STAGES`; ยกเลิกทั้งหมด: เปลี่ยน `TRACKING_SECRET`


---

# ระบบข้อมูลแรงงาน (Foreign Data) — `/foreign`

แรงงานเข้าดูข้อมูลและเอกสารของตัวเองจากโมดูล **Foreign Data** ใน CRM

## การเข้าสู่ระบบ
- **เลขพาสปอร์ต** (`Passport_ID`) + **รหัส 8 หลัก** = เลขบัตรประจำตัว (`National_ID`) 4 ตัวแรก + 4 ตัวท้าย
  เช่น 6920000031376 → 69201376
- ใส่ผิด 5 ครั้ง ล็อก 15 นาที / ผิดสะสม 10 ครั้ง ล็อก 24 ชม. / จำกัดต่อ IP ด้วย
- ข้อความ error เหมือนกันทุกกรณี (ไม่บอกว่าพาสปอร์ตนี้มีในระบบหรือไม่)
- Session เป็น cookie แบบเซ็นชื่อ (HttpOnly, SameSite=Strict, Secure เมื่อใช้ https) อายุ 30 นาที
- แรงงานที่ไม่มีเลขบัตรใน CRM จะเข้าระบบไม่ได้

## ข้อมูลที่แสดง
แสดงทุกฟิลด์ในโมดูลที่มีค่า แบ่งเป็นกลุ่ม (ส่วนตัว, Passport, VISA, Work Permit, 90 วัน, บัตรชมพู,
การทำงาน, ที่อยู่, ประกันสังคม/ธนาคาร, ผู้ติดต่อฉุกเฉิน) ฟิลด์ใหม่ที่เพิ่มใน CRM จะขึ้นใน "ข้อมูลอื่นๆ" อัตโนมัติ
ฟิลด์ "จะหมดอายุ (วัน)" แสดงเป็นป้ายสี: แดง ≤30 วัน, ส้ม ≤90 วัน

**ซ่อนโดยค่าเริ่มต้น:** ฟิลด์ระบบ/เจ้าของเรคคอร์ด, หมายเหตุภายใน (`field6`), วันที่หลบหนี, ลิงก์โฟลเดอร์ WorkDrive,
ช่อง "นายจ้างต่อเอง" ฯลฯ — ซ่อนเพิ่มได้ด้วย `FOREIGN_HIDDEN_FIELDS`

## เอกสาร (ดูอย่างเดียว ไม่ต้องแชร์สิทธิ์ทางอีเมล)
- อ่านจาก subform "เอกสารประจำตัว" (`LinkingModule10`) ฟิลด์ลิงก์ไฟล์ `https://workdrive.zoho.com/file/<id>`
- server ดาวน์โหลดไฟล์จาก WorkDrive ด้วย token ของระบบ แล้วส่งต่อให้ผู้ใช้ → ไม่ต้องแชร์ไฟล์ให้แรงงาน
- เปิดได้เฉพาะเอกสารที่อยู่ในเรคคอร์ดของผู้ที่ล็อกอิน (ระบบไม่รับ file id จากผู้ใช้)
- **ไม่ใช้** ลิงก์โฟลเดอร์ (`WorkDrive_File_URL` แบบ /folder/) เพราะโฟลเดอร์รวมไฟล์ของแรงงานหลายคน
- หน้าดูเอกสาร: PDF แสดงผ่าน pdf.js, รูปแสดงเป็นภาพ, ไม่มีปุ่มดาวน์โหลด/พิมพ์, มีลายน้ำ (พาสปอร์ตแบบปิดบาง + เวลา)
  > ข้อจำกัด: ไม่มีเว็บใดกันการบันทึกได้ 100% (เช่น แคปหน้าจอ) ระบบนี้กันการดาวน์โหลดทั่วไปและติดลายน้ำเพื่อติดตามได้
- รองรับไฟล์ pdf, jpg, jpeg, png, gif, webp
- ทุกการเข้าสู่ระบบและการเปิดเอกสารถูกบันทึก log (JSON) โดยปิดบังเลขพาสปอร์ตบางส่วน

## ตั้งค่า
1. สร้าง refresh token ใหม่ด้วย scope ที่เพิ่ม (ดู `scripts/get-refresh-token.js`)
   บัญชีที่สร้าง token ต้องมีสิทธิ์เข้าถึงไฟล์ใน WorkDrive ทีม
2. `.env`: `FOREIGN_ENABLED=true`, `FOREIGN_SESSION_SECRET=<สุ่ม 64 ตัว>`
3. เปิด `https://<โดเมน>/foreign`
