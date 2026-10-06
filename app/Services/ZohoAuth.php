<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zoho OAuth: แลก refresh token เป็น access token
 * - แคช access token ผ่าน Laravel Cache (ข้าม request ได้)
 * - ใช้ cache lock กันหลาย request refresh พร้อมกัน (Zoho จำกัดจำนวน access token ต่อ refresh token ต่อช่วงเวลา)
 */
class ZohoAuth
{
    private const CACHE_KEY = 'zoho:access_token';

    private const LOCK_KEY = 'zoho:access_token:lock';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
        private readonly string $accountsUrl,
    ) {}

    public function getAccessToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached) {
                return $cached;
            }
        }

        return Cache::lock(self::LOCK_KEY, 15)->block(10, function () use ($forceRefresh) {
            if (! $forceRefresh) {
                $cached = Cache::get(self::CACHE_KEY);
                if ($cached) {
                    return $cached;
                }
            }

            return $this->refresh();
        });
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function refresh(): string
    {
        $res = Http::asForm()->timeout(15)->post("{$this->accountsUrl}/oauth/v2/token", [
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        $json = $res->json() ?? [];

        // Zoho มักตอบ 200 แต่มี field error มาแทน
        if (! $res->successful() || isset($json['error']) || empty($json['access_token'])) {
            $reason = $json['error'] ?? ('HTTP '.$res->status());
            throw new RuntimeException("Zoho token refresh failed: {$reason}");
        }

        $ttl = max(60, (int) ($json['expires_in'] ?? 3600) - 120); // refresh ก่อนหมดอายุ 2 นาที
        Cache::put(self::CACHE_KEY, $json['access_token'], $ttl);

        return $json['access_token'];
    }
}
