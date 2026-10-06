<?php

namespace App\Http\Controllers\Foreign;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\ForeignData;
use App\Support\ForeignProfile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProfileController extends Controller
{
    public function __construct(private readonly ForeignData $foreign) {}

    public function show(Request $request): Response
    {
        try {
            $record = $this->foreign->get($request->attributes->get('foreign_id'));
        } catch (\Throwable $e) {
            report($e);

            return $this->message(__('ระบบขัดข้องชั่วคราว'), __('กรุณาลองใหม่อีกครั้งในอีกสักครู่'), 503);
        }

        if (! $record) {
            $request->session()->forget(['foreign.id', 'foreign.expires_at']);

            return $this->message(__('ไม่พบข้อมูล'), __('ไม่สามารถแสดงข้อมูลได้ กรุณาติดต่อเจ้าหน้าที่'), 404);
        }

        $p = ForeignProfile::build($record, config('foreign.hidden_fields'), config('foreign.expiry_warning_days'));

        // ฐานข้อมูล Ticket ล่ม -> หน้าข้อมูลยังเปิดได้ แค่ไม่มีรายการ Ticket
        $tickets = rescue(fn () => Ticket::where('foreign_id', (string) $record['id'])
            ->orderByDesc('last_activity_at')->limit(50)->get(), collect());

        return response()->view('foreign.profile', [
            'p' => $p,
            'tickets' => $tickets,
            'company' => config('foreign.company'),
            'expiresAt' => (int) $request->session()->get('foreign.expires_at'),
        ]);
    }

    public function photo(Request $request): Response
    {
        try {
            $record = $this->foreign->get($request->attributes->get('foreign_id'));
            $photo = $record ? $this->foreign->photo($record) : null;
        } catch (\Throwable $e) {
            report($e);
            $photo = null;
        }

        abort_unless($photo, 404);

        return response($photo['body'], 200, ['Content-Type' => $photo['type']]);
    }

    public function document(Request $request, string $rowId): Response
    {
        abort_unless(preg_match('/^\d{5,25}$/', $rowId), 404);

        try {
            $record = $this->foreign->get($request->attributes->get('foreign_id'));
            $row = $record ? ForeignData::findDocument($record, $rowId) : null;
            abort_unless($row, 404);

            $file = $this->foreign->downloadDocument($row);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->message(__('เปิดเอกสารไม่ได้'), __('กรุณาลองใหม่อีกครั้งในอีกสักครู่'), 502);
        }

        abort_unless($file, 404);

        $name = $this->safeFilename($row['field6'] ?? '', $rowId);
        $inline = ! $request->boolean('download')
            && (str_starts_with($file['type'], 'image/') || str_starts_with($file['type'], 'application/pdf'));

        return response($file['body'], 200, [
            'Content-Type' => $file['type'],
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                ."; filename=\"{$name}\"",
        ]);
    }

    private function safeFilename(string $name, string $fallback): string
    {
        $name = preg_replace('/[^A-Za-z0-9.\-_]+/', '_', $name);

        return trim($name, '._') !== '' ? $name : "document-{$fallback}";
    }

    private function message(string $title, string $message, int $status): Response
    {
        return response()->view('foreign.message', [
            'title' => $title,
            'message' => $message,
            'company' => config('foreign.company'),
        ], $status);
    }
}
