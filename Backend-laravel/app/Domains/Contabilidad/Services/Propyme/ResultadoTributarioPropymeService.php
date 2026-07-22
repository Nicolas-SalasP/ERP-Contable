<?php

namespace App\Domains\Contabilidad\Services\Propyme;

use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Models\TasaPpmPropyme;
use App\Domains\Core\Models\Propietario;
use Illuminate\Support\Facades\DB;

class ResultadoTributarioPropymeService
{
    // Tipos de documento de venta (SiiDteEmitido constants)
    private const TIPOS_VENTA = [33, 34, 39, 41, 56, 110, 111];
    private const TIPOS_NC    = [61, 112];
    private const ESTADOS_EMITIDOS = [
        'FIRMADO', 'ENVIADO_SII', 'EN_PROCESO_SII', 'ACEPTADO', 'ACEPTADO_CON_REPAROS',
    ];

    public function calcular(int $empresaId, int $anio): array
    {
        $empresa = DB::table('empresas')->where('id', $empresaId)->first();

        if (! $empresa || $empresa->regimen_tributario !== '14_D8') {
            throw DjException::regla(
                'El cálculo de resultado tributario Propyme solo aplica a empresas en régimen 14 D N°8 (Transparente).'
            );
        }

        // Ingresos percibidos: DTEs emitidos del año
        $ventasNeto = (float) DB::table('sii_dte_emitido')
            ->where('empresa_id', $empresaId)
            ->whereYear('fecha_emision', $anio)
            ->whereIn('estado', self::ESTADOS_EMITIDOS)
            ->whereIn('tipo_dte', self::TIPOS_VENTA)
            ->sum('monto_neto');

        $ncNeto = (float) DB::table('sii_dte_emitido')
            ->where('empresa_id', $empresaId)
            ->whereYear('fecha_emision', $anio)
            ->whereIn('estado', self::ESTADOS_EMITIDOS)
            ->whereIn('tipo_dte', self::TIPOS_NC)
            ->sum('monto_neto');

        $ingresosTotales = $ventasNeto - $ncNeto;

        // Gastos pagados: facturas de compras del año
        $gastosTotales = (float) DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->whereYear('fecha_emision', $anio)
            ->where('estado', '!=', 'ANULADA')
            ->sum('monto_neto');

        $baseImponible = $ingresosTotales - $gastosTotales;

        // PPM del año (tasa_base para cálculo anual DJ)
        $tasa = TasaPpmPropyme::porAnio($anio);
        $ppmTotal = (int) round($ingresosTotales * ($tasa->tasa_base_pct / 100));

        // Propietarios y distribución
        $propietarios = Propietario::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->get();

        if ($propietarios->isEmpty()) {
            throw DjException::regla('La empresa no tiene propietarios registrados. Registre al menos uno antes de calcular el resultado tributario.');
        }

        $sumaPct = (float) $propietarios->sum('porcentaje_participacion');
        if (abs($sumaPct - 100.0) > 0.01) {
            throw DjException::regla(
                "Los porcentajes de participación de los propietarios suman {$sumaPct}%, deben sumar exactamente 100%."
            );
        }

        $distribucion = $propietarios->map(function ($p) use ($baseImponible, $ppmTotal) {
            $factor = $p->porcentaje_participacion / 100;
            return [
                'propietario_id'       => $p->id,
                'rut'                  => $p->rut,
                'nombre'               => $p->nombre,
                'porcentaje'           => (float) $p->porcentaje_participacion,
                'base_atribuida'       => (int) round($baseImponible * $factor),
                'ppm_atribuido'        => (int) round($ppmTotal * $factor),
                'creditos'             => 0, // 14D N°8 está liberado de IDPC → crédito IDPC = 0
            ];
        })->values()->all();

        return [
            'empresa_id'       => $empresaId,
            'anio'             => $anio,
            'ingresos_totales' => (int) round($ingresosTotales),
            'gastos_totales'   => (int) round($gastosTotales),
            'base_imponible'   => (int) round($baseImponible),
            'tasa_ppm'         => (float) $tasa->tasa_base_pct,
            'ppm_total'        => $ppmTotal,
            'distribucion'     => $distribucion,
        ];
    }
}
