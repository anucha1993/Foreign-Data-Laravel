<?php

namespace Tests\Fixtures;

/** โครงสร้างตาม record จริงใน Foreign_Data (ข้อมูลสมมติ) */
class ForeignRecord
{
    public const ID = '6274719000149621288';

    public const DOC_ID = '6274719000155989211';

    public static function make(array $override = []): array
    {
        return array_merge([
            'id' => self::ID,
            'Name' => '2607-00237402',
            'Owner' => ['name' => 'Staff', 'id' => '1', 'email' => 'owner-secret@example.com'],
            'Tag' => [['name' => 'INTERNAL-TAG']],
            'Title' => 'MR.',
            'First_Name' => 'AUNG',
            'Last_Name' => 'KYAW',
            'field4' => 'อ่อง จ่อ',
            'Gender' => 'ชาย',
            'Birthday' => '1995-03-14',
            'Nationality' => 'เมียนมา',
            'Passport_ID' => 'MA1234567',
            'National_ID' => '6920000031376',
            'Passport_Expire' => '2026-10-15',
            'VISA_End_Date_0' => '2026-09-01',
            'WP_End_Date_0' => '2027-06-30',
            'Foreigners_Status' => 'ทำงาน',
            'Account_Name' => ['name' => 'บริษัท ตัวอย่าง จำกัด', 'id' => '2'],
            'Passport_Status' => 'Active',
            'Mobile' => '081-234-5678',
            'field6' => 'INTERNAL-REMARK ห้ามแสดง',
            'field20' => '123-4-56789-0',
            'Self_VISA' => true,
            'Record_Image' => null,
            'Modified_Time' => '2026-09-28T10:00:00+07:00',
            'LinkingModule10' => [
                ['id' => self::DOC_ID, 'No' => 1, 'field1' => 'หน้าพาสปอร์ต', 'field4' => 'มีไฟล์เอกสาร',
                    'field5' => 'https://workdrive.zoho.com/file/5vnro6281671152cf4d51970029f107b8a222', 'field6' => 'CC8603663_PASSPORT_IO.pdf'],
                ['id' => '6274719000155989299', 'No' => 2, 'field1' => 'บัตรสมาร์ทการ์ด', 'field4' => 'ไม่มีไฟล์เอกสาร',
                    'field5' => null, 'field6' => null],
            ],
        ], $override);
    }
}
