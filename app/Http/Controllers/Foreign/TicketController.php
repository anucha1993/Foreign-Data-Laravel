<?php

namespace App\Http\Controllers\Foreign;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\ForeignData;
use App\Services\TicketNotifier;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    public function __construct(
        private readonly ForeignData $foreign,
        private readonly TicketService $tickets,
        private readonly TicketNotifier $notifier,
    ) {}

    private function workerId(Request $request): string
    {
        return (string) $request->attributes->get('foreign_id');
    }

    /** เปิดได้เฉพาะเรื่องของตัวเอง (ไม่บอกว่ามีเรื่องนี้อยู่) */
    private function own(Request $request, Ticket $ticket): Ticket
    {
        abort_unless($ticket->foreign_id === $this->workerId($request), 404);

        return $ticket;
    }

    private function record(Request $request): array
    {
        $record = $this->foreign->get($this->workerId($request));
        abort_unless($record, 404);

        return $record;
    }

    public function create(Request $request): Response
    {
        $record = $this->record($request);
        $category = $request->query('type') === 'correction' ? 'correction' : 'problem';

        return response()->view('foreign.tickets.create', [
            'company' => config('foreign.company'),
            'category' => $category,
            'fields' => TicketService::correctableFields($record, config('foreign.hidden_fields')),
            'selectedField' => (string) $request->query('field', ''),
            'section' => (string) $request->query('section', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $record = $this->record($request);
        $fields = array_keys(TicketService::correctableFields($record, config('foreign.hidden_fields')));

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'subject' => ['required_if:category,problem', 'nullable', 'string', 'max:150'],
            'field_key' => ['required_if:category,correction', 'nullable', Rule::in($fields)],
            'requested_value' => ['required_if:category,correction', 'nullable', 'string', 'max:500'],
            'body' => ['required_if:category,problem', 'nullable', 'string', 'max:3000'],
            ...TicketService::imageRules(),
        ], [], $this->attributes());

        try {
            $ticket = $this->tickets->create($record, $data, $request->file('images', []));
            $this->notifier->opened($ticket, $ticket->messages()->first());
        } catch (\Throwable $e) {
            report($e); // เช่น R2 ยังไม่ตั้งค่า / เชื่อมต่อไม่ได้

            return redirect()->route('foreign.tickets.create', ['type' => $data['category']])
                ->withInput()->withErrors(['images' => __('ส่งไม่สำเร็จ กรุณาลองใหม่อีกครั้ง')]);
        }

        return redirect()->route('foreign.tickets.show', $ticket)
            ->with('notice', __('ส่งเรื่องเรียบร้อยแล้ว เจ้าหน้าที่จะตอบกลับโดยเร็ว'));
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $this->own($request, $ticket);
        if ($ticket->worker_unread) {
            $ticket->forceFill(['worker_unread' => false])->saveQuietly();
        }

        $messages = $ticket->messages()->with('attachments')->get();
        $locale = app()->getLocale();

        return response()->view('foreign.tickets.show', [
            'company' => config('foreign.company'),
            'ticket' => $ticket,
            'messages' => $messages,
            // คำตอบเจ้าหน้าที่ (ภาษาไทย) -> แปลเป็นภาษาของแรงงาน
            'translations' => $locale === 'th' ? [] : $this->tickets->translateMessages($messages, $locale, 'admin'),
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->own($request, $ticket);
        if ($ticket->isClosed()) {
            return redirect()->route('foreign.tickets.show', $ticket)->withErrors(['body' => __('เรื่องนี้ปิดแล้ว หากมีปัญหาใหม่กรุณาแจ้งเรื่องใหม่')]);
        }

        $data = $request->validate([
            'body' => ['required_without:images', 'nullable', 'string', 'max:3000'],
            ...TicketService::imageRules(),
        ], [], $this->attributes());

        try {
            $message = $this->tickets->reply($ticket, 'worker', null, $data['body'] ?? null, $request->file('images', []));
            $this->notifier->workerReplied($ticket, $message);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('foreign.tickets.show', $ticket)->withInput()->withErrors(['images' => __('ส่งไม่สำเร็จ กรุณาลองใหม่อีกครั้ง')]);
        }

        return redirect()->route('foreign.tickets.show', $ticket)->with('notice', __('ส่งข้อความแล้ว'));
    }

    public function attachment(Request $request, Ticket $ticket, TicketAttachment $attachment)
    {
        $this->own($request, $ticket);
        abort_unless($attachment->message?->ticket_id === $ticket->id, 404);

        return TicketService::attachmentResponse($attachment);
    }

    private function attributes(): array
    {
        return [
            'subject' => __('หัวข้อ'),
            'field_key' => __('ข้อมูลที่ต้องการแก้ไข'),
            'requested_value' => __('ข้อมูลที่ถูกต้อง'),
            'body' => __('รายละเอียด'),
            'images' => __('รูปภาพ'),
            'images.*' => __('รูปภาพ'),
        ];
    }
}
