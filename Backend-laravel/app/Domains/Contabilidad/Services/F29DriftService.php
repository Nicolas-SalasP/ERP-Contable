<?php

namespace App\Domains\Contabilidad\Services;

use Illuminate\Support\Facades\DB;

/**
 * Detecta cuando un F29 ya centralizado (asiento MAYORIZADO con glosa "Centralización F29 - MM/YYYY")
 * queda desactualizado por la anulación posterior de una factura o DTE del mismo período.
 * No relanza el F29 (eso requiere criterio contable humano): solo deja constancia consultable
 * para que la UI alerte al usuario (ver ImpuestosService::simularF29).
 */
class F29DriftService
{
    /** Si la fecha cae en un período con F29 ya centralizado para la empresa, registra (o actualiza) la alerta. */
    public function marcarSiPeriodoCentralizado(int $empresaId, string $fecha, string $motivo): void
    {
        $mes = (int) date('n', strtotime($fecha));
        $anio = (int) date('Y', strtotime($fecha));

        $glosa = 'Centralización F29 - ' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "/$anio";

        $centralizado = DB::table('asientos_contables')
            ->where('empresa_id', $empresaId)
            ->where('origen_modulo', 'impuestos')
            ->where('glosa', $glosa)
            ->where('estado', 'MAYORIZADO')
            ->exists();

        if (!$centralizado) {
            return;
        }

        DB::table('f29_desactualizaciones')->updateOrInsert(
            ['empresa_id' => $empresaId, 'mes' => $mes, 'anio' => $anio],
            ['motivo' => $motivo, 'detectado_en' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** Estado de desactualización de un período puntual (para exponer junto a simularF29). */
    public function estado(int $empresaId, int $mes, int $anio): array
    {
        $registro = DB::table('f29_desactualizaciones')
            ->where('empresa_id', $empresaId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->first();

        return [
            'desactualizado' => (bool) $registro,
            'motivo' => $registro->motivo ?? null,
            'detectado_en' => $registro->detectado_en ?? null,
        ];
    }
}
