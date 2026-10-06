<?php

namespace Tests\Feature;

use App\Services\GoogleTranslate;
use App\Support\Locales;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\ForeignRecord;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.zoho.client_id' => 'cid',
            'services.zoho.client_secret' => 'secret',
            'services.zoho.refresh_token' => 'refresh',
        ]);
        $this->app->forgetInstance(GoogleTranslate::class);
        $this->app->singleton(GoogleTranslate::class, fn () => new GoogleTranslate('test-key'));
    }

    /** จำลอง Google: คำแปล = "[target] ข้อความเดิม" */
    private function fakeGoogle(array &$calls = []): void
    {
        // ค่าที่ไม่มีในไฟล์คำแปล -> ต้องแปลผ่าน Google ตอนแสดงผล
        $record = ForeignRecord::make(['Immigrant_Type' => 'MOU (นำเข้าใหม่)']);
        $record['LinkingModule10'][0]['field1'] = 'เอกสารพิเศษ';
        Http::fake([
            'accounts.zoho.com/*' => Http::response(['access_token' => 'AT', 'expires_in' => 3600]),
            'www.zohoapis.com/crm/v7/Foreign_Data/search*' => Http::response(['data' => [$record]]),
            'www.zohoapis.com/crm/v7/Foreign_Data/*' => Http::response(['data' => [$record]]),
            'translation.googleapis.com/*' => function (Request $r) use (&$calls) {
                $calls[] = $r->data();

                return Http::response(['data' => ['translations' => array_map(
                    fn ($q) => ['translatedText' => '['.$r->data()['target'].'] '.$q],
                    $r->data()['q'],
                )]]);
            },
        ]);
    }

    public function test_translate_many_batches_caches_and_keeps_placeholders(): void
    {
        $calls = [];
        $this->fakeGoogle($calls);
        $g = app(GoogleTranslate::class);

        $out = $g->translateMany(['เหลือ :n วัน', 'ทำงาน', 'ทำงาน'], 'my');

        $this->assertSame('[my] เหลือ :n วัน', $out['เหลือ :n วัน']);
        $this->assertCount(1, $calls);
        $this->assertSame(['เหลือ <span translate="no">:n</span> วัน', 'ทำงาน'], $calls[0]['q']);
        $this->assertSame('html', $calls[0]['format']);

        $g->translateMany(['ทำงาน'], 'my'); // มาจากแคช
        $this->assertCount(1, $calls);
    }

    public function test_without_api_key_falls_back_to_english_then_original(): void
    {
        $this->app->singleton(GoogleTranslate::class, fn () => new GoogleTranslate(null));
        Http::fake();
        app()->setLocale('km');

        app('translator')->addLines(['*.ข้อความเฉพาะอังกฤษ' => 'English only'], 'en');
        $this->assertSame('English only', Locales::t('ข้อความเฉพาะอังกฤษ')); // ไม่มีใน km.json -> ใช้ en
        $this->assertSame(__('ทำงาน', [], 'km'), Locales::t('ทำงาน')); // มีใน km.json
        $this->assertSame('สาขาพิเศษ', Locales::t('สาขาพิเศษ'));     // ไม่มีเลย -> ข้อความเดิม
        Http::assertNothingSent();
    }

    public function test_thai_and_english_use_lang_files_only(): void
    {
        Http::fake();

        app()->setLocale('th');
        $this->assertSame('นาย', Locales::t('MR.'));
        app()->setLocale('en');
        $this->assertSame('Myanmar', Locales::t('เมียนมา'));
        Http::assertNothingSent();
    }

    public function test_profile_in_burmese_translates_crm_values_in_one_call(): void
    {
        $calls = [];
        $this->fakeGoogle($calls);

        $this->post('/foreign/login', ['passport' => 'MA1234567', 'password' => '69201376']);
        $calls = [];

        $html = $this->get('/foreign?lang=my')->assertOk()
            ->assertSee('lang="my"', false)
            ->assertSee('Noto+Sans+Myanmar', false)
            ->assertSee('မြန်မာ')
            ->assertSee('[my] เอกสารพิเศษ')
            ->assertCookie('lang', 'my')
            ->getContent();

        $this->assertStringContainsString('[my] MOU (นำเข้าใหม่)', $html);
        $this->assertStringNotContainsString('@if', $html);
        // ค่าจาก CRM แปลรวมเป็นภาษาพม่า 1 ครั้ง + ชื่อนายจ้างแปลเป็นอังกฤษ 1 ครั้ง
        $this->assertSame(['my', 'en'], array_column($calls, 'target'));
        $this->assertStringContainsString('[en] บริษัท ตัวอย่าง จำกัด', $html);
    }

    public function test_employer_name_is_english_in_every_non_thai_locale(): void
    {
        $calls = [];
        $this->fakeGoogle($calls);

        foreach (['my', 'zh', 'en'] as $locale) {
            app()->setLocale($locale);
            $this->assertSame('[en] บริษัท ตัวอย่าง จำกัด', Locales::company('บริษัท ตัวอย่าง จำกัด'), $locale);
        }
        app()->setLocale('th');
        $this->assertSame('บริษัท ตัวอย่าง จำกัด', Locales::company('บริษัท ตัวอย่าง จำกัด'));
        app()->setLocale('my');
        $this->assertSame('CJ Logistics', Locales::company('CJ Logistics')); // ไม่มีอักษรไทย = ไม่ต้องแปล

        $this->assertCount(1, $calls, 'แปลครั้งเดียวแล้วใช้แคช');
        $this->assertSame('en', $calls[0]['target']);
    }

    public function test_all_supported_locales_render_login(): void
    {
        foreach (array_keys(Locales::SUPPORTED) as $code) {
            $this->get("/foreign/login?lang={$code}")->assertOk()->assertSee('lang="'.$code.'"', false);
        }
        $this->get('/foreign/login?lang=xx')->assertOk()->assertSee('lang="th"', false);
    }
}
