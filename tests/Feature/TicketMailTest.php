<?php

namespace Tests\Feature;

use App\Mail\TicketStaffAlert;
use App\Mail\TicketWorkerUpdate;
use App\Models\Ticket;
use App\Models\User;
use App\Services\GoogleTranslate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\ForeignRecord;
use Tests\TestCase;

class TicketMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const API = 'https://www.zohoapis.com/crm/v7';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.zoho.client_id' => 'cid',
            'services.zoho.client_secret' => 'secret',
            'services.zoho.refresh_token' => 'refresh',
        ]);
        Storage::fake('r2');
        $this->withoutDefer();
        config(['foreign.tickets.email_workers' => true]);
    }

    private function fakeCrm(array $override = []): void
    {
        $record = ForeignRecord::make($override);
        Http::preventStrayRequests();
        Http::fake([
            'accounts.zoho.com/oauth/v2/token' => Http::response(['access_token' => 'AT', 'expires_in' => 3600]),
            self::API.'/Foreign_Data/search*' => Http::response(['data' => [$record]]),
            self::API.'/Foreign_Data/'.ForeignRecord::ID => Http::response(['data' => [$record]]),
            'translation.googleapis.com/*' => fn ($r) => Http::response(['data' => ['translations' => array_map(
                fn ($q) => ['translatedText' => '['.$r->data()['target'].'] '.$q], $r->data()['q'])]]),
        ]);
    }

    private function openTicket(string $locale = 'th'): Ticket
    {
        $this->post('/foreign/login?lang='.$locale, ['passport' => 'MA1234567', 'password' => '69201376']);
        $this->post('/foreign/tickets?lang='.$locale, ['category' => 'problem', 'subject' => 'เปิดไฟล์ไม่ได้', 'body' => 'help'])->assertRedirect();

        return Ticket::firstOrFail();
    }

    private function admin(): User
    {
        return User::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret-password-1']);
    }

    public function test_new_ticket_alerts_staff_and_confirms_to_worker(): void
    {
        Mail::fake();
        $this->fakeCrm(['Email' => 'worker@example.com']);
        $this->admin();

        $ticket = $this->openTicket();

        Mail::assertSent(TicketStaffAlert::class, fn ($m) => $m->hasTo('staff@example.com') && $m->event === 'opened' && $m->ticket->is($ticket));
        Mail::assertSent(TicketWorkerUpdate::class, fn ($m) => $m->hasTo('worker@example.com') && $m->event === 'received');
        $this->assertSame('worker@example.com', $ticket->worker_email);
    }

    public function test_worker_without_email_gets_no_mail_and_notify_list_overrides_admins(): void
    {
        Mail::fake();
        config(['foreign.tickets.notify_emails' => ['desk@example.com', 'boss@example.com']]);
        $this->fakeCrm(['Email' => null]);
        $this->admin();

        $this->openTicket();

        Mail::assertNotSent(TicketWorkerUpdate::class);
        Mail::assertSent(TicketStaffAlert::class, fn ($m) => $m->hasTo('desk@example.com') && $m->hasTo('boss@example.com') && ! $m->hasTo('staff@example.com'));
    }

    public function test_worker_reply_alerts_staff(): void
    {
        Mail::fake();
        $this->fakeCrm();
        $this->admin();
        $ticket = $this->openTicket();

        $this->post(route('foreign.tickets.reply', $ticket), ['body' => 'ยังไม่ได้']);

        Mail::assertSent(TicketStaffAlert::class, fn ($m) => $m->event === 'worker_replied');
    }

    public function test_admin_reply_emails_worker_in_their_language(): void
    {
        Mail::fake();
        config(['services.google_translate.key' => 'k']);
        $this->app->singleton(GoogleTranslate::class, fn () => new GoogleTranslate('k'));
        $this->fakeCrm(['Email' => 'worker@example.com']);
        $ticket = $this->openTicket('my');
        $this->assertSame('my', $ticket->locale);

        $this->actingAs($this->admin())
            ->post(route('admin.tickets.reply', $ticket), ['body' => 'รับเรื่องแล้วค่ะ', 'status' => 'in_progress']);

        Mail::assertSent(TicketWorkerUpdate::class, fn ($m) => $m->event === 'replied'
            && $m->locale === 'my'
            && $m->staffMessage === '[my] รับเรื่องแล้วค่ะ');
    }

    public function test_status_change_emails_worker_only_when_status_changes(): void
    {
        Mail::fake();
        $this->fakeCrm(['Email' => 'worker@example.com']);
        $ticket = $this->openTicket();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['status' => 'open']);
        Mail::assertNotSent(TicketWorkerUpdate::class, fn ($m) => $m->event === 'status');

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['status' => 'resolved']);
        Mail::assertSent(TicketWorkerUpdate::class, fn ($m) => $m->event === 'status' && $m->ticket->status === 'resolved');
    }

    public function test_mail_failure_does_not_block_ticket(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $this->fakeCrm(['Email' => 'worker@example.com']);
        $this->admin();

        $ticket = $this->openTicket();

        $this->assertSame('open', $ticket->status);
        $this->get(route('foreign.tickets.show', $ticket))->assertOk();
    }

    public function test_emails_render_with_links(): void
    {
        $this->fakeCrm(['Email' => 'worker@example.com']);
        $ticket = $this->openTicket();

        $staff = new TicketStaffAlert($ticket, $ticket->messages()->first(), 'opened');
        $staff->assertSeeInHtml($ticket->code);
        $staff->assertSeeInHtml(route('admin.tickets.show', $ticket));
        $staff->assertHasSubject("[{$ticket->code}] เรื่องใหม่: เปิดไฟล์ไม่ได้");

        $worker = (new TicketWorkerUpdate($ticket, 'replied', 'ข้อความทดสอบ'))->locale('en');
        $worker->assertSeeInHtml('Message from our staff');
        $worker->assertSeeInHtml('ข้อความทดสอบ');
        $worker->assertSeeInHtml(route('foreign.tickets.show', $ticket));
    }

    public function test_worker_emails_are_off_by_default(): void
    {
        Mail::fake();
        config(['foreign.tickets.email_workers' => false]);
        $this->fakeCrm(['Email' => 'worker@example.com']);
        $admin = $this->admin();
        $ticket = $this->openTicket();

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['body' => 'ok', 'status' => 'resolved']);

        Mail::assertNotSent(TicketWorkerUpdate::class);
        Mail::assertSent(TicketStaffAlert::class, fn ($m) => $m->hasTo('staff@example.com'));
    }

    public function test_mail_test_command_sends_to_staff_recipients(): void
    {
        Mail::fake();
        config(['foreign.tickets.notify_emails' => ['me@example.com']]);

        $this->artisan('mail:test')->assertSuccessful();
    }
}
