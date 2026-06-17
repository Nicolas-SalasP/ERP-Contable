<?php

namespace App\Domains\Contabilidad\Services\Propyme;

use Illuminate\Support\Facades\DB;

class ElegibilidadPropymeService
{
    private const LIMITE_UF = 75_000;
    // ~75.000 UF en pesos (UF promedio ~$38.000 en 2026)
    private const LIMITE_PESOS_APROXIMADO = 2_850_000_000;

    private const ESTADOS_EMITIDOS = [
        'FIRMADO', 'ENVIADO_SII', 'EN_PROCESO_SII', 'ACEPTADO', 'ACEPTADO_CON_REPAROS',
    ];
    private const TIPOS_VENTA = [33, 34, 39, 41, 56, 110, 111];
    private const TIPOS_NC    = [61, 112];

    public function verificar(int $empresaId, int $anioBase): array
    {
        $limiteEnPesos   = $this->limiteEnPesosParaAnio($anioBase);
        $ingresosPorAnio = [];

        for ($i = 1; $i <= 3; $i++) {
            $anio = $anioBase - $i;

            $ventas = (float) DB::table('sii_dte_emitido')
                ->where('empresa_id', $empresaId)
                ->whereYear('fecha_emision', $anio)
                ->whereIn('estado', self::ESTADOS_EMITIDOS)
                ->whereIn('tipo_dte', self::TIPOS_VENTA)
                ->sum('monto_neto');

            $nc = (float) DB::table('sii_dte_emitido')
                ->where('empresa_id', $empresaId)
                ->whereYear('fecha_emision', $anio)
                ->whereIn('estado', self::ESTADOS_EMITIDOS)
                ->whereIn('tipo_dte', self::TIPOS_NC)
                ->sum('monto_neto');

            $ingresosPorAnio[$anio] = $ventas - $nc;
        }

        $promedioPesos = array_sum($ingresosPorAnio) / 3;
        $elegible      = $promedioPesos <= $limiteEnPesos;

        return [
            'elegible'          => $elegible,
            'promedio_pesos'    => (int) round($promedioPesos),
            'limite_pesos'      => $limiteEnPesos,
            'limite_uf'         => self::LIMITE_UF,
            'ingresos_por_anio' => $ingresosPorAnio,
            'mensaje'           => $elegible
                ? 'La empresa cumple el límite de ingresos para el régimen Propyme Transparente.'
                : 'Advertencia: los ingresos promedio de los últimos 3 años superan el límite de 75.000 UF. La empresa podría no ser elegible para continuar en el régimen Propyme Transparente.',
        ];
    }

    private function limiteEnPesosParaAnio(int $anio): int
    {
        $uf = DB::table('indicadores_mensuales')
            ->where('anio', $anio)
            ->whereNotNull('uf_valor')
            ->orderByDesc('mes')
            ->value('uf_valor');

        if ($uf === null) {
            return self::LIMITE_PESOS_APROXIMADO;
        }

        return (int) round($uf * self::LIMITE_UF);
    }
}
