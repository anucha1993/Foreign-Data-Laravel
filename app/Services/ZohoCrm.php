<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * ตัวเรียก Zoho CRM API แบบบางๆ + แคชผลลัพธ์ระยะสั้น
 */
class ZohoCrm
{
    public function __construct(
        private readonly ZohoAuth $auth,
        private readonly string $apiDomain,
        private readonly string $apiVersion,
        private readonly int $cacheTtlSeconds,
    ) {}

    /** ดึง record เดียว (รวม subform) คืน null ถ้าไม่พบ */
    public function getRecord(string $module, string $id): ?array
    {
        if (! preg_match('/^\d{5,25}$/', $id)) {
            return null;
        }

        return Cache::remember("zoho:{$module}:{$id}", $this->cacheTtlSeconds, function () use ($module, $id) {
            try {
                return $this->json("/{$module}/{$id}")['data'][0] ?? null;
            } catch (ZohoCrmException $e) {
                if ($e->status === 404 || $e->zohoCode === 'INVALID_DATA') {
                    return null;
                }
                throw $e;
            }
        });
    }

    /**
     * ค้นหาด้วย criteria เช่น (Passport_ID:equals:AB123)
     * ผู้เรียกต้องกรองค่าที่ใส่ใน criteria เอง (ห้ามมี ( ) , : ที่ยังไม่ escape)
     */
    public function search(string $module, string $criteria): array
    {
        return $this->json("/{$module}/search", ['criteria' => $criteria, 'per_page' => 20])['data'] ?? [];
    }

    /** รูปโปรไฟล์ของ record คืน null ถ้าไม่มีรูป */
    public function getPhoto(string $module, string $id): ?array
    {
        $res = $this->send("/{$module}/{$id}/photo");
        if ($res->status() === 204 || ! $res->successful()) {
            return null;
        }

        return ['body' => $res->body(), 'type' => $res->header('Content-Type') ?: 'image/jpeg'];
    }

    /** เรียก URL ใดๆ ของ Zoho (เช่น WorkDrive) ด้วย access token เดียวกัน */
    public function authorizedGet(string $url, array $query = []): Response
    {
        return $this->withRetry(fn (string $token) => Http::withToken($token, 'Zoho-oauthtoken')
            ->timeout(60)
            ->get($url, $query));
    }

    private function json(string $path, array $query = []): array
    {
        $res = $this->send($path, $query);

        if ($res->status() === 204) {
            return [];
        }

        $json = $res->json() ?? [];

        if (! $res->successful()) {
            throw new ZohoCrmException(
                "Zoho CRM {$res->status()}: ".trim(($json['code'] ?? '').' '.($json['message'] ?? '')),
                $res->status(),
                $json['code'] ?? null,
            );
        }

        return $json;
    }

    private function send(string $path, array $query = []): Response
    {
        return $this->authorizedGet("{$this->apiDomain}/crm/{$this->apiVersion}{$path}", $query);
    }

    /** token ถูกเพิกถอน/หมดอายุก่อนเวลา -> refresh แล้วลองใหม่ 1 ครั้ง */
    private function withRetry(callable $call): Response
    {
        $res = $call($this->auth->getAccessToken());

        if ($res->status() === 401) {
            $this->auth->invalidate();
            $res = $call($this->auth->getAccessToken(true));
        }

        return $res;
    }
}
