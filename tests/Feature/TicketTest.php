<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Services\GoogleTranslate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\ForeignRecord;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

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
        Storage::fake('r2');

        $record = ForeignRecord::make();
        $other = ForeignRecord::make(['id' => '6274719000000000777', 'Passport_ID' => 'ZZ9999999', 'National_ID' => '1111222233334']);
        Http::fake([
            'accounts.zoho.com/*' => Http::response(['access_token' => 'AT', 'expires_in' => 3600]),
            self::API.'/Foreign_Data/search*' => fn ($req) => Http::response(['data' => [str_contains(urldecode($req->url()), 'ZZ9999999') ? $other : $record]]),
            self::API.'/Foreign_Data/'.ForeignRecord::ID => Http::response(['data' => [$record]]),
            self::API.'/Foreign_Data/6274719000000000777' => Http::response(['data' => [$other]]),
        ]);
    }

    private function loginWorker(string $passport = 'MA1234567', string $password = '69201376'): void
    {
        $this->post('/foreign/login', ['passport' => $passport, 'password' => $password])->assertRedirect('/foreign');
    }

    private function image(string $name = 'photo.jpg', int $kb = 200): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 600)->size($kb);
    }

    public function test_worker_reports_problem_with_multiple_images(): void
    {
        $this->loginWorker();

        $res = $this->post('/foreign/tickets', [
            'category' => 'problem',
            'subject' => 'เปิดไฟล์ไม่ได้',
            'body' => 'ကျွန်တော် ဖိုင်ဖွင့်လို့မရပါ',
            'images' => [$this->image('a.jpg'), $this->image('b.png')],
        ]);

        $ticket = Ticket::firstOrFail();
        $res->assertRedirect(route('foreign.tickets.show', $ticket));
        $this->assertSame('TK-000001', $ticket->code);
        $this->assertSame(ForeignRecord::ID, $ticket->foreign_id);
        $this->assertSame('MA1234567', $ticket->passport);
        $this->assertSame('open', $ticket->status);
        $this->assertTrue($ticket->admin_unread);

        $attachments = $ticket->messages()->first()->attachments;
        $this->assertCount(2, $attachments);
        foreach ($attachments as $a) {
            Storage::disk('r2')->assertExists($a->path);
            $this->assertStringStartsWith('tickets/TK-000001/', $a->path);
        }

        // แรงงานเปิดรูปของตัวเองได้
        $this->get(route('foreign.tickets.attachment', [$ticket, $attachments[0]]))->assertOk();
    }

    public function test_correction_ticket_snapshots_current_value(): void
    {
        $this->loginWorker();

        $this->get('/foreign/tickets/new?type=correction&section=personal')->assertOk()->assertSee('Passport_ID', false);

        $this->post('/foreign/tickets', [
            'category' => 'correction',
            'field_key' => 'Last_Name',
            'requested_value' => 'KYAW KYAW',
        ])->assertSessionHasNoErrors();

        $t = Ticket::firstOrFail();
        $this->assertSame('correction', $t->category);
        $this->assertSame('นามสกุล', $t->field_label);
        $this->assertSame('KYAW', $t->current_value);
        $this->assertSame('KYAW KYAW', $t->requested_value);
    }

    public function test_cannot_request_correction_of_hidden_or_unknown_field(): void
    {
        $this->loginWorker();

        foreach (['field20', 'Owner', 'Tag'] as $field) {
            $this->post('/foreign/tickets', ['category' => 'correction', 'field_key' => $field, 'requested_value' => 'x'])
                ->assertSessionHasErrors('field_key');
        }
        $this->assertSame(0, Ticket::count());
    }

    public function test_image_over_5mb_or_non_image_is_rejected(): void
    {
        $this->loginWorker();

        $this->post('/foreign/tickets', [
            'category' => 'problem', 'subject' => 'x', 'body' => 'y',
            'images' => [$this->image('big.jpg', 5121)],
        ])->assertSessionHasErrors('images.0');

        $this->post('/foreign/tickets', [
            'category' => 'problem', 'subject' => 'x', 'body' => 'y',
            'images' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('images.0');

        $this->post('/foreign/tickets', [
            'category' => 'problem', 'subject' => 'x', 'body' => 'y',
            'images' => array_map(fn ($i) => $this->image("p{$i}.jpg", 10), range(1, 11)),
        ])->assertSessionHasErrors('images');

        $this->assertSame(0, Ticket::count());
        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_worker_cannot_see_other_workers_ticket_or_files(): void
    {
        $this->loginWorker();
        $this->post('/foreign/tickets', ['category' => 'problem', 'subject' => 's', 'body' => 'b', 'images' => [$this->image()]]);
        $ticket = Ticket::firstOrFail();
        $attachment = $ticket->messages()->first()->attachments()->first();

        $this->post('/foreign/logout');
        $this->loginWorker('ZZ9999999', '11113334');

        $this->get(route('foreign.tickets.show', $ticket))->assertNotFound();
        $this->get(route('foreign.tickets.attachment', [$ticket, $attachment]))->assertNotFound();
        $this->post(route('foreign.tickets.reply', $ticket), ['body' => 'hack'])->assertNotFound();
        $this->get('/foreign')->assertOk()->assertDontSee($ticket->code);
    }

    public function test_tickets_require_login(): void
    {
        $this->get('/foreign/tickets/new')->assertRedirect('/foreign/login');
        $this->post('/foreign/tickets', ['category' => 'problem'])->assertRedirect('/foreign/login');
        $this->get('/admin/tickets')->assertRedirect('/admin/login');
    }

    public function test_admin_flow_reply_status_and_worker_sees_translation(): void
    {
        $this->loginWorker();
        $this->post('/foreign/tickets', ['category' => 'problem', 'subject' => 'ช่วยด้วย', 'body' => 'Hello']);
        $ticket = Ticket::firstOrFail();

        $admin = User::create(['name' => 'Staff A', 'email' => 'staff@example.com', 'password' => 'secret-password-1']);

        // ล็อกอิน Admin ผิด/ถูก
        $this->post('/admin/login', ['email' => 'staff@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => 'staff@example.com', 'password' => 'secret-password-1'])->assertRedirect('/admin/tickets');

        $this->get('/admin/tickets')->assertOk()->assertSee('TK-000001')->assertSee('ช่วยด้วย');
        $this->get('/admin/tickets?q=MA1234567')->assertSee('TK-000001');
        $this->get('/admin/tickets?q=nomatch')->assertDontSee('TK-000001');

        $this->get(route('admin.tickets.show', $ticket))->assertOk()->assertSee('Hello');
        $this->assertFalse($ticket->fresh()->admin_unread);

        $this->post(route('admin.tickets.reply', $ticket), [
            'body' => 'รับเรื่องแล้วค่ะ',
            'status' => 'in_progress',
            'images' => [$this->image('reply.jpg')],
        ])->assertRedirect(route('admin.tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertTrue($ticket->worker_unread);
        $this->assertSame($admin->id, $ticket->messages()->reorder()->latest('id')->first()->user_id);

        // เปลี่ยนสถานะอย่างเดียว
        $this->post(route('admin.tickets.reply', $ticket), ['status' => 'resolved']);
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertSame(2, $ticket->messages()->count());

        // แรงงานเห็นแจ้งเตือน + คำตอบ แล้วตอบกลับ -> เปิดเรื่องใหม่
        $this->get('/foreign')->assertSee('dot-new', false);
        $this->get(route('foreign.tickets.show', $ticket))->assertOk()->assertSee('รับเรื่องแล้วค่ะ');
        $this->assertFalse($ticket->fresh()->worker_unread);

        $this->post(route('foreign.tickets.reply', $ticket), ['body' => 'ยังไม่ได้'])->assertRedirect();
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertTrue($ticket->fresh()->admin_unread);

        // ปิดเรื่องแล้วตอบไม่ได้
        $ticket->update(['status' => 'closed']);
        $this->post(route('foreign.tickets.reply', $ticket), ['body' => 'again'])->assertSessionHasErrors('body');
    }

    public function test_admin_can_translate_worker_messages(): void
    {
        config(['services.google_translate.key' => 'k']);
        $this->app->forgetInstance(GoogleTranslate::class);
        $this->app->singleton(GoogleTranslate::class, fn () => new GoogleTranslate('k'));
        Http::fake(['translation.googleapis.com/*' => fn ($r) => Http::response(['data' => ['translations' => array_map(
            fn ($q) => ['translatedText' => 'TH:'.$q], $r->data()['q'])]])]);

        $this->loginWorker();
        $this->post('/foreign/tickets', ['category' => 'problem', 'subject' => 's', 'body' => 'ဖိုင်ဖွင့်လို့မရပါ']);
        $ticket = Ticket::firstOrFail();

        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password-1']));
        $this->get(route('admin.tickets.show', [$ticket, 'translate' => 1]))->assertOk()->assertSee('TH:ဖိုင်ဖွင့်လို့မရပါ');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'translation.googleapis.com') && $r->data()['target'] === 'th' && ! isset($r->data()['source']));
    }

    public function test_admin_create_command(): void
    {
        $this->artisan('admin:create', ['email' => 'boss@example.com', '--name' => 'Boss', '--password' => 'a-strong-password'])->assertSuccessful();
        $this->assertTrue(User::where('email', 'boss@example.com')->exists());

        $this->artisan('admin:create', ['email' => 'x@example.com', '--password' => 'short'])->assertFailed();
    }

    /** Google Translate จำลอง: คำแปล = "[target] ข้อความ" */
    private function fakeTranslator(): void
    {
        $this->app->singleton(GoogleTranslate::class, fn () => new GoogleTranslate('k'));
        Http::fake(['translation.googleapis.com/*' => fn ($r) => Http::response(['data' => ['translations' => array_map(
            fn ($q) => ['translatedText' => '['.$r->data()['target'].'] '.$q], $r->data()['q'])]])]);
    }

    private function burmeseTicket(): Ticket
    {
        $this->loginWorker();
        $this->post('/foreign/tickets?lang=my', ['category' => 'problem', 'subject' => 's', 'body' => 'ဖိုင်ဖွင့်မရပါ']);

        return Ticket::firstOrFail();
    }

    public function test_admin_sees_thai_translation_automatically_for_non_thai_worker(): void
    {
        $this->fakeTranslator();
        $ticket = $this->burmeseTicket();
        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password-1']));

        $this->get(route('admin.tickets.show', $ticket))->assertSee('[th] ဖိုင်ဖွင့်မရပါ');
        $this->get(route('admin.tickets.show', [$ticket, 'translate' => 0]))->assertDontSee('[th] ဖိုင်ဖွင့်မရပါ');
    }

    public function test_admin_previews_translation_then_worker_sees_vetted_text(): void
    {
        $this->fakeTranslator();
        $ticket = $this->burmeseTicket();
        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password-1']));

        $this->postJson(route('admin.tickets.translate', $ticket), ['text' => 'รับเรื่องแล้วค่ะ', 'locale' => 'my'])
            ->assertOk()->assertExactJson(['text' => '[my] รับเรื่องแล้วค่ะ']);

        // เจ้าหน้าที่แก้คำแปลก่อนส่ง
        $this->post(route('admin.tickets.reply', $ticket), [
            'body' => 'รับเรื่องแล้วค่ะ', 'status' => 'in_progress', 'reply_locale' => 'my', 'body_translated' => 'ကိုင်တွယ်နေပါပြီ',
        ]);
        $reply = $ticket->messages()->reorder()->latest('id')->first();
        $this->assertSame('ကိုင်တွယ်နေပါပြီ', $reply->body_translated);
        $this->assertSame('my', $reply->translated_locale);

        // แรงงานเห็นคำแปลที่ตรวจแล้ว โดยไม่ต้องเรียก Google อีก
        Http::fake(['translation.googleapis.com/*' => Http::response('', 500)]);
        $this->get(route('foreign.tickets.show', $ticket).'?lang=my')->assertOk()
            ->assertSee('ကိုင်တွယ်နေပါပြီ')->assertSee('รับเรื่องแล้วค่ะ'); // ต้นฉบับใน "ดูข้อความต้นฉบับ"
    }

    public function test_admin_reply_is_translated_automatically_when_not_previewed(): void
    {
        $this->fakeTranslator();
        $ticket = $this->burmeseTicket();
        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password-1']));

        $this->post(route('admin.tickets.reply', $ticket), ['body' => 'ส่งเอกสารแล้ว', 'status' => 'resolved', 'reply_locale' => 'km']);
        $this->assertSame(['[km] ส่งเอกสารแล้ว', 'km'], [
            $ticket->messages()->reorder()->latest('id')->first()->body_translated,
            $ticket->messages()->reorder()->latest('id')->first()->translated_locale,
        ]);

        $this->post(route('admin.tickets.reply', $ticket), ['body' => 'ข้อความไทย', 'status' => 'resolved', 'reply_locale' => 'th']);
        $this->assertNull($ticket->messages()->reorder()->latest('id')->first()->body_translated);
    }

    public function test_translate_endpoint_requires_admin_and_reports_unavailable_service(): void
    {
        $ticket = $this->burmeseTicket();
        $this->post('/foreign/logout');

        $this->postJson(route('admin.tickets.translate', $ticket), ['text' => 'x', 'locale' => 'my'])->assertUnauthorized();

        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password-1']));
        $this->postJson(route('admin.tickets.translate', $ticket), ['text' => 'x', 'locale' => 'my'])->assertStatus(503); // ไม่มี API key
        $this->postJson(route('admin.tickets.translate', $ticket), ['text' => 'x', 'locale' => 'xx'])->assertUnprocessable();
    }

    public function test_errors_return_to_form_even_after_browser_hits_a_missing_url(): void
    {
        $this->loginWorker();
        $this->get('/foreign/tickets/new?type=problem')->assertOk();
        // Chrome DevTools เรียก URL นี้เองทุกครั้งที่เปิดหน้า
        $this->get('/.well-known/appspecific/com.chrome.devtools.json')->assertNotFound();

        $this->post('/foreign/tickets', ['category' => 'problem'])
            ->assertRedirect('/foreign/tickets/new?type=problem')
            ->assertSessionHasErrors('subject');
    }

    public function test_storage_failure_returns_to_form_with_message(): void
    {
        config(['foreign.tickets.disk' => 'missing-disk']); // จำลอง R2 ใช้ไม่ได้
        $this->loginWorker();

        $this->post('/foreign/tickets', ['category' => 'problem', 'subject' => 's', 'body' => 'b', 'images' => [$this->image()]])
            ->assertRedirect(route('foreign.tickets.create', ['type' => 'problem']))
            ->assertSessionHasErrors('images');

        $this->assertSame(0, Ticket::count());
    }
}
