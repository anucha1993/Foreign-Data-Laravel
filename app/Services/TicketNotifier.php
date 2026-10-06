<?php

namespace App\Services;

use App\Mail\TicketStaffAlert;
use App\Mail\TicketWorkerUpdate;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

use function Illuminate\Support\defer;

/**
 * ส่งอีเมลแจ้งเตือนของระบบ Ticket
 * - ส่งหลังตอบกลับหน้าเว็บแล้ว (defer) ผู้ใช้ไม่ต้องรอ SMTP
 * - ส่งไม่สำเร็จ = report แล้วข้าม (เรื่องที่แจ้งยังบันทึกปกติ)
 */
class TicketNotifier
{
    public function __construct(private readonly TicketService $tickets) {}

    /** แรงงานแจ้งเรื่องใหม่: แจ้งเจ้าหน้าที่ + ยืนยันกับแรงงาน */
    public function opened(Ticket $ticket, TicketMessage $message): void
    {
        $this->toStaff(new TicketStaffAlert($ticket, $message, 'opened'));
        $this->toWorker($ticket, fn () => new TicketWorkerUpdate($ticket, 'received'));
    }

    /** แรงงานตอบกลับ: แจ้งเจ้าหน้าที่ */
    public function workerReplied(Ticket $ticket, TicketMessage $message): void
    {
        $this->toStaff(new TicketStaffAlert($ticket, $message, 'worker_replied'));
    }

    /** เจ้าหน้าที่ตอบกลับ: แจ้งแรงงาน พร้อมข้อความที่แปลเป็นภาษาของแรงงาน */
    public function adminReplied(Ticket $ticket, TicketMessage $message): void
    {
        $this->toWorker($ticket, function () use ($ticket, $message) {
            $body = $message->body;
            if ($body && $ticket->locale !== 'th') {
                $body = $this->tickets->translateMessages([$message], $ticket->locale, 'admin')[$message->id] ?? $body;
            }

            return new TicketWorkerUpdate($ticket, 'replied', $body);
        });
    }

    /** เจ้าหน้าที่เปลี่ยนสถานะอย่างเดียว (ไม่มีข้อความ) */
    public function statusChanged(Ticket $ticket, string $oldStatus): void
    {
        if ($ticket->status !== $oldStatus) {
            $this->toWorker($ticket, fn () => new TicketWorkerUpdate($ticket, 'status'));
        }
    }

    /**
     * ผู้รับฝั่งเจ้าหน้าที่: TICKET_NOTIFY_EMAILS ถ้าตั้งไว้ ไม่งั้นส่งถึงบัญชี Admin ทุกคน
     *
     * @return list<string>
     */
    public static function staffRecipients(): array
    {
        $configured = config('foreign.tickets.notify_emails');

        return $configured ?: User::query()->pluck('email')->filter()->values()->all();
    }

    private function toStaff(Mailable $mail): void
    {
        $this->send(function () use ($mail) {
            $to = self::staffRecipients();
            if ($to) {
                Mail::to($to)->locale('th')->send($mail);
            }
        });
    }

    /**
     * @param  callable(): Mailable  $build  สร้างอีเมลตอนส่ง (ในภาษาของแรงงาน)
     */
    private function toWorker(Ticket $ticket, callable $build): void
    {
        if (! config('foreign.tickets.email_workers') || ! filter_var($ticket->worker_email, FILTER_VALIDATE_EMAIL)) {
            return; // ปิดการส่งถึงแรงงาน หรือแรงงานไม่มีอีเมลใน CRM
        }

        $this->send(fn () => Mail::to($ticket->worker_email)->locale($ticket->locale)->send($build()));
    }

    private function send(callable $callback): void
    {
        defer(fn () => rescue($callback));
    }
}
