<?php

namespace App\Domains\Inventario\Services\Reportes\Concerns;

trait ManejaCalculosReporte
{
    private function normalizarLimit(mixed $limit): int
    {
        $limit = (int) $limit;

        if ($limit <= 0) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    private function metadata(array $filtros, int $limit): array
    {
        return [
            'generado_en' => now()->toISOString(),
            'limit' => $limit,
            'filtros' => $filtros,
        ];
    }

    private function redondear(float $valor, int $decimales = 4): float
    {
        return round($valor, $decimales);
    }

    private function claveProductoBodega(int $productoId, int $bodegaId): string
    {
        return $productoId . ':' . $bodegaId;
    }
}
