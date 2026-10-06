<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketNotifier;
use App\Services\TicketService;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketNotifier $notifier,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'active');
        $q = trim((string) $request->query('q', ''));

        $query = Ticket::query()->orderByDesc('admin_unread')->orderByDesc('last_activity_at');
        match ($status) {
            'active' => $query->whereIn('status', ['open', 'in_progress']),
            'all' => null,
            default => $query->where('status', $status),
        };
        if ($request->query('category') && isset(Ticket::CATEGORIES[$request->query('category')])) {
            $query->where('category', $request->query('category'));
        }
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")
                ->orWhere('passport', 'like', "%{$q}%")
                ->orWhere('worker_name', 'like', "%{$q}%")
                ->orWhere('employer', 'like', "%{$q}%")
                ->orWhere('subject', 'like', "%{$q}%"));
        }

        $counts = Ticket::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return response()->view('admin.tickets.index', [
            'company' => config('foreign.company'),
            'tickets' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'q' => $q,
            'counts' => $counts,
            'unread' => Ticket::where('admin_unread', true)->count(),
        ]);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        if ($ticket->admin_unread) {
            $ticket->forceFill(['admin_unread' => false])->saveQuietly();
        }

        $messages = $ticket->messages()->with(['attachments', 'user'])->get();
        // แรงงานใช้ภาษาอื่น -> แสดงคำแปลไทยให้อัตโนมัติ (กดซ่อนได้ด้วย ?translate=0)
        $translate = $request->has('translate') ? $request->boolean('translate') : $ticket->locale !== 'th';

        return response()->view('admin.tickets.show', [
            'company' => config('foreign.company'),
            'ticket' => $ticket,
            'messages' => $messages,
            'translate' => $translate,
            'translations' => $translate ? $this->tickets->translateMessages($messages, 'th', 'worker') : [],
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'], // ว่างได้ = เปลี่ยนสถานะอย่างเดียว
            'status' => ['required', Rule::in(array_keys(Ticket::STATUSES))],
            'reply_locale' => ['nullable', Rule::in(array_keys(Locales::SUPPORTED))],
            'body_translated' => ['nullable', 'string', 'max:5000'],
            ...TicketService::imageRules(),
        ], [], ['body' => 'ข้อความ', 'images.*' => 'รูปภาพ']);

        $files = $request->file('images', []);
        if (($data['body'] ?? null) || $files) {
            try {
                $message = $this->tickets->reply($ticket, 'admin', $request->user()->id, $data['body'] ?? null, $files, $data['status'], $this->replyTranslation($data));
                $this->notifier->adminReplied($ticket, $message);
            } catch (\Throwable $e) {
                report($e);

                return redirect()->route('admin.tickets.show', $ticket)->withInput()->withErrors(['images' => 'ส่งไม่สำเร็จ (อัปโหลดรูปไป R2 ไม่ได้?) กรุณาลองใหม่']);
            }
            $msg = 'ส่งคำตอบแล้ว';
        } else {
            $oldStatus = $ticket->status;
            $ticket->update(['status' => $data['status'], 'worker_unread' => true, 'last_activity_at' => now()]);
            $this->notifier->statusChanged($ticket, $oldStatus);
            $msg = 'เปลี่ยนสถานะแล้ว';
        }

        return redirect()->route('admin.tickets.show', $ticket)->with('notice', $msg);
    }

    /** ปุ่ม "แปลก่อนส่ง": แปลข้อความของเจ้าหน้าที่เป็นภาษาที่เลือก ให้ตรวจ/แก้ก่อนส่ง */
    public function translate(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'locale' => ['required', Rule::in(array_keys(Locales::SUPPORTED))],
        ]);

        $text = $this->tickets->translateText($data['text'], $data['locale']);

        return $text === null
            ? response()->json(['message' => 'แปลไม่ได้ (ยังไม่ได้ตั้งค่า Google Translate หรือ API ขัดข้อง)'], 503)
            : response()->json(['text' => $text]);
    }

    /**
     * คำแปลที่จะส่งให้แรงงาน: ใช้ที่เจ้าหน้าที่ตรวจแล้ว (กด "แปลก่อนส่ง") ถ้าไม่ได้กด ระบบแปลให้ตอนส่ง
     *
     * @return array{text: string, locale: string}|null
     */
    private function replyTranslation(array $data): ?array
    {
        $locale = $data['reply_locale'] ?? null;
        if (! ($data['body'] ?? null) || ! $locale || $locale === 'th') {
            return null;
        }

        $text = trim((string) ($data['body_translated'] ?? '')) ?: $this->tickets->translateText($data['body'], $locale);

        return $text ? ['text' => $text, 'locale' => $locale] : null;
    }

    public function attachment(Ticket $ticket, TicketAttachment $attachment)
    {
        abort_unless($attachment->message?->ticket_id === $ticket->id, 404);

        return TicketService::attachmentResponse($attachment);
    }
}
