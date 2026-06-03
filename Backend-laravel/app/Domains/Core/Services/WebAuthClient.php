<?php

namespace App\Domains\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebAuthClient
{
    public function validateLogin(string $email, string $password): ?array
    {
        return $this->postValidacion('/api/internal/erp/validate-login', [
            'email' => $email,
            'password' => $password,
        ], ['email' => $email]);
    }

    /**
     * Valida un token SSO de un solo uso emitido por la web Tenri y devuelve
     * los datos de provisionamiento del usuario (mismo contrato que validateLogin).
     *
     * Contrato esperado en la web: POST {base}/api/internal/erp/validate-sso-token
     * con { sso_token } -> { success, user{...}, plan{...} }.
     */
    public function validateSsoToken(string $ssoToken): ?array
    {
        return $this->postValidacion('/api/internal/erp/validate-sso-token', [
            'sso_token' => $ssoToken,
        ], ['sso_token' => substr($ssoToken, 0, 6) . '…']);
    }

    /**
     * Realiza el POST de validacion contra la web y normaliza la respuesta al
     * contrato comun usado por el login y el SSO.
     *
     * @param  array  $payload    Cuerpo enviado a la web.
     * @param  array  $logContext Datos no sensibles para los logs.
     */
    private function postValidacion(string $path, array $payload, array $logContext): ?array
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
                ->post("{$baseUrl}{$path}", $payload);

            if ($response->status() === 401) {
                return ['valid' => false];
            }

            if (!$response->successful()) {
                Log::warning('WebAuthClient: respuesta inesperada del web', array_merge([
                    'status' => $response->status(),
                    'path' => $path,
                ], $logContext));
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
            Log::warning('WebAuthClient: error de red al validar contra la web', array_merge([
                'error' => $e->getMessage(),
                'path' => $path,
            ], $logContext));

            return null;
        }
    }
}
