<?php

namespace Tests\Unit;

use App\Services\ForeignData;
use App\Support\ForeignProfile;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Fixtures\ForeignRecord;
use Tests\TestCase;

class ForeignProfileTest extends TestCase
{
    public function test_national_id_and_password(): void
    {
        $this->assertSame('MH455218', ForeignData::normalizePassport(' mh-455 218 '));
        $this->assertNull(ForeignData::normalizePassport('MA12(3)'));
        $this->assertSame('6920000031376', ForeignData::normalizeNationalId(' 6-9200-00031-37-6 '));
        $this->assertNull(ForeignData::normalizeNationalId('692000003137'));
        $this->assertNull(ForeignData::normalizeNationalId(null));
        $this->assertSame('69201376', ForeignData::passwordFor('6920000031376'));
    }

    public function test_workdrive_resource_id(): void
    {
        $this->assertSame('5vnro6281671152cf4d51970029f107b8a222',
            ForeignData::workdriveResourceId('https://workdrive.zoho.com/file/5vnro6281671152cf4d51970029f107b8a222'));
        $this->assertNull(ForeignData::workdriveResourceId('https://workdrive.zoho.com/folder/d5w9r0b14ccf3237347b5a74b9b8b580140fe'));
        $this->assertNull(ForeignData::workdriveResourceId('https://evil.example.com/file/5vnro6281671152cf4d51970029f107b8a222'));
        $this->assertNull(ForeignData::workdriveResourceId(null));
    }

    public function test_document_cards_in_fixed_order(): void
    {
        $today = new DateTimeImmutable('2026-10-01', new DateTimeZone('Asia/Bangkok'));
        $p = ForeignProfile::build(ForeignRecord::make(), ['field20'], 30, $today);

        // ลำดับตายตัว Passport -> VISA -> Work Permit (ไม่มีข้อมูล 90 วัน/บัตรชมพูเลย = ไม่แสดงการ์ด)
        $this->assertSame(['passport', 'visa', 'permit'], array_column($p['cards'], 'id'));
        $this->assertSame([14, -30, 272], array_column($p['cards'], 'days'));
        $this->assertSame(['warn', 'expired', 'ok'], array_column($p['cards'], 'level'));

        // รายงานตัว 90 วันอยู่ท้ายสุดเสมอ แม้ใกล้หมดอายุที่สุด
        $all = ForeignProfile::build(ForeignRecord::make(['Days_End_Date' => '2026-10-03', 'field5' => '2027-01-01']), [], 30, $today);
        $this->assertSame(['passport', 'visa', 'permit', 'pink', 'days90'], array_column($all['cards'], 'id'));
        // ในการ์ดแสดงเฉพาะช่องที่มีค่า
        $this->assertNotContains('', array_column($p['cards'][0]['details'], 'value'));
        $this->assertSame(2, $p['attention']);

        $passport = $p['cards'][0];
        $this->assertSame('MA1234567', $passport['number']);
        $this->assertSame('15 ต.ค. 2026', $passport['expiry']);
        $this->assertSame('ใช้งานได้', $passport['crmStatus']); // Active -> ไทย
    }

    public function test_info_tab_sections_and_hidden_fields(): void
    {
        $p = ForeignProfile::build(ForeignRecord::make(), ['field20'], 30, new DateTimeImmutable('2026-10-01'));

        // หมวดที่ไม่มีข้อมูลเลย (ที่อยู่, ประกันสังคม, ผู้ติดต่อฉุกเฉิน) ไม่แสดง
        $this->assertSame(['personal', 'numbers', 'work'], array_column($p['info'], 'id'));
        $this->assertNotContains('', collect($p['info'])->flatMap(fn ($s) => array_column($s['rows'], 'value'))->all());
        $labels = collect($p['info'])->flatMap(fn ($s) => array_column($s['rows'], 'label'))->all();
        $this->assertNotContains('เลขที่บัญชีธนาคาร', $labels);
        $this->assertNotContains('passport', array_column($p['info'], 'id')); // อยู่ในแท็บสถานะเอกสารแทน

        $values = collect($p['info'])->flatMap(fn ($s) => array_column($s['rows'], 'value'))->all();
        $this->assertContains('31 ปี', $values);
        $this->assertNotContains('123-4-56789-0', $values);

        $this->assertSame(['หน้าพาสปอร์ต', 'บัตรสมาร์ทการ์ด'], array_column($p['documents'], 'name'));
        $this->assertSame([true, false], array_column($p['documents'], 'available'));
    }

    public function test_profile_card_shows_birthday_with_age(): void
    {
        $p = ForeignProfile::build(ForeignRecord::make(), [], 30, new DateTimeImmutable('2026-10-01'));
        $this->assertSame(['14 มี.ค. 1995', '31 ปี'], [$p['birthday'], $p['age']]);

        $hidden = ForeignProfile::build(ForeignRecord::make(), ['Birthday']);
        $this->assertSame(['', null], [$hidden['birthday'], $hidden['age']]);
    }

    public function test_staff_id_shown_only_when_present(): void
    {
        $this->assertSame('EMP-001', ForeignProfile::build(ForeignRecord::make(['Employer_Staff_ID' => ' EMP-001 ']))['staffId']);
        $this->assertSame('', ForeignProfile::build(ForeignRecord::make())['staffId']);
        $this->assertSame('', ForeignProfile::build(ForeignRecord::make(['Employer_Staff_ID' => 'EMP-001']), ['Employer_Staff_ID'])['staffId']);
    }

    public function test_thai_date_uses_gregorian_year(): void
    {
        $this->assertSame('14 มี.ค. 1995', ForeignProfile::date('1995-03-14')); // ค.ศ. ไม่ใช่ พ.ศ.
    }
}
