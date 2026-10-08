# TFG Foreign Data (Laravel)

ระบบให้ **แรงงานต่างด้าวดูข้อมูลและเอกสารของตัวเอง** โดยดึงข้อมูลสดจากโมดูล `Foreign_Data` ใน Zoho CRM
ทีมไม่ต้องกรอกข้อมูลซ้ำ

```
แรงงานกรอกเลขพาสปอร์ต + รหัสผ่าน → server ค้นใน Zoho CRM → แสดงข้อมูล/วันหมดอายุ/เอกสาร (ไฟล์จาก WorkDrive)
```

> โค้ด Node.js เดิม (ระบบ Tracking) ย้ายไปไว้ที่ `_legacy-node/` เพื่ออ้างอิง —
> ระบบ Tracking เวอร์ชัน Laravel อยู่ที่โปรเจกต์ `tfg-tracking-laravel`

## สิ่งที่แรงงานเห็น (3 แท็บ เหมือนแอป)
มือถือ: เมนูติดด้านล่างจอ / Desktop: เมนูแนวตั้งในแถบซ้ายใต้การ์ดโปรไฟล์ (จำแท็บใน URL `#status` `#files` `#info`)

1. **สถานะเอกสาร** (หน้าแรก) — การ์ดละ 1 เอกสาร: Passport, VISA, Work Permit, รายงานตัว 90 วัน, บัตรชมพู
   แสดงเลขที่ สถานะ วันหมดอายุ และจำนวนวันที่เหลือตัวใหญ่ เรียงจากใกล้หมดอายุที่สุด
   (เขียว = ปกติ, ส้ม = เหลือ ≤ 30 วัน, แดง = หมดอายุ) ข้อมูลอื่นของเอกสารอยู่ใน "รายละเอียดเพิ่มเติม"
2. **ไฟล์เอกสาร** — ไฟล์จาก subform `LinkingModule10` เปิดดู/ดาวน์โหลดผ่าน server (ไม่เปิดเผยลิงก์ WorkDrive)
3. **ข้อมูลส่วนตัว** — ข้อมูลส่วนตัว, การทำงาน, ที่อยู่, ประกันสังคม/ธนาคาร, ผู้ติดต่อฉุกเฉิน พับเก็บทีละหมวด เปิดเฉพาะหมวดแรก

รองรับ 6 ภาษา (ไทย, อังกฤษ, พม่า, เขมร, ลาว, จีน), dark mode, สั่งพิมพ์ได้ (พิมพ์ครบทุกแท็บ)

**ไม่แสดง:** ฟิลด์ที่ไม่อยู่ใน whitelist ทั้งหมด (Owner, Tag, หมายเหตุภายใน, flag ของระบบ ฯลฯ) — ดู `app/Support/ForeignProfile.php`
ซ่อนเพิ่มได้ด้วย `FOREIGN_HIDDEN_FIELDS`

## ติดตั้ง
```bash
composer install
cp .env.example .env
php artisan key:generate
```

### Zoho OAuth (ทำครั้งเดียว)
1. https://api-console.zoho.com → **Self Client** → ใส่ Client ID / Secret ใน `.env`
2. **Generate Code** → Scope: `ZohoCRM.modules.custom.READ,WorkDrive.files.READ` → Create
3. ภายใน 10 นาที: `php artisan zoho:token 1000.xxxxxxxx.xxxxxxxx`
4. นำ `ZOHO_REFRESH_TOKEN` ที่ได้ใส่ `.env`

> refresh token ของระบบ Tracking เดิมมีแค่ scope Deals ใช้กับระบบนี้ไม่ได้ ต้องสร้างใหม่

## รัน
```bash
php artisan serve        # http://localhost:8000/foreign
php artisan test         # ทดสอบ (ไม่เรียก Zoho จริง)
```
ไม่ต้อง build frontend (CSS อยู่ที่ `public/css/foreign.css`) ฐานข้อมูลใช้เฉพาะระบบ Ticket (MySQL/MariaDB) — session/cache เป็นไฟล์

Production: PHP 8.3+ (ext: intl, curl, mbstring), ชี้ web root ไปที่ `public/`, ต้องเป็น **HTTPS**,
ตั้ง `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`

## ตั้งค่าใน `.env`
| ตัวแปร | ความหมาย |
|---|---|
| `FOREIGN_SESSION_MINUTES` | อายุการล็อกอิน (นาที) นับจากตอนล็อกอิน (default 30) |
| `FOREIGN_HIDDEN_FIELDS` | ซ่อนฟิลด์เพิ่ม (API name คั่นด้วย ,) เช่น `field20,field18` (เลขบัญชี, เลขผู้เสียภาษี) |
| `FOREIGN_BLOCKED_STATUSES` | สถานะพนักงานที่ห้ามล็อกอิน (default `ปิดระบบ`) |
| `FOREIGN_EXPIRY_WARNING_DAYS` | เตือนเอกสารใกล้หมดอายุ (default 30 วัน) |
| `FOREIGN_LOGIN_MAX_PER_IP` / `_PER_PASSPORT` | จำกัดการลองล็อกอิน: ต่อ IP ต่อนาที / ต่อเลขพาสปอร์ตต่อ 15 นาที |
| `CACHE_TTL_SECONDS` | แคชข้อมูล CRM (default 60) |
| `GOOGLE_TRANSLATE_API_KEY` | API key ของ Google Cloud Translation (ว่าง = ใช้คำแปลในไฟล์อย่างเดียว) |
| `COMPANY_NAME`, `CONTACT_PHONE`, `CONTACT_LINE_URL` | ข้อมูลติดต่อบนหน้าเว็บ |

## ระบบ Ticket (แจ้งปัญหา / ขอแก้ไขข้อมูล)
**แรงงาน** — แท็บ "แจ้งเรื่อง" (แท็บที่ 4)
- **แจ้งปัญหา**: หัวข้อ + รายละเอียด + รูป
- **ขอแก้ไขข้อมูล**: เลือกช่องที่ผิด (ระบบดึงค่าปัจจุบันจาก CRM มาให้) + ค่าที่ถูกต้อง + รูปเอกสาร — หรือกด "ข้อมูลไม่ถูกต้อง? ขอแก้ไข" ท้ายแต่ละหมวดในแท็บข้อมูลส่วนตัว
- ตอบโต้กับเจ้าหน้าที่ได้ (แชท), คำตอบเจ้าหน้าที่แปลเป็นภาษาของแรงงานอัตโนมัติ, มีจุดแดงเมื่อมีคำตอบใหม่

**Admin** — `/admin/login`
- รายการเรื่อง กรองตามสถานะ (รอดำเนินการ / กำลังดำเนินการ / ดำเนินการแล้ว / ปิดเรื่อง) + ค้นหา รหัสเรื่อง/Passport/ชื่อ/นายจ้าง
- ตอบกลับ + แนบรูป + เปลี่ยนสถานะ, ปุ่ม "แปลข้อความแรงงานเป็นภาษาไทย"
- การแก้ข้อมูลจริงทำใน Zoho CRM (token เป็นสิทธิ์อ่านอย่างเดียว) แล้วเปลี่ยนสถานะเป็น "ดำเนินการแล้ว"
- สร้างบัญชี: `php artisan admin:create staff@example.com --name="ชื่อ"` (รหัสผ่าน ≥ 10 ตัว, รันซ้ำ = เปลี่ยนรหัสผ่าน)

**รูปแนบ** — เก็บใน **Cloudflare R2** (bucket แบบ private, path `tickets/{รหัสเรื่อง}/{uuid}.jpg`)
- หลายรูป สูงสุด 10 รูป/ข้อความ, **รูปละไม่เกิน 5MB** (jpg, png, webp, heic) ตรวจทั้งในเบราว์เซอร์และ server
- เปิดรูปผ่าน server หลังตรวจสิทธิ์เท่านั้น (แรงงานเห็นเฉพาะเรื่องของตัวเอง)
- ตั้งค่า `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` (`https://<ACCOUNT_ID>.r2.cloudflarestorage.com`)
- PHP ต้องตั้ง `upload_max_filesize` ≥ 5M และ `post_max_size` ≥ 55M (10 รูป × 5MB)

**ฐานข้อมูล** — ตาราง `tickets`, `ticket_messages`, `ticket_attachments`, `users` (= Admin) → `php artisan migrate`

## ภาษา (Google Cloud Translation)
- ภาษา: `th` ไทย, `en` อังกฤษ, `my` พม่า, `km` เขมร (กัมพูชา), `lo` ลาว, `zh` จีน — เลือกจากเมนู 🌐 (จำไว้ใน cookie)
- **ข้อความหน้าเว็บ**: ต้นฉบับอยู่ที่ `lang/en.json` (key = ข้อความไทย) แล้วสร้างภาษาอื่นด้วย
  ```bash
  php artisan lang:translate          # แปลเฉพาะข้อความใหม่ที่ยังไม่มีคำแปล (ไม่ทับที่ล่ามแก้ไว้)
  php artisan lang:translate my --force  # แปลใหม่ทั้งไฟล์ (ทับทั้งหมด)
  ```
  เพิ่มข้อความใหม่ในหน้าเว็บ → ใส่ใน `lang/en.json` → รัน `lang:translate`
- **ค่าจาก CRM** (สถานะ, ประเภทกลุ่มนำเข้า, ชื่อเอกสาร ฯลฯ) ที่ไม่มีในไฟล์ → แปลตอนแสดงผลรวมเป็น 1 API call ต่อหน้า และแคช 30 วัน
- ไม่มี `GOOGLE_TRANSLATE_API_KEY` หรือ API ล่ม → ใช้คำแปลในไฟล์, ถ้าไม่มีใช้ภาษาอังกฤษ, สุดท้ายใช้ข้อความเดิม (หน้าไม่พัง)
- ชื่อคน/ชื่อบริษัท ไม่แปล
- ⚠️ คำแปลเป็นแบบเครื่อง (machine translation) ควรให้ล่ามตรวจ `lang/my.json`, `km.json`, `lo.json`, `zh.json` ก่อนใช้งานจริง

## Endpoints
| Path | ใช้ทำอะไร |
|---|---|
| `GET /foreign/login`, `POST /foreign/login` | หน้าเข้าสู่ระบบ (เลขพาสปอร์ต + รหัสผ่าน) |
| `GET /foreign` | หน้าข้อมูลแรงงาน (ต้องล็อกอิน) |
| `GET /foreign/documents/{rowId}` | เปิดไฟล์เอกสาร (`?download=1` = ดาวน์โหลด) |
| `GET /foreign/photo` | รูปโปรไฟล์จาก CRM |
| `POST /foreign/logout` | ออกจากระบบ |
| `GET /healthz` | health check |

## ความปลอดภัย
- ล็อกอิน: **ชื่อผู้ใช้ = เลขพาสปอร์ต** (`Passport_ID`, ไม่สนตัวพิมพ์เล็ก/ใหญ่และช่องว่าง) + **รหัสผ่าน = 4 ตัวหน้า + 4 ตัวท้ายของเลขบัตรประจำตัว** (`National_ID` 13 หลัก) เช่น `6681090240435` → `66810435`
  - ข้อความผิดพลาดไม่บอกว่าผิดช่องไหน
  - แรงงานที่ไม่มีเลขบัตรประจำตัวใน CRM จะล็อกอินไม่ได้
- จำกัดการลองล็อกอินต่อ IP และต่อเลขพาสปอร์ต, session หมดอายุตายตัว + เข้ารหัส, regenerate session ID ตอนล็อกอิน
- เปิดเอกสารได้เฉพาะแถวที่อยู่ใน record ของตัวเอง, ไฟล์ส่งผ่าน server, `Cache-Control: private, no-store` ทุก response
- `noindex`, `X-Frame-Options: DENY`, credential ทั้งหมดอยู่ใน `.env` ฝั่ง server

## โครงสร้าง
```
app/Services/ZohoAuth.php        refresh/แคช access token (cache lock กัน refresh ซ้อน)
app/Services/ZohoCrm.php         เรียก CRM API + retry เมื่อ 401
app/Services/ForeignData.php     ล็อกอิน (พาสปอร์ต + รหัสผ่านจากเลขบัตร), ดึง record, ตรวจสิทธิ์และดาวน์โหลดเอกสาร WorkDrive
app/Support/ForeignProfile.php   whitelist ฟิลด์/หมวด, คำนวณวันหมดอายุ, จัดรูปแบบวันที่ (ค.ศ. ทุกภาษา)
app/Http/Controllers/Foreign/    AuthController, ProfileController
resources/views/foreign/         login, profile, message (+ components/layout)
app/Services/GoogleTranslate.php Google Cloud Translation (batch + แคช + กัน placeholder)
app/Support/Locales.php          รายการภาษา, ฟอนต์, รูปแบบวันที่, ลำดับการหาคำแปล
lang/*.json                      คำแปล (en = ต้นฉบับ, my/km/lo/zh สร้างด้วย lang:translate)
```
