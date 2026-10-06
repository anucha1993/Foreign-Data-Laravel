<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google Cloud Translation API (v2, REST + API key)
 * - แปลทีละหลายข้อความในการเรียกครั้งเดียว และแคชผลแต่ละข้อความ 30 วัน
 * - ป้องกัน placeholder ของ Laravel (:n, :m) ไม่ให้ถูกแปล
 * - ไม่มีคีย์ / API ล้มเหลว -> คืนเฉพาะที่แปลได้ (ผู้เรียกใช้ข้อความเดิมแทน)
 */
class GoogleTranslate
{
    private const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    private const BATCH = 100; // API รับได้สูงสุด 128 ข้อความต่อครั้ง

    public function __construct(private readonly ?string $apiKey) {}

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey;
    }

    public function translate(string $text, string $target, ?string $source = 'th'): ?string
    {
        return $this->translateMany([$text], $target, $source)[$text] ?? null;
    }

    /**
     * @param  string|null  $source  null = ให้ Google ตรวจภาษาต้นฉบับเอง
     * @return array<string,string> [ข้อความต้นฉบับ => คำแปล]
     */
    public function translateMany(array $texts, string $target, ?string $source = 'th'): array
    {
        $texts = array_values(array_unique(array_filter($texts, fn ($t) => is_string($t) && trim($t) !== '')));
        $result = [];
        $missing = [];

        foreach ($texts as $t) {
            $hit = Cache::get($this->key($t, $target, $source));
            $hit !== null ? $result[$t] = $hit : $missing[] = $t;
        }

        if (! $missing || ! $this->apiKey) {
            return $result;
        }

        foreach (array_chunk($missing, self::BATCH) as $chunk) {
            try {
                $res = Http::timeout(8)->post(self::ENDPOINT.'?'.http_build_query(['key' => $this->apiKey]), array_filter([
                    'q' => array_map(fn ($t) => $this->protect($t), $chunk),
                    'source' => $source,
                    'target' => $target,
                    'format' => 'html',
                ]));
            } catch (\Throwable $e) {
                report($e);

                return $result;
            }

            if (! $res->successful()) {
                report(new \RuntimeException('Google Translate API '.$res->status().': '.$res->json('error.message')));

                return $result;
            }

            foreach ($res->json('data.translations') ?? [] as $i => $row) {
                $out = $this->unprotect($row['translatedText'] ?? '');
                if ($out !== '' && isset($chunk[$i])) {
                    $result[$chunk[$i]] = $out;
                    Cache::put($this->key($chunk[$i], $target, $source), $out, now()->addDays(30));
                }
            }
        }

        return $result;
    }

    /** ห่อ placeholder (:n) ด้วย span translate="no" และ escape เป็น HTML */
    private function protect(string $text): string
    {
        return preg_replace('/:(\w+)/', '<span translate="no">:$1</span>', htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function unprotect(string $html): string
    {
        $text = preg_replace('~<span translate="no">\s*(:\w+)\s*</span>~u', '$1', $html);

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function key(string $text, string $target, ?string $source): string
    {
        return 'gtranslate:'.($source ?? 'auto').":{$target}:".md5($text);
    }
}
