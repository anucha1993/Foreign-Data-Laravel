<?php

$list = fn (?string $v) => array_values(array_filter(array_map('trim', explode(',', (string) $v))));

return [

    // อายุการล็อกอินแบบตายตัว (นาที) นับจากตอนล็อกอิน ไม่ต่ออายุอัตโนมัติ
    'session_minutes' => (int) env('FOREIGN_SESSION_MINUTES', 30),

    // ซ่อนฟิลด์เพิ่มเติม (API name คั่นด้วย ,) เช่น field20,field18
    'hidden_fields' => $list(env('FOREIGN_HIDDEN_FIELDS', '')),

    // สถานะพนักงานที่ไม่อนุญาตให้ล็อกอิน
    'blocked_statuses' => $list(env('FOREIGN_BLOCKED_STATUSES', 'ปิดระบบ')),

    // แจ้งเตือนเอกสารใกล้หมดอายุเมื่อเหลือไม่เกินกี่วัน
    'expiry_warning_days' => (int) env('FOREIGN_EXPIRY_WARNING_DAYS', 30),

    // จำกัดการลองล็อกอิน
    'login_max_per_ip' => (int) env('FOREIGN_LOGIN_MAX_PER_IP', 10),       // ต่อนาที
    'login_max_per_passport' => (int) env('FOREIGN_LOGIN_MAX_PER_PASSPORT', 5), // ต่อเลขพาสปอร์ต ต่อ 15 นาที

    'workdrive_download_url' => rtrim(env('ZOHO_WORKDRIVE_DOWNLOAD_URL', 'https://www.zohoapis.com/workdrive/api/v1/download'), '/'),

    'cache_ttl_seconds' => (int) env('CACHE_TTL_SECONDS', 60),

    'tickets' => [
        'disk' => 'r2',
        'max_images' => 10,     // ต่อข้อความ
        'max_image_kb' => 5120, // 5MB ต่อรูป
        // อีเมลเจ้าหน้าที่ที่รับแจ้งเตือน (คั่นด้วย ,) ว่าง = ส่งถึงบัญชี Admin ทุกคน
        'notify_emails' => $list(env('TICKET_NOTIFY_EMAILS', '')),
        // ส่งอีเมลถึงแรงงานด้วยหรือไม่ (รับเรื่องแล้ว / เจ้าหน้าที่ตอบกลับ / สถานะเปลี่ยน)
        'email_workers' => filter_var(env('TICKET_EMAIL_WORKERS', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'company' => [
        'name' => env('COMPANY_NAME', 'The First Good Man Group'),
        'phone' => env('CONTACT_PHONE', ''),
        'line_url' => env('CONTACT_LINE_URL', ''),
    ],

];
