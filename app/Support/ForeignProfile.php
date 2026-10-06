<?php

namespace App\Support;

use App\Services\ForeignData;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;

/**
 * แปลง record Foreign_Data -> ข้อมูลที่ "อนุญาต" ให้แรงงานเห็น (whitelist)
 * ฟิลด์ที่ไม่อยู่ใน SECTIONS จะไม่ถูกแสดงเลย (เช่น Owner, Tag, หมายเหตุภายใน, ค่า flag ของระบบ)
 */
class ForeignProfile
{
    /**
     * [section id => [title, [api_name => [label, type]]]] type: text|date|datetime|lookup|pick|phone|bool
     * แสดงเฉพาะฟิลด์ที่มีค่า, หมวด/การ์ดที่ไม่มีข้อมูลเลยจะไม่แสดง
     */
    public const SECTIONS = [
        // ---------- แท็บ "ข้อมูลส่วนตัว" ----------
        'personal' => ['ข้อมูลส่วนตัวแรงงาน (Foreign Personal Data)', [
            'Name' => ['รหัสแรงงาน', 'text'],
            'Title' => ['คำนำหน้า', 'pick'],
            'First_Name' => ['ชื่อ', 'text'],
            'Middle_Name' => ['ชื่อกลาง', 'text'],
            'Last_Name' => ['นามสกุล', 'text'],
            'field4' => ['ชื่อ (ไทย)', 'text'],
            'Gender' => ['เพศ', 'pick'],
            'Birthday' => ['วันเกิด', 'date'],
            '_age' => ['อายุ', 'text'],
            'Nationality' => ['สัญชาติ', 'pick'],
            'field28' => ['ส่วนสูง', 'text'],
            'field27' => ['น้ำหนัก', 'text'],
            'Mobile' => ['เบอร์ติดต่อ', 'phone'],
            'Email' => ['อีเมล', 'text'],
            'Secondary_Email' => ['อีเมลสำรอง', 'text'],
        ]],
        'numbers' => ['เลขที่เอกสาร (Document Number)', [
            'National_ID' => ['เลขบัตรประจำตัว', 'text'],
            'field29' => ['รหัสอ้างอิงคนต่างด้าว', 'text'],
            'Passport_ID' => ['เลขที่หนังสือเดินทาง', 'text'],
            'VISA_ID' => ['เลขที่วีซ่า', 'text'],
            'Work_Permit_ID' => ['เลขที่ใบอนุญาตทำงาน', 'text'],
            'field2' => ['เลขที่บัตรชมพู', 'text'],
            'Immigration_Bureau' => ['เลขที่ ตม.', 'text'],
            'Thai_TM47_Number' => ['เลขที่ ตม.47', 'text'],
            'field16' => ['เลขที่ประกันสังคม', 'text'],
            'field14' => ['เลขที่คำขอ', 'text'],
        ]],
        'work' => ['ข้อมูลการทำงาน', [
            'Account_Name' => ['บริษัทนายจ้าง', 'lookup'],
            'Agency' => ['Agency', 'lookup'],
            'field7' => ['ชื่อสาขา', 'text'],
            'field' => ['แผนก', 'text'],
            'Employer_Staff_ID' => ['รหัสพนักงาน', 'text'],
            'Foreigners_Status' => ['สถานะพนักงาน', 'pick'],
            'Immigrant_Type' => ['ประเภทกลุ่มนำเข้า', 'pick'],
            'field23' => ['ชุดเอกสาร', 'pick'],
            'field13' => ['ดำเนินการต่อเอกสาร', 'pick'],
            'Work_Start_Date' => ['วันที่เริ่มทำงาน', 'date'],
            'Work_End_Date' => ['วันที่ลาออก', 'date'],
            'field15' => ['ล่ามผู้ดูแล', 'text'],
        ]],
        'address' => ['ที่อยู่ (Address)', [
            'Address_Street' => ['เลขที่/หมู่/ซอย', 'text'],
            'Address_City' => ['ตำบล/แขวง', 'text'],
            'Address_State' => ['อำเภอ/เขต', 'text'],
            'Address_Province' => ['จังหวัด', 'text'],
            'Address_Code' => ['รหัสไปรษณีย์', 'text'],
        ]],
        'social' => ['ข้อมูลประกันสังคม และ ธนาคาร', [
            'field16' => ['เลขที่ประกันสังคม', 'text'],
            'field17' => ['วันที่แจ้งเข้าประกันสังคม', 'datetime'],
            'field19' => ['สิทธิรักษาโรงพยาบาล', 'text'],
            'field18' => ['เลขประจำตัวผู้เสียภาษี', 'text'],
            'field21' => ['ธนาคาร', 'pick'],
            'field20' => ['เลขที่บัญชีธนาคาร', 'text'],
        ]],
        'emergency' => ['ข้อมูลผู้ติดต่อกรณีฉุกเฉินแรงงาน', [
            'field24' => ['ชื่อผู้ติดต่อ', 'text'],
            'field26' => ['ความสัมพันธ์กับแรงงาน', 'text'],
            'field25' => ['เบอร์โทรศัพท์ติดต่อ', 'phone'],
        ]],

        // ---------- แท็บ "สถานะเอกสาร" (การ์ด) ----------
        'passport' => ['หนังสือเดินทาง (Passport)', [
            'Passport_ID' => ['เลขที่หนังสือเดินทาง', 'text'],
            'Passport_Created_Date' => ['วันที่ออก', 'date'],
            'Passport_Expire' => ['วันหมดอายุ', 'date'],
            'Passport_Status' => ['สถานะ', 'pick'],
            'Self_Passport' => ['นายจ้างต่ออายุเอง', 'bool'],
        ]],
        'visa' => ['วีซ่า (VISA)', [
            'VISA_ID' => ['เลขที่วีซ่า', 'text'],
            'VISA_Arrival_Date' => ['วันที่เดินทางเข้า', 'date'],
            'VISA_Start_Date_0' => ['วันเริ่มวีซ่า', 'date'],
            'VISA_End_Date_0' => ['วันหมดอายุวีซ่า', 'date'],
            'VISA_Status' => ['สถานะ', 'pick'],
            'VISA_Start_Date_1' => ['วันเริ่มวีซ่า (ต่อปี 1)', 'date'],
            'VISA_End_Date_1' => ['วันหมดวีซ่า (ต่อปี 1)', 'date'],
            'VISA_Start_Date_2' => ['วันเริ่มวีซ่า (ต่อปี 2)', 'date'],
            'VISA_End_Date_2' => ['วันหมดวีซ่า (ต่อปี 2)', 'date'],
            'Self_VISA' => ['นายจ้างต่ออายุเอง', 'bool'],
        ]],
        'permit' => ['ใบอนุญาตทำงาน (Work Permit)', [
            'Work_Permit_ID' => ['เลขที่ใบอนุญาต', 'text'],
            'WP_Start_Date_0' => ['วันเริ่มใบอนุญาตล่าสุด', 'date'],
            'WP_End_Date_0' => ['วันสิ้นสุดใบอนุญาตล่าสุด', 'date'],
            'Workpermit_Status' => ['สถานะ', 'pick'],
            'WP_Start_Date_1' => ['วันเริ่มใบอนุญาต รอบ 1', 'date'],
            'WP_End_Date_1' => ['วันสิ้นสุดใบอนุญาต รอบ 1', 'date'],
            'WP_Start_Date_2' => ['วันเริ่มใบอนุญาต รอบ 2', 'date'],
            'WP_End_Date_2' => ['วันสิ้นสุดใบอนุญาต รอบ 2', 'date'],
            'Self_Work_Permit' => ['นายจ้างต่ออายุเอง', 'bool'],
        ]],
        'days90' => ['รายงานตัว 90 วัน', [
            'Immigration_Bureau' => ['เลขที่ ตม.', 'text'],
            'Immigration_Bureau_Province' => ['ตม. จังหวัด', 'pick'],
            'Thai_TM47_Number' => ['เลขที่ ตม.47', 'text'],
            'Days_Start_Date' => ['วันที่รายงานตัว', 'date'],
            'Days_End_Date' => ['ครบกำหนดรายงานตัวครั้งถัดไป', 'date'],
            'Days_Status' => ['สถานะ', 'pick'],
            'Self_90_Days' => ['นายจ้างต่ออายุเอง', 'bool'],
        ]],
        'pink' => ['ข้อมูลบัตรชมพู', [
            'field2' => ['เลขที่บัตรชมพู', 'text'],
            'field1' => ['วันที่ออกบัตร', 'date'],
            'field5' => ['วันหมดอายุ', 'date'],
            'field3' => ['ครบกำหนดถ่ายบัตร', 'date'],
            'Pink_Card_Status' => ['สถานะ', 'pick'],
        ]],
    ];

    /**
     * แท็บ "สถานะเอกสาร": การ์ดละ 1 เอกสาร เรียงตามลำดับนี้เสมอ (รายงานตัว 90 วันอยู่ท้ายสุด)
     * ไม่มีข้อมูลเลย = ไม่แสดงการ์ด
     * [section id => [ชื่อสั้น, ฟิลด์เลขที่, ฟิลด์วันหมดอายุ, ฟิลด์สถานะใน CRM]]
     */
    public const CARDS = [
        'passport' => ['Passport', 'Passport_ID', 'Passport_Expire', 'Passport_Status'],
        'visa' => ['VISA', 'VISA_ID', 'VISA_End_Date_0', 'VISA_Status'],
        'permit' => ['Work Permit', 'Work_Permit_ID', 'WP_End_Date_0', 'Workpermit_Status'],
        'pink' => ['บัตรชมพู', 'field2', 'field5', 'Pink_Card_Status'],
        'days90' => ['รายงานตัว 90 วัน', 'Immigration_Bureau', 'Days_End_Date', 'Days_Status'],
    ];

    /** แท็บ "ข้อมูลส่วนตัว" (พับเก็บทีละหมวด) */
    public const INFO = ['personal', 'numbers', 'work', 'address', 'social', 'emergency'];

    private const DOC_STATUS = [
        'มีไฟล์เอกสาร' => 'ok',
        'ไฟล์หมดอายุ' => 'expired',
        'ไม่มีไฟล์เอกสาร' => 'none',
    ];

    public static function build(array $r, array $hiddenFields = [], int $warnDays = 30, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok'));
        $hidden = array_flip($hiddenFields);
        $r['_age'] = self::age($r['Birthday'] ?? null, $today);

        // รวมข้อความภาษาไทยจาก CRM ที่ต้องแปล แล้วแปลในการเรียก API ครั้งเดียว
        $thai = [];
        foreach (self::SECTIONS as [, $fields]) {
            foreach ($fields as $api => [, $type]) {
                if ($type === 'pick' && is_string($r[$api] ?? null)) {
                    $thai[] = $r[$api];
                }
            }
        }
        foreach ($r['LinkingModule10'] ?? [] as $row) {
            $thai[] = $row['field1'] ?? '';
            $thai[] = $row['field4'] ?? '';
        }
        Locales::prefetch($thai);

        $rows = function (string $section, array $skip = []) use ($r, $hidden): array {
            $out = [];
            foreach (self::SECTIONS[$section][1] as $api => [$label, $type]) {
                if (isset($hidden[$api]) || in_array($api, $skip, true)) {
                    continue;
                }
                $value = self::format($r[$api] ?? null, $type);
                if ($value !== '') {
                    $out[] = ['label' => __($label), 'value' => $value, 'type' => $type];
                }
            }

            return $out;
        };

        $info = [];
        foreach (self::INFO as $id) {
            if ($list = $rows($id)) {
                $info[] = ['id' => $id, 'title' => __(self::SECTIONS[$id][0]), 'rows' => $list];
            }
        }

        $cards = [];
        foreach (self::CARDS as $id => [$label, $numberApi, $expiryApi, $statusApi]) {
            $number = isset($hidden[$numberApi]) ? '' : self::format($r[$numberApi] ?? null, 'text');
            $expiry = ! isset($hidden[$expiryApi]) && is_string($r[$expiryApi] ?? null) && self::isDate($r[$expiryApi]) ? $r[$expiryApi] : null;
            $details = $rows($id, [$numberApi, $expiryApi, $statusApi]);
            $crmStatus = isset($hidden[$statusApi]) ? '' : self::format($r[$statusApi] ?? null, 'pick');
            if ($number === '' && ! $expiry && ! $details && $crmStatus === '') {
                continue; // ไม่มีข้อมูลเอกสารนี้เลย (เช่น แรงงาน MOU ไม่มีบัตรชมพู)
            }

            $days = $expiry ? (int) $today->diff(new DateTimeImmutable($expiry, $today->getTimezone()))->format('%r%a') : null;
            $cards[] = [
                'id' => $id,
                'title' => __($label),
                'fullTitle' => __(self::SECTIONS[$id][0]),
                'number' => $number,
                'numberLabel' => __(self::SECTIONS[$id][1][$numberApi][0]),
                'expiry' => $expiry ? self::date($expiry) : '',
                'days' => $days,
                'level' => $days === null ? 'none' : ($days < 0 ? 'expired' : ($days <= $warnDays ? 'warn' : 'ok')),
                'crmStatus' => $crmStatus,
                'details' => $details,
            ];
        }

        $docs = [];
        foreach ($r['LinkingModule10'] ?? [] as $row) {
            $status = $row['field4'] ?? '';
            $hasLink = ForeignData::workdriveResourceId($row['field5'] ?? null) !== null;
            $docs[] = [
                'id' => (string) ($row['id'] ?? ''),
                'no' => (int) ($row['No'] ?? 0),
                'name' => ($row['field1'] ?? '') ? Locales::t($row['field1']) : ($row['base_name'] ?? __('เอกสาร')),
                'filename' => $row['field6'] ?? '',
                'status' => $status && $status !== '-None-' ? Locales::t($status) : '',
                'level' => self::DOC_STATUS[$status] ?? ($hasLink ? 'ok' : 'none'),
                'available' => $hasLink,
            ];
        }
        usort($docs, fn ($a, $b) => $a['no'] <=> $b['no']);

        $name = trim(implode(' ', array_filter([
            $r['First_Name'] ?? '', $r['Middle_Name'] ?? '', $r['Last_Name'] ?? '',
        ])));

        return [
            'foreignId' => $r['Name'] ?? '',
            'name' => $name ?: ($r['Full_Name_Labour'] ?? ''),
            'nameTh' => $r['field4'] ?? '',
            'employer' => Locales::company((string) ($r['Account_Name']['name'] ?? '')),
            'status' => self::format($r['Foreigners_Status'] ?? null, 'pick'),
            'nationality' => self::format($r['Nationality'] ?? null, 'pick'),
            'passport' => $r['Passport_ID'] ?? '',
            'staffId' => isset($hidden['Employer_Staff_ID']) ? '' : self::format($r['Employer_Staff_ID'] ?? null, 'text'),
            'hasPhoto' => ! empty($r['Record_Image']) || ForeignData::photoDocument($r) !== null,
            'initials' => strtoupper(mb_substr($r['First_Name'] ?? '', 0, 1).mb_substr($r['Last_Name'] ?? '', 0, 1)),
            'cards' => $cards,
            'attention' => count(array_filter($cards, fn ($c) => in_array($c['level'], ['warn', 'expired'], true))),
            'info' => $info,
            'documents' => $docs,
            'updatedAt' => self::dateTime($r['Modified_Time'] ?? null),
        ];
    }

    public static function format(mixed $v, string $type): string
    {
        if ($type === 'bool') {
            return $v === true ? __('ใช่') : ''; // ไม่ได้ติ๊ก = ไม่แสดง
        }
        if (is_array($v)) {
            $v = $v['name'] ?? '';
        }
        if ($v === null || $v === '' || $v === false || $v === '-None-' || $v === 'None') {
            return '';
        }

        return match ($type) {
            'date' => self::date((string) $v),
            'datetime' => self::dateTime((string) $v),
            'pick' => Locales::t((string) $v),
            'lookup' => Locales::company((string) $v), // นายจ้าง / Agency
            default => trim((string) $v),
        };
    }

    public static function age(?string $birthday, DateTimeImmutable $today): ?string
    {
        if (! $birthday || ! self::isDate($birthday)) {
            return null;
        }

        return __(':n ปี', ['n' => (new DateTimeImmutable($birthday, $today->getTimezone()))->diff($today)->y]);
    }

    private static function isDate(string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
    }

    public static function date(?string $iso): string
    {
        if (! $iso || ! self::isDate($iso)) {
            return '';
        }
        $tz = new DateTimeZone('UTC');

        return self::formatter($tz)->format(new DateTimeImmutable($iso, $tz)) ?: '';
    }

    public static function dateTime(?string $iso): string
    {
        if (! $iso) {
            return '';
        }
        try {
            $d = new DateTimeImmutable($iso);
        } catch (\Exception) {
            return '';
        }

        return self::formatter(new DateTimeZone('Asia/Bangkok'), ' HH:mm')->format($d) ?: '';
    }

    private static function formatter(DateTimeZone $tz, string $suffix = ''): IntlDateFormatter
    {
        $locale = Locales::has(app()->getLocale()) ? app()->getLocale() : 'th';

        return new IntlDateFormatter(Locales::icu($locale), IntlDateFormatter::NONE, IntlDateFormatter::NONE, $tz, IntlDateFormatter::TRADITIONAL, Locales::datePattern($locale).$suffix);
    }
}
