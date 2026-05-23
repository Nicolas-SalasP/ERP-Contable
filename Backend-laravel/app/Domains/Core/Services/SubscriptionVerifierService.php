<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubscriptionVerifierService
{
    private const CACHE_TTL = 60;
    private const CACHE_PREFIX = 'erp_sub_';

    public function isActive(User $user): bool
    {
        if (!$user->tenri_user_id) {
            return true;
        }

        $cacheKey = self::CACHE_PREFIX . $user->tenri_user_id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $this->fetchFromWeb($user->tenri_user_id);
        });
    }

    public function forgetCache(int $tenriUserId): void
    {
        Cache::forget(self::CACHE_PREFIX . $tenriUserId);
    }

    private function fetchFromWeb(?int $tenriUserId): bool
    {
        if (!$tenriUserId) {
            return true;
        }

        $baseUrl = config('services.tenri_web.base_url');
        $apiKey = config('services.tenri_web.api_key');

        if (!$baseUrl || !$apiKey) {
            return true;
        }

        try {
            $response = Http::withHeaders([
                'X-ERP-API-KEY' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(5)
                ->post("{$baseUrl}/api/internal/erp/validate-token", [
                    'tenri_user_id' => $tenriUserId,
                ]);

            if (!$response->successful()) {
                Log::warning('SubscriptionVerifier: respuesta no exitosa del web', [
                    'status' => $response->status(),
                    'tenri_user_id' => $tenriUserId,
                ]);
                return true;
            }

            $data = $response->json();

            return (bool) ($data['valid'] ?? true) && (bool) ($data['is_active'] ?? true);
        } catch (Throwable $e) {
            Log::warning('SubscriptionVerifier: error de red al verificar suscripción', [
                'error' => $e->getMessage(),
                'tenri_user_id' => $tenriUserId,
            ]);
            return true;
        }
    }
}
