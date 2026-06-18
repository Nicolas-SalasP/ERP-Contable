<?php

namespace App\Domains\Soporte\Services;

use App\Support\HmacFirma;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SoporteClienteService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.tenri_web.base_url', '');
        $this->apiKey  = config('services.tenri_web.api_key', '');
    }

    public function listar(int $empresaId, array $filtros = []): ?array
    {
        return $this->get('/api/internal/erp/tickets', array_merge(['empresa_id' => $empresaId], $filtros));
    }

    public function crear(array $datos): ?array
    {
        return $this->post('/api/internal/erp/tickets', $datos);
    }

    public function ver(int $empresaId, int $ticketId): ?array
    {
        return $this->get("/api/internal/erp/tickets/{$ticketId}", ['empresa_id' => $empresaId]);
    }

    public function responder(int $empresaId, int $ticketId, string $mensaje, string $email, string $nombre): ?array
    {
        return $this->post("/api/internal/erp/tickets/{$ticketId}/reply", [
            'empresa_id'    => $empresaId,
            'origen_email'  => $email,
            'origen_nombre' => $nombre,
            'message'       => $mensaje,
        ]);
    }

    private function get(string $path, array $params = []): ?array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            return null;
        }

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withRequestMiddleware(fn ($req) => HmacFirma::firmarPsr($req, $this->apiKey))
                ->timeout(8)
                ->get($this->baseUrl . $path, $params);

            if (!$response->successful()) {
                Log::warning('SoporteClienteService: respuesta inesperada', [
                    'status' => $response->status(),
                    'path'   => $path,
                ]);
                return null;
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::warning('SoporteClienteService: error de red', [
                'error' => $e->getMessage(),
                'path'  => $path,
            ]);
            return null;
        }
    }

    private function post(string $path, array $payload): ?array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            return null;
        }

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withRequestMiddleware(fn ($req) => HmacFirma::firmarPsr($req, $this->apiKey))
                ->timeout(8)
                ->post($this->baseUrl . $path, $payload);

            if (!$response->successful()) {
                Log::warning('SoporteClienteService: error en POST', [
                    'status' => $response->status(),
                    'path'   => $path,
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::warning('SoporteClienteService: error de red en POST', [
                'error' => $e->getMessage(),
                'path'  => $path,
            ]);
            return null;
        }
    }
}
