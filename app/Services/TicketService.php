<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Support\ForeignProfile;
use App\Support\Locales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketService
{
    public function __construct(private readonly GoogleTranslate $google) {}

    /** กฎตรวจรูปแนบ: หลายรูป, รูปละไม่เกิน 5MB */
    public static function imageRules(): array
    {
        $cfg = config('foreign.tickets');

        return [
            'images' => ['nullable', 'array', 'max:'.$cfg['max_images']],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.$cfg['max_image_kb']],
        ];
    }

    /**
     * ฟิลด์ที่แรงงานขอแก้ไขได้ = ทุกฟิลด์ที่แสดงในหน้าข้อมูล (ไม่ซ้ำ, ไม่รวมค่าที่คำนวณเอง)
     *
     * @return array<string, array{label:string, section:string, current:string}>
     */
    public static function correctableFields(array $record, array $hidden = []): array
    {
        $out = [];
        foreach (ForeignProfile::SECTIONS as [$title, $fields]) {
            foreach ($fields as $api => [$label, $type]) {
                if (str_starts_with($api, '_') || isset($out[$api]) || in_array($api, $hidden, true)) {
                    continue;
                }
                $out[$api] = [
                    'label' => $label,
                    'section' => $title,
                    'current' => ForeignProfile::format($record[$api] ?? null, $type),
                ];
            }
        }

        return $out;
    }

    /** สร้าง Ticket ใหม่จากแรงงาน พร้อมข้อความแรกและรูปแนบ */
    public function create(array $record, array $data, array $files = []): Ticket
    {
        $name = trim(implode(' ', array_filter([$record['First_Name'] ?? '', $record['Middle_Name'] ?? '', $record['Last_Name'] ?? ''])));

        $field = null;
        if ($data['category'] === 'correction') {
            $field = self::correctableFields($record, config('foreign.hidden_fields'))[$data['field_key']] ?? null;
        }

        return DB::transaction(function () use ($record, $data, $files, $name, $field) {
            $ticket = Ticket::create([
                'foreign_id' => (string) $record['id'],
                'worker_name' => $name ?: ($record['Full_Name_Labour'] ?? ''),
                'passport' => (string) ($record['Passport_ID'] ?? ''),
                'employer' => (string) ($record['Account_Name']['name'] ?? ''),
                'worker_email' => filter_var($record['Email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
                'locale' => app()->getLocale(),
                'category' => $data['category'],
                'subject' => $field ? $field['label'] : $data['subject'],
                'field_key' => $field ? $data['field_key'] : null,
                'field_label' => $field['label'] ?? null,
                'current_value' => $field['current'] ?? null,
                'requested_value' => $field ? $data['requested_value'] : null,
                'status' => 'open',
                'admin_unread' => true,
                'last_activity_at' => now(),
            ]);

            $this->storeMessage($ticket, 'worker', null, $data['body'] ?? null, $files);

            return $ticket;
        });
    }

    /**
     * เพิ่มข้อความตอบกลับ (แรงงานหรือ Admin)
     *
     * @param  array{text: string, locale: string}|null  $translation  คำแปลที่ส่งให้แรงงาน (เฉพาะ Admin)
     */
    public function reply(Ticket $ticket, string $author, ?int $userId, ?string $body, array $files = [], ?string $status = null, ?array $translation = null): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $author, $userId, $body, $files, $status, $translation) {
            $message = $this->storeMessage($ticket, $author, $userId, $body, $files);
            if ($translation && $message->body && trim($translation['text']) !== '') {
                $message->update(['body_translated' => trim($translation['text']), 'translated_locale' => $translation['locale']]);
            }

            $update = ['last_activity_at' => now()];
            if ($author === 'worker') {
                $update['admin_unread'] = true;
                // แรงงานตอบกลับเรื่องที่ "ดำเนินการแล้ว" = ยังไม่จบ -> เปิดใหม่
                if ($ticket->status === 'resolved') {
                    $update['status'] = 'open';
                }
            } else {
                $update['worker_unread'] = true;
                if ($status && isset(Ticket::STATUSES[$status])) {
                    $update['status'] = $status;
                }
            }
            $ticket->update($update);

            return $message;
        });
    }

    private function storeMessage(Ticket $ticket, string $author, ?int $userId, ?string $body, array $files): TicketMessage
    {
        $message = $ticket->messages()->create([
            'author_type' => $author,
            'user_id' => $userId,
            'body' => trim((string) $body) !== '' ? trim($body) : null,
        ]);

        $disk = config('foreign.tickets.disk');
        $stored = [];
        try {
            foreach ($files as $file) {
                /** @var UploadedFile $file */
                $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
                $path = Storage::disk($disk)->putFileAs("tickets/{$ticket->code}", $file, Str::uuid().'.'.$ext);
                $stored[] = $path;
                $message->attachments()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
                    'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => (int) $file->getSize(),
                ]);
            }
        } catch (\Throwable $e) {
            // อัปโหลดไม่ครบ -> ลบไฟล์ที่ขึ้นไปแล้ว (DB จะ rollback เอง)
            foreach ($stored as $path) {
                rescue(fn () => Storage::disk($disk)->delete($path), report: false);
            }
            throw $e;
        }

        return $message;
    }

    public function deleteAttachments(Ticket $ticket): void
    {
        foreach ($ticket->messages()->with('attachments')->get() as $m) {
            foreach ($m->attachments as $a) {
                rescue(fn () => Storage::disk($a->disk)->delete($a->path), report: false);
            }
        }
    }

    /**
     * แปลข้อความของอีกฝ่ายเป็นภาษาที่ต้องการ (ตรวจภาษาต้นฉบับอัตโนมัติ)
     *
     * @return array<int,string> [message id => คำแปล] เฉพาะที่แปลได้และต่างจากต้นฉบับ
     */
    public function translateMessages(iterable $messages, string $locale, string $fromAuthor): array
    {
        $bodies = [];
        $stored = [];
        foreach ($messages as $m) {
            if ($m->author_type !== $fromAuthor || ! $m->body) {
                continue;
            }
            // คำแปลที่เจ้าหน้าที่ตรวจแล้วตอนส่ง -> ใช้เลย ไม่ต้องเรียก Google
            if ($m->body_translated && $m->translated_locale === $locale) {
                $stored[$m->id] = $m->body_translated;
            } else {
                $bodies[$m->id] = $m->body;
            }
        }
        if (! $bodies) {
            return $stored;
        }

        $translated = $this->google->translateMany(array_values($bodies), Locales::googleCode($locale), null);

        $out = [];
        foreach ($bodies as $id => $body) {
            if (isset($translated[$body]) && trim($translated[$body]) !== trim($body)) {
                $out[$id] = $translated[$body];
            }
        }

        return $stored + $out;
    }

    /** แปลข้อความหนึ่งข้อความเป็นภาษาที่ระบุ (ตรวจภาษาต้นฉบับอัตโนมัติ) คืน null ถ้าแปลไม่ได้ */
    public function translateText(string $text, string $locale): ?string
    {
        $text = trim($text);
        if ($text === '' || ! Locales::has($locale)) {
            return null;
        }

        return $this->google->translate($text, Locales::googleCode($locale), null);
    }

    public static function attachmentResponse(TicketAttachment $a)
    {
        return Storage::disk($a->disk)->response($a->path, $a->original_name, [
            'Content-Type' => $a->mime,
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
