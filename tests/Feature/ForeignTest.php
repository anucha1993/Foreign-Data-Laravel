<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\ForeignRecord;
use Tests\TestCase;

class ForeignTest extends TestCase
{
    private const API = 'https://www.zohoapis.com/crm/v7';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.zoho.client_id' => 'cid',
            'services.zoho.client_secret' => 'secret',
            'services.zoho.refresh_token' => 'refresh',
            'foreign.hidden_fields' => ['field20'],
        ]);
    }

    private function fakeZoho(?array $record = null, array $extra = []): void
    {
        $record ??= ForeignRecord::make();

        Http::fake($extra + [
            'accounts.zoho.com/oauth/v2/token' => Http::response(['access_token' => 'AT', 'expires_in' => 3600]),
            self::API.'/Foreign_Data/search*' => Http::response(['data' => [$record]]),
            self::API.'/Foreign_Data/'.ForeignRecord::ID => Http::response(['data' => [$record]]),
            'www.zohoapis.com/workdrive/*' => Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']),
        ]);
    }

    private function login(string $passport = 'MA1234567', string $password = '69201376')
    {
        return $this->post('/foreign/login', ['passport' => $passport, 'password' => $password]);
    }

    public function test_login_page_renders_form(): void
    {
        $this->get('/foreign/login')
            ->assertOk()
            ->assertSee('name="passport"', false)
            ->assertSee('type="password"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_profile_requires_login(): void
    {
        $this->get('/foreign')->assertRedirect('/foreign/login');
        $this->get('/foreign/documents/'.ForeignRecord::DOC_ID)->assertRedirect('/foreign/login');
    }

    public function test_login_with_passport_and_password_shows_profile(): void
    {
        $this->fakeZoho();

        // พิมพ์เล็ก + เว้นวรรคก็ล็อกอินได้
        $this->login(' ma 1234567 ')->assertRedirect('/foreign');

        $html = $this->get('/foreign')->assertOk()
            ->assertSee('AUNG KYAW')
            ->assertSee('บริษัท ตัวอย่าง จำกัด')
            ->assertSee('หน้าพาสปอร์ต')
            ->assertSee('ใช้งานได้') // Active -> ไทย
            ->getContent();

        // ไม่แสดงฟิลด์ภายใน / ฟิลด์ที่ตั้งให้ซ่อน / ลิงก์ WorkDrive จริง
        foreach (['owner-secret@example.com', 'INTERNAL-TAG', 'INTERNAL-REMARK', '123-4-56789-0', 'workdrive.zoho.com', '5vnro6281671152'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/Foreign_Data/search')
            && str_contains(urldecode($r->url()), '(Passport_ID:equals:MA1234567)')
            && $r->header('Authorization')[0] === 'Zoho-oauthtoken AT');
    }

    public function test_wrong_password_is_rejected_without_session(): void
    {
        $this->fakeZoho();

        $this->from('/foreign/login')->login('MA1234567', '69200000')
            ->assertRedirect('/foreign/login')
            ->assertSessionHasErrors('login')
            ->assertSessionMissing('foreign.id');
    }

    public function test_invalid_input_format_does_not_call_crm(): void
    {
        $this->fakeZoho();

        $this->from('/foreign/login')->login('MA12(*)', '69201376')->assertSessionHasErrors('login');
        $this->from('/foreign/login')->login('MA1234567', 'abc')->assertSessionHasErrors('login');
        Http::assertNothingSent();
    }

    public function test_record_without_national_id_cannot_login(): void
    {
        $this->fakeZoho(ForeignRecord::make(['National_ID' => null]));

        $this->from('/foreign/login')->login()->assertSessionHasErrors('login');
    }

    public function test_blocked_status_cannot_login(): void
    {
        $this->fakeZoho(ForeignRecord::make(['Foreigners_Status' => 'ปิดระบบ']));

        $this->from('/foreign/login')->login()->assertSessionHasErrors('login');
    }

    public function test_too_many_attempts_per_passport_are_throttled(): void
    {
        $this->fakeZoho();
        config(['foreign.login_max_per_passport' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->from('/foreign/login')->login('MA1234567', '00000000');
        }

        // แม้รหัสผ่านถูกก็ยังถูกบล็อกไว้ชั่วคราว
        $this->from('/foreign/login')->login()->assertSessionHasErrors('login');
        $this->assertStringContainsString('หลายครั้งเกินไป', session('errors')->first('login'));
    }

    public function test_session_expires_after_configured_minutes(): void
    {
        $this->fakeZoho();
        $this->login()->assertRedirect('/foreign');

        $this->travel(31)->minutes();

        $this->get('/foreign')->assertRedirect('/foreign/login')->assertSessionHas('notice');
    }

    public function test_logout_clears_session(): void
    {
        $this->fakeZoho();
        $this->login();

        $this->post('/foreign/logout')->assertRedirect('/foreign/login');
        $this->get('/foreign')->assertRedirect('/foreign/login');
    }

    public function test_document_is_proxied_inline_or_as_download(): void
    {
        $this->fakeZoho();
        $this->login();

        $this->get('/foreign/documents/'.ForeignRecord::DOC_ID)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="CC8603663_PASSPORT_IO.pdf"');

        $this->get('/foreign/documents/'.ForeignRecord::DOC_ID.'?download=1')
            ->assertHeader('Content-Disposition', 'attachment; filename="CC8603663_PASSPORT_IO.pdf"');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://www.zohoapis.com/workdrive/api/v1/download/5vnro6281671152cf4d51970029f107b8a222');
    }

    public function test_cannot_open_document_of_another_worker_or_without_file(): void
    {
        $this->fakeZoho();
        $this->login();

        $this->get('/foreign/documents/6274719000000000999')->assertNotFound();
        $this->get('/foreign/documents/6274719000155989299')->assertNotFound(); // แถวที่ไม่มีไฟล์
        $this->get('/foreign/documents/abc')->assertNotFound();
    }

    public function test_crm_401_refreshes_token_and_retries(): void
    {
        $tokens = 0;
        $calls = 0;
        Http::fake([
            'accounts.zoho.com/oauth/v2/token' => function () use (&$tokens) {
                return Http::response(['access_token' => 'T'.++$tokens, 'expires_in' => 3600]);
            },
            self::API.'/Foreign_Data/search*' => function () use (&$calls) {
                return ++$calls === 1
                    ? Http::response(['code' => 'INVALID_TOKEN'], 401)
                    : Http::response(['data' => [ForeignRecord::make()]]);
            },
        ]);

        $this->login()->assertRedirect('/foreign');
        $this->assertSame(2, $tokens);
    }

    public function test_crm_outage_shows_friendly_error(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->from('/foreign/login')->login()->assertSessionHasErrors('login');
        $this->assertStringContainsString('ขัดข้อง', session('errors')->first('login'));
    }

    public function test_english_locale(): void
    {
        $this->fakeZoho();
        $this->login();

        $this->get('/foreign?lang=en')->assertOk()
            ->assertSee('Document files')
            ->assertSee('Myanmar')
            ->assertCookie('lang', 'en');
    }

    public function test_profile_photo_comes_from_worker_photo_document(): void
    {
        $img = imagecreatetruecolor(600, 800);
        ob_start();
        imagejpeg($img);
        $jpeg = ob_get_clean();

        $record = ForeignRecord::make();
        $record['LinkingModule10'][] = ['id' => '6274719000155989217', 'No' => 7, 'base_name' => 'IMG', 'field1' => 'รูปถ่ายแรงงาน',
            'field4' => 'มีไฟล์เอกสาร', 'field5' => 'https://workdrive.zoho.com/file/10xw21a61a56fe6454a4188ca2a0cbc29d390', 'field6' => 'X_IMG.jpg'];
        $this->fakeZoho($record, [
            'www.zohoapis.com/workdrive/api/v1/download/10xw21a61a56fe6454a4188ca2a0cbc29d390' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $this->login();

        $this->get('/foreign')->assertSee('/foreign/photo', false);
        $res = $this->get('/foreign/photo')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame([256, 256], array_slice(getimagesizefromstring($res->getContent()), 0, 2)); // ย่อแล้ว
    }

    public function test_no_photo_returns_404(): void
    {
        $this->fakeZoho();
        $this->login();

        $this->get('/foreign')->assertDontSee('/foreign/photo', false);
        $this->get('/foreign/photo')->assertNotFound();
    }
}
