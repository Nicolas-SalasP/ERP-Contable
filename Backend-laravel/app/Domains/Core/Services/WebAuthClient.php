<?php

namespace App\Domains\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebAuthClient
{
    public function validateLogin(string $email, string $password): ?array
    {
        $baseUrl = config('services.tenri_web.base_url');
        $apiKey = config('services.tenri_web.api_key');

        if (!$baseUrl || !$apiKey) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-ERP-API-KEY' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(5)
                ->post("{$baseUrl}/api/internal/erp/validate-login", [
                    'email' => $email,
                    'password' => $password,
                ]);

            if ($response->status() === 401) {
                return ['valid' => false];
            }

            if (!$response->successful()) {
                Log::warning('WebAuthClient: respuesta inesperada del web', [
                    'status' => $response->status(),
                    'email' => $email,
                ]);
                return null;
            }

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                return ['valid' => false];
            }

            return [
                'valid' => true,
                'tenri_user_id' => $data['user']['tenri_user_id'] ?? null,
                'name' => $data['user']['name'] ?? null,
                'email' => $data['user']['email'] ?? null,
                'password_hash' => $data['user']['password_hash'] ?? null,
                'plan_slug' => $data['plan']['plan_slug'] ?? null,
                'module_keys' => $data['plan']['module_keys'] ?? [],
                'rol_erp' => $data['plan']['rol_erp'] ?? 'Administrador',
            ];
        } catch (Throwable $e) {
            Log::warning('WebAuthClient: error de red al validar login', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);

            return null;
        }
    }
}
