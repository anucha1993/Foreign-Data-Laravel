<?php

namespace App\Support;

use App\Services\GoogleTranslate;
use Illuminate\Support\Facades\Lang;

/**
 * ภาษาที่รองรับ + การแปลข้อความที่มาจาก CRM (ภาษาไทย)
 * ลำดับการหาคำแปล: lang/{locale}.json -> Google Translate (แคช) -> ภาษาอังกฤษ -> ข้อความเดิม
 */
class Locales
{
    /** code => [ชื่อภาษา, รหัส Google, ICU locale, รูปแบบวันที่, ฟอนต์ Google Fonts (null = ใช้ฟอนต์หลัก)] */
    public const SUPPORTED = [
        'th' => ['ไทย', 'th', 'th_TH@calendar=gregorian', 'd MMM y', null], // ใช้ ค.ศ. ทุกภาษา (ไม่ใช้ พ.ศ.)
        'en' => ['English', 'en', 'en_US', 'd MMM y', null],
        'my' => ['မြန်မာ', 'my', 'my_MM@numbers=latn', 'd MMM y', 'Noto Sans Myanmar'],
        'km' => ['ខ្មែរ', 'km', 'km_KH', 'd MMM y', 'Noto Sans Khmer'],
        'lo' => ['ລາວ', 'lo', 'lo_LA', 'd MMM y', 'Noto Sans Lao'],
        'zh' => ['中文', 'zh-CN', 'zh_CN', 'y年M月d日', 'Noto Sans SC'],
    ];

    /** คำแปลที่ดึงมาแล้วใน request นี้ [locale][thai] => text */
    private static array $runtime = [];

    public static function has(?string $code): bool
    {
        return is_string($code) && isset(self::SUPPORTED[$code]);
    }

    public static function label(string $code): string
    {
        return self::SUPPORTED[$code][0];
    }

    public static function googleCode(string $code): string
    {
        return self::SUPPORTED[$code][1];
    }

    public static function icu(string $code): string
    {
        return self::SUPPORTED[$code][2] ?? 'en_US';
    }

    public static function datePattern(string $code): string
    {
        return self::SUPPORTED[$code][3] ?? 'd MMM y';
    }

    public static function font(string $code): ?string
    {
        return self::SUPPORTED[$code][4] ?? null;
    }

    /** ภาษาที่ไม่มีไฟล์คำแปลครบ ต้องพึ่ง Google ตอนแสดงผล */
    private static function needsMachine(string $locale): bool
    {
        return ! in_array($locale, ['th', 'en'], true);
    }

    /** ดึงคำแปลของหลายข้อความในการเรียก API ครั้งเดียว (เรียกก่อน t() เพื่อลดจำนวนครั้ง) */
    public static function prefetch(array $texts): void
    {
        $locale = app()->getLocale();
        if (! self::needsMachine($locale)) {
            return;
        }

        $todo = array_filter(array_unique($texts), fn ($t) => is_string($t) && $t !== ''
            && ! Lang::hasForLocale($t, $locale)
            && ! isset(self::$runtime[$locale][$t])
            && preg_match('/\p{Thai}/u', $t));

        if ($todo) {
            self::$runtime[$locale] = (self::$runtime[$locale] ?? [])
                + app(GoogleTranslate::class)->translateMany($todo, self::googleCode($locale), 'th');
        }
    }

    /**
     * ชื่อบริษัท (นายจ้าง/Agency): ภาษาไทยแสดงตามเดิม, ภาษาอื่นทุกภาษาแสดงเป็นภาษาอังกฤษ
     * (ชื่อบริษัทแปลเป็นพม่า/จีน ฯลฯ แล้วใช้ติดต่อจริงไม่ได้) — แปลไม่ได้ = ใช้ชื่อเดิม
     */
    public static function company(string $name): string
    {
        $name = trim($name);
        if ($name === '' || app()->getLocale() === 'th' || ! preg_match('/\p{Thai}/u', $name)) {
            return $name;
        }

        return app(GoogleTranslate::class)->translate($name, 'en', 'th') ?? $name;
    }

    /** แปลข้อความภาษาไทยจาก CRM เป็นภาษาปัจจุบัน */
    public static function t(string $thai): string
    {
        $locale = app()->getLocale();
        if ($thai === '') {
            return $thai;
        }
        if (Lang::hasForLocale($thai, $locale)) {
            return __($thai);
        }
        if ($locale === 'th') {
            return $thai;
        }
        if (self::needsMachine($locale)) {
            self::prefetch([$thai]);
            if (isset(self::$runtime[$locale][$thai])) {
                return self::$runtime[$locale][$thai];
            }
        }

        // ไม่มีคำแปล -> ภาษาอังกฤษ (ถ้ามี) อ่านง่ายกว่าภาษาไทยสำหรับแรงงาน
        return Lang::hasForLocale($thai, 'en') ? __($thai, [], 'en') : $thai;
    }
}
