<?php

namespace App\Domains\CorreccionMonetaria\Providers;

use App\Domains\CorreccionMonetaria\Models\CmIndiceIpc;
use Illuminate\Support\Facades\Cache;

class ManualIpcProvider implements IpcProviderInterface
{
    private function cacheKey(int $anio, int $mes): string
    {
        return "cm_ipc_{$anio}_{$mes}";
    }

    private function getIndice(int $anio, int $mes): ?CmIndiceIpc
    {
        return Cache::remember(
            $this->cacheKey($anio, $mes),
            3600, // 1 hora
            fn() => CmIndiceIpc::where('anio', $anio)->where('mes', $mes)->first()
        );
    }

    public function invalidarCache(int $anio, int $mes): void
    {
        Cache::forget($this->cacheKey($anio, $mes));
    }

    public function getVariacionMensual(int $anio, int $mes): ?float
    {
        $indice = $this->getIndice($anio, $mes);
        return $indice ? (float) $indice->variacion_mensual : null;
    }

    public function getFactorMultiplicador(int $anio, int $mes): ?float
    {
        $indice = $this->getIndice($anio, $mes);
        return $indice ? (float) $indice->factor_multiplicador : null;
    }

    public function getVariacionAcumulada(int $anio, int $mesHasta): ?float
    {
        $indice = $this->getIndice($anio, $mesHasta);
        return $indice ? (float) $indice->variacion_acumulada_anual : null;
    }

    public function tieneIndice(int $anio, int $mes): bool
    {
        return $this->getIndice($anio, $mes) !== null;
    }

    public function getNombre(): string
    {
        return 'Manual (base de datos)';
    }
}