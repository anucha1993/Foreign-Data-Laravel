<?php

use App\Models\User;
use App\Services\GoogleTranslate;
use App\Services\TicketNotifier;
use App\Support\Locales;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * แลก Grant Code (จาก Self Client) เป็น Refresh Token ครั้งเดียว
 *   Scope: ZohoCRM.modules.custom.READ,WorkDrive.files.READ
 *   php artisan zoho:token <grant_code>
 */
Artisan::command('zoho:token {code : Grant code จาก api-console.zoho.com (Self Client)}', function (string $code) {
    $cfg = config('services.zoho');
    if (! $cfg['client_id'] || ! $cfg['client_secret']) {
        $this->error('ใส่ ZOHO_CLIENT_ID และ ZOHO_CLIENT_SECRET ใน .env ก่อน');

        return 1;
    }

    $json = Http::asForm()->post("{$cfg['accounts_url']}/oauth/v2/token", [
        'grant_type' => 'authorization_code',
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'code' => $code,
    ])->json() ?? [];

    if (isset($json['error']) || empty($json['refresh_token'])) {
        $this->error('ไม่สำเร็จ: '.json_encode($json, JSON_UNESCAPED_UNICODE));
        $this->line('ตรวจว่า grant code ยังไม่หมดอายุ และ ZOHO_ACCOUNTS_URL ตรงกับ Data Center');

        return 1;
    }

    $this->info('สำเร็จ! ใส่ค่านี้ใน .env:');
    $this->line("ZOHO_REFRESH_TOKEN={$json['refresh_token']}");
    if (! empty($json['api_domain'])) {
        $this->line("ZOHO_API_DOMAIN={$json['api_domain']}");
    }

    return 0;
})->purpose('แลก Zoho grant code เป็น refresh token');

/**
 * แปลข้อความหน้าเว็บ (lang/en.json) เป็นภาษาอื่นด้วย Google Translate แล้วบันทึกเป็น lang/{locale}.json
 *   php artisan lang:translate            # ทุกภาษา เฉพาะข้อความที่ยังไม่มีคำแปล
 *   php artisan lang:translate my --force # แปลภาษาพม่าใหม่ทั้งหมด (ทับที่แก้ไว้)
 * แปลจากภาษาอังกฤษ (ได้ผลดีกว่าแปลจากไทยโดยตรง) — ล่ามแก้ไขไฟล์ json ได้ คำแปลเดิมจะไม่ถูกทับ
 */
Artisan::command('lang:translate {locales?* : เช่น my km lo zh (ว่าง = ทุกภาษา)} {--force : แปลทับคำแปลเดิม}', function (GoogleTranslate $google) {
    if (! $google->isConfigured()) {
        $this->error('ใส่ GOOGLE_TRANSLATE_API_KEY ใน .env ก่อน');

        return 1;
    }

    $source = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
    $targets = $this->argument('locales') ?: array_diff(array_keys(Locales::SUPPORTED), ['th', 'en']);

    foreach ($targets as $locale) {
        if (! Locales::has($locale) || in_array($locale, ['th', 'en'], true)) {
            $this->warn("ข้าม {$locale}: ไม่ใช่ภาษาที่ต้องแปล");

            continue;
        }

        $file = lang_path("{$locale}.json");
        $existing = is_file($file) ? json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR) : [];
        $todo = $this->option('force') ? $source : array_diff_key($source, $existing);

        $translated = $google->translateMany(array_values($todo), Locales::googleCode($locale), 'en');

        $out = [];
        foreach ($source as $key => $english) {
            $out[$key] = isset($todo[$key]) ? ($translated[$english] ?? $existing[$key] ?? $english) : $existing[$key];
        }

        file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        $this->info(sprintf('%s (%s): แปลใหม่ %d / ทั้งหมด %d ข้อความ', $locale, Locales::label($locale), count($translated), count($out)));
    }

    return 0;
})->purpose('แปลข้อความหน้าเว็บเป็นภาษาอื่นด้วย Google Translate');

/**
 * สร้าง/เปลี่ยนรหัสผ่านบัญชี Admin (ดู Ticket)
 *   php artisan admin:create admin@example.com --name="ชื่อเจ้าหน้าที่"
 */
Artisan::command('admin:create {email} {--name= : ชื่อที่แสดง} {--password= : ไม่ใส่ = ถามในหน้าจอ}', function (string $email) {
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('อีเมลไม่ถูกต้อง');

        return 1;
    }

    $password = $this->option('password') ?: $this->secret('รหัสผ่าน (อย่างน้อย 10 ตัวอักษร)');
    if (strlen((string) $password) < 10) {
        $this->error('รหัสผ่านต้องมีอย่างน้อย 10 ตัวอักษร');

        return 1;
    }

    $user = User::updateOrCreate(['email' => strtolower($email)], [
        'name' => $this->option('name') ?: strstr($email, '@', true),
        'password' => $password,
    ]);

    $this->info(($user->wasRecentlyCreated ? 'สร้างบัญชี' : 'อัปเดตรหัสผ่าน')." {$user->email} แล้ว — เข้าใช้ที่ /admin/login");

    return 0;
})->purpose('สร้างบัญชี Admin สำหรับดู Ticket');

/**
 * ทดสอบการส่งอีเมล (SMTP) — ไม่ใส่ผู้รับ = ส่งถึงผู้รับแจ้งเตือน Ticket
 *   php artisan mail:test
 */
Artisan::command('mail:test {to? : อีเมลผู้รับ}', function (?string $to = null) {
    $recipients = $to ? [$to] : TicketNotifier::staffRecipients();
    if (! $recipients) {
        $this->error('ไม่มีผู้รับ: ใส่ TICKET_NOTIFY_EMAILS ใน .env หรือสร้างบัญชี Admin ก่อน');

        return 1;
    }

    $this->line('Mailer: '.config('mail.default').' · From: '.config('mail.from.address'));
    try {
        Mail::raw('ทดสอบการส่งอีเมลจากระบบข้อมูลแรงงาน ('.now()->timezone('Asia/Bangkok')->format('d/m/Y H:i').')', function ($m) use ($recipients) {
            $m->to($recipients)->subject('ทดสอบอีเมล — '.config('foreign.company.name'));
        });
    } catch (Throwable $e) {
        $this->error('ส่งไม่สำเร็จ: '.$e->getMessage());

        return 1;
    }

    $this->info('ส่งแล้วถึง: '.implode(', ', $recipients));
    if (config('mail.default') === 'log') {
        $this->warn('MAIL_MAILER=log — อีเมลถูกเขียนลง storage/logs/laravel.log ไม่ได้ส่งจริง');
    }

    return 0;
})->purpose('ส่งอีเมลทดสอบ SMTP');
