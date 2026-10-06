<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * แจ้งแรงงาน: รับเรื่องแล้ว / เจ้าหน้าที่ตอบกลับ / สถานะเปลี่ยน
 * ส่งเป็นภาษาที่แรงงานใช้ตอนแจ้งเรื่อง (ผู้เรียกตั้ง ->locale($ticket->locale))
 */
class TicketWorkerUpdate extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'received'|'replied'|'status'  $event
     * @param  string|null  $staffMessage  ข้อความเจ้าหน้าที่ (แปลเป็นภาษาของแรงงานแล้ว ถ้าแปลได้)
     */
    public function __construct(
        public Ticket $ticket,
        public string $event,
        public ?string $staffMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            'received' => __('[:code] เราได้รับเรื่องของคุณแล้ว', ['code' => $this->ticket->code]),
            'replied' => __('[:code] เจ้าหน้าที่ตอบกลับเรื่องของคุณ', ['code' => $this->ticket->code]),
            default => __('[:code] สถานะเรื่องของคุณ: :status', ['code' => $this->ticket->code, 'status' => $this->ticket->statusLabel()]),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ticket-worker',
            with: [
                'headline' => match ($this->event) {
                    'received' => __('เราได้รับเรื่องของคุณแล้ว'),
                    'replied' => __('เจ้าหน้าที่ตอบกลับเรื่องของคุณ'),
                    default => __('สถานะเรื่องของคุณเปลี่ยนแปลง'),
                },
                'url' => route('foreign.tickets.show', $this->ticket),
            ],
        );
    }
}
