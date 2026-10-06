<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * ข้อมูลแรงงานจากโมดูล Foreign_Data ใน Zoho CRM
 * - ล็อกอินด้วยเลขพาสปอร์ต (Passport_ID) + รหัสผ่าน = 4 ตัวหน้า + 4 ตัวท้ายของเลขบัตรประจำตัว (National_ID 13 หลัก)
 * - เอกสารอยู่ใน subform LinkingModule10 (ลิงก์ไฟล์ WorkDrive ใน field5)
 */
class ForeignData
{
    public const MODULE = 'Foreign_Data';

    public const DOCS_SUBFORM = 'LinkingModule10';

    public function __construct(
        private readonly ZohoCrm $crm,
        private readonly string $workdriveDownloadUrl,
        private readonly array $blockedStatuses = [],
    ) {}

    /** ตัดช่องว่าง/ขีด และเป็นตัวพิมพ์ใหญ่ — คืน null ถ้ารูปแบบไม่ใช่เลขพาสปอร์ต */
    public static function normalizePassport(?string $value): ?string
    {
        $p = strtoupper(preg_replace('/[\s\-]/', '', (string) $value));

        return preg_match('/^[A-Z0-9]{5,20}$/', $p) ? $p : null;
    }

    /** เหลือแต่ตัวเลข — คืน null ถ้าไม่ใช่ 13 หลัก */
    public static function normalizeNationalId(?string $value): ?string
    {
        $id = preg_replace('/\D/', '', (string) $value);

        return strlen($id) === 13 ? $id : null;
    }

    /** รหัสผ่าน = 4 ตัวหน้า + 4 ตัวท้าย เช่น 6920000031376 -> 69201376 */
    public static function passwordFor(string $nationalId): string
    {
        return substr($nationalId, 0, 4).substr($nationalId, -4);
    }

    /** คืน record id ถ้าเลขพาสปอร์ต + รหัสผ่านถูกต้องและเป็นแรงงานที่ล็อกอินได้ ไม่งั้นคืน null */
    public function findForLogin(string $passport, string $password): ?string
    {
        $passport = self::normalizePassport($passport);
        $password = trim($password);
        if (! $passport || ! preg_match('/^\d{8}$/', $password)) {
            return null;
        }

        $matches = array_filter(
            $this->crm->search(self::MODULE, "(Passport_ID:equals:{$passport})"),
            function (array $r) use ($passport, $password) {
                $nationalId = self::normalizeNationalId($r['National_ID'] ?? '');

                return self::normalizePassport($r['Passport_ID'] ?? '') === $passport
                    && $nationalId !== null // ไม่มีเลขบัตรใน CRM = ล็อกอินไม่ได้
                    && hash_equals(self::passwordFor($nationalId), $password)
                    && ! in_array($r['Foreigners_Status'] ?? '', $this->blockedStatuses, true);
            },
        );

        if (! $matches) {
            return null;
        }

        // พาสเดียวกันอาจมีหลาย record (เช่น เปลี่ยนนายจ้าง) -> ใช้อันที่แก้ไขล่าสุด
        usort($matches, fn ($a, $b) => strcmp($b['Modified_Time'] ?? '', $a['Modified_Time'] ?? ''));

        return (string) $matches[0]['id'];
    }

    public function get(string $id): ?array
    {
        $record = $this->crm->getRecord(self::MODULE, $id);

        if (! $record || in_array($record['Foreigners_Status'] ?? '', $this->blockedStatuses, true)) {
            return null;
        }

        return $record;
    }

    /** แถว "รูปถ่ายแรงงาน" ใน subform เอกสาร (base_name = IMG) ที่มีไฟล์ใน WorkDrive */
    public static function photoDocument(array $record): ?array
    {
        foreach ($record[self::DOCS_SUBFORM] ?? [] as $row) {
            $isPhoto = strtoupper((string) ($row['base_name'] ?? '')) === 'IMG' || ($row['field1'] ?? '') === 'รูปถ่ายแรงงาน';
            if ($isPhoto && self::workdriveResourceId($row['field5'] ?? null)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * รูปโปรไฟล์: รูปของ record ใน CRM ก่อน ไม่มีค่อยใช้ไฟล์ "รูปถ่ายแรงงาน" จาก WorkDrive
     * ย่อเหลือ 256px (ไฟล์สแกนอาจใหญ่หลาย MB) และแคชไว้ 1 วัน
     */
    public function photo(array $record): ?array
    {
        if (! empty($record['Record_Image'])) {
            $photo = $this->crm->getPhoto(self::MODULE, (string) $record['id']);
            if ($photo) {
                return $photo;
            }
        }

        $row = self::photoDocument($record);
        if (! $row) {
            return null;
        }

        $key = 'foreign:photo:'.$record['id'].':'.self::workdriveResourceId($row['field5']);

        return Cache::remember($key, now()->addDay(), function () use ($row) {
            $file = $this->downloadDocument($row);

            return $file && str_starts_with($file['type'], 'image/') ? self::thumbnail($file) : null;
        });
    }

    /** ย่อรูปเป็นสี่เหลี่ยมจัตุรัส (crop กลางภาพ) — ถ้าย่อไม่ได้ส่งไฟล์เดิม */
    public static function thumbnail(array $file, int $size = 256): array
    {
        $src = @imagecreatefromstring($file['body']);
        if (! $src) {
            return $file;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        // รูปถ่ายติดบัตรมักมีหน้าอยู่ค่อนบน -> crop จากด้านบนเมื่อภาพเป็นแนวตั้ง
        $x = intdiv($w - $side, 2);
        $y = $h > $w ? intdiv($h - $side, 4) : intdiv($h - $side, 2);

        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst, $src, 0, 0, $x, $y, $size, $size, $side, $side);

        ob_start();
        imagejpeg($dst, null, 85);

        return ['body' => ob_get_clean(), 'type' => 'image/jpeg'];
    }

    /** หาแถวเอกสารจาก record ของแรงงานคนนี้เท่านั้น (กันเปิดเอกสารของคนอื่น) */
    public static function findDocument(array $record, string $rowId): ?array
    {
        foreach ($record[self::DOCS_SUBFORM] ?? [] as $row) {
            if ((string) ($row['id'] ?? '') === $rowId) {
                return $row;
            }
        }

        return null;
    }

    /** https://workdrive.zoho.com/file/<resource_id> -> resource_id */
    public static function workdriveResourceId(?string $url): ?string
    {
        return preg_match('~^https://workdrive\.zoho\.[a-z.]+/(?:.*/)?file/([A-Za-z0-9]{20,64})(?:[/?#]|$)~', (string) $url, $m)
            ? $m[1]
            : null;
    }

    /** ดาวน์โหลดไฟล์จาก WorkDrive ผ่าน server (ลูกค้าไม่เห็นลิงก์ WorkDrive) */
    public function downloadDocument(array $row): ?array
    {
        $resourceId = self::workdriveResourceId($row['field5'] ?? null);
        if (! $resourceId) {
            return null;
        }

        $res = $this->crm->authorizedGet("{$this->workdriveDownloadUrl}/{$resourceId}");
        if (! $res->successful()) {
            throw new ZohoCrmException("WorkDrive download {$res->status()}", $res->status());
        }

        return [
            'body' => $res->body(),
            'type' => $res->header('Content-Type') ?: 'application/octet-stream',
        ];
    }
}
