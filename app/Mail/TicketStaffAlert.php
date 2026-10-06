<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * แจ้งเจ้าหน้าที่: มีเรื่องใหม่ / แรงงานตอบกลับ (ภาษาไทย)
 */
class TicketStaffAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'opened'|'worker_replied'  $event
     */
    public function __construct(
        public Ticket $ticket,
        public TicketMessage $ticketMessage,
        public string $event,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->event === 'opened' ? 'เรื่องใหม่' : 'แรงงานตอบกลับ';

        return new Envelope(
            subject: "[{$this->ticket->code}] {$prefix}: {$this->ticket->subject}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ticket-staff',
            with: [
                'headline' => $this->event === 'opened' ? 'มีเรื่องใหม่จากแรงงาน' : 'แรงงานตอบกลับเรื่อง',
                'url' => route('admin.tickets.show', $this->ticket),
                'attachmentCount' => $this->ticketMessage->attachments()->count(),
            ],
        );
    }
}
