<?php

namespace App\Domains\CorreccionMonetaria\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class IneApiIpcProvider implements IpcProviderInterface
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = config('correccion_monetaria.ine_api.base_url', 'https://servicios.ine.cl/IPCV2/api/v1');
        $this->apiKey = config('correccion_monetaria.ine_api.api_key', '');
        $this->timeoutSeconds = config('correccion_monetaria.ine_api.timeout', 10);
    }

    public function getVariacionMensual(int $anio, int $mes): ?float
    {
        $this->lanzarNoImplementado();
        return null;
    }

    public function getFactorMultiplicador(int $anio, int $mes): ?float
    {
        $variacion = $this->getVariacionMensual($anio, $mes);
        if ($variacion === null) {
            return null;
        }
        return 1 + ($variacion / 100);
    }

    public function getVariacionAcumulada(int $anio, int $mesHasta): ?float
    {
        $this->lanzarNoImplementado();
        return null;
    }

    public function tieneIndice(int $anio, int $mes): bool
    {
        return false;
    }

    public function getNombre(): string
    {
        return 'API INE (no implementado aún)';
    }

    private function consultarApiIne(int $anio, int $mes): ?array
    {
        /*
        $cacheKey = "ine_api_ipc_{$anio}_{$mes}";

        return Cache::remember($cacheKey, 86400, function () use ($anio, $mes) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Accept'        => 'application/json',
                ])
                ->timeout($this->timeoutSeconds)
                ->get("{$this->baseUrl}/variacion-mensual/{$anio}/{$mes}");

                if ($response->successful()) {
                    $data = $response->json();
                    // La estructura exacta depende de la API del INE.
                    // Adaptar según documentación cuando se implemente.
                    return [
                        'variacion'  => (float) ($data['variacion'] ?? $data['value'] ?? 0),
                        'url'        => $response->effectiveUri(),
                        'raw'        => $data,
                    ];
                }

                Log::warning("IneApiIpcProvider: respuesta no exitosa", [
                    'status' => $response->status(),
                    'anio'   => $anio,
                    'mes'    => $mes,
                ]);
                return null;

            } catch (\Exception $e) {
                Log::error("IneApiIpcProvider: error consultando API", [
                    'error' => $e->getMessage(),
                    'anio'  => $anio,
                    'mes'   => $mes,
                ]);
                return null;
            }
        });
        */

        return null;
    }

    private function lanzarNoImplementado(): void
    {
        throw new \RuntimeException(
            'El proveedor API del INE no está implementado aún. ' .
            'Usa el ingreso manual de índices IPC en el módulo de Corrección Monetaria. ' .
            'Para activar la integración, completa IneApiIpcProvider y configura ' .
            'CM_IPC_PROVIDER=api_ine en el .env.'
        );
    }
}