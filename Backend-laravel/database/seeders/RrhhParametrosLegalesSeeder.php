<?php

namespace Database\Seeders;

use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use Illuminate\Database\Seeder;

/**
 * Carga inicial de la parametrización legal del módulo de remuneraciones (R2).
 *
 * IMPORTANTE: estos valores corresponden a la referencia 2026 documentada en
 * docs/rrhh-leyes/MARCO-LEGAL-LABORAL-CHILE.md. Cambian periódicamente.
 * Antes de producción, verificar cada valor contra la fuente oficial
 * (Superintendencia de Pensiones, SII, Previred, Dirección del Trabajo).
 */
class RrhhParametrosLegalesSeeder extends Seeder
{
    public function run(): void
    {
        $this->conceptosRemuneracion();
        $this->parametrosPrevisionales();
        $this->tablaImpuestoUnico();
        $this->indicadoresMensuales();
    }

    private function conceptosRemuneracion(): void
    {
        // Conceptos del sistema (es_sistema = true): los usa el motor de liquidación.
        $conceptos = [
            // Haberes imponibles
            [ConceptoRemuneracion::SUELDO_BASE, 'Sueldo Base', 'HABER_IMPONIBLE', 'MONTO_FIJO', 10],
            [ConceptoRemuneracion::GRATIFICACION, 'Gratificación Legal Art.50', 'HABER_IMPONIBLE', 'FORMULA_GRATIFICACION', 20],
            [ConceptoRemuneracion::HORAS_EXTRA, 'Horas Extraordinarias', 'HABER_IMPONIBLE', 'FORMULA_HORAS_EXTRA', 30],
            // Haberes no imponibles
            [ConceptoRemuneracion::COLACION, 'Colación', 'HABER_NO_IMPONIBLE', 'MONTO_FIJO', 100],
            [ConceptoRemuneracion::MOVILIZACION, 'Movilización', 'HABER_NO_IMPONIBLE', 'MONTO_FIJO', 110],
            [ConceptoRemuneracion::ASIGNACION_FAMILIAR, 'Asignación Familiar', 'HABER_NO_IMPONIBLE', 'MANUAL', 120],
            // Descuentos legales
            [ConceptoRemuneracion::AFP_COTIZACION, 'AFP Cotización Obligatoria', 'DESCUENTO_LEGAL', 'PORCENTAJE_IMPONIBLE', 200],
            [ConceptoRemuneracion::AFP_COMISION, 'AFP Comisión', 'DESCUENTO_LEGAL', 'PORCENTAJE_IMPONIBLE', 210],
            [ConceptoRemuneracion::SALUD, 'Salud (Fonasa/Isapre)', 'DESCUENTO_LEGAL', 'PORCENTAJE_IMPONIBLE', 220],
            [ConceptoRemuneracion::AFC_TRABAJADOR, 'Seguro de Cesantía', 'DESCUENTO_LEGAL', 'PORCENTAJE_IMPONIBLE', 230],
            [ConceptoRemuneracion::IMPUESTO_UNICO, 'Impuesto Único 2ª Categoría', 'DESCUENTO_LEGAL', 'TABLA_IMPUESTO', 240],
        ];

        foreach ($conceptos as [$codigo, $nombre, $tipo, $regla, $orden]) {
            ConceptoRemuneracion::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'empresa_id' => null,
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                    'regla_calculo' => $regla,
                    'es_sistema' => true,
                    'activo' => true,
                    'orden' => $orden,
                ],
            );
        }
    }

    private function parametrosPrevisionales(): void
    {
        // Referencia 2026 (ver docs/rrhh-leyes/MARCO-LEGAL-LABORAL-CHILE.md).
        ParametroPrevisional::updateOrCreate(
            ['vigente_desde' => '2026-01-01'],
            [
                'vigente_hasta' => null,
                'afp_cotizacion_pct' => 10.0000,
                // Comisiones AFP vigentes junio 2026
                'afp_comisiones_json' => [
                    'Uno' => 0.46,
                    'Modelo' => 0.58,
                    'PlanVital' => 1.16,
                    'Habitat' => 1.27,
                    'Capital' => 1.44,
                    'Cuprum' => 1.44,
                    'ProVida' => 1.45,
                ],
                'afp_sis_pct' => 1.6200,             // 1.62% desde abril 2026 (cargo empleador)
                'tope_imponible_uf' => 90.0000,      // 90 UF (2026)
                'salud_fonasa_pct' => 7.0000,
                'afc_indefinido_trabajador_pct' => 0.6000,
                'afc_indefinido_empleador_pct' => 2.4000,
                'afc_plazo_fijo_trabajador_pct' => 0.0000,
                'afc_plazo_fijo_empleador_pct' => 3.0000,
                'afc_tope_imponible_uf' => 135.2000, // 135.2 UF (2026)
                'imm' => 539000.00,                  // IMM 2026 Ley 21.751
                'gratificacion_tope_mensual_factor' => 4.7500,
                'cotizacion_adicional_empleador_pct' => 1.0000, // Ley 21.735 (ago2025-jul2026)
                'mutual_cotizacion_basica_pct' => 0.9000,
                'asignacion_familiar_tramos_json' => [
                    ['hasta_pesos' => 620251, 'monto_por_carga' => 22007],
                    ['hasta_pesos' => 905941, 'monto_por_carga' => 13505],
                    ['hasta_pesos' => 1412957, 'monto_por_carga' => 4267],
                    ['hasta_pesos' => null, 'monto_por_carga' => 0],
                ],
                'fuente' => 'Referencia 2026: Superintendencia de Pensiones, AFC Chile, Mintrab Ley 21.751. Verificar antes de producción.',
            ],
        );
    }

    private function tablaImpuestoUnico(): void
    {
        // Tabla IUSC 2026 expresada en UTM (Art. 42-43 LIR). Fuente: SII.
        $tramos = [
            // [orden, desde_utm, hasta_utm, tasa, factor_deduccion_utm]
            [1, 0.0,   13.5,  0.000, 0.00],
            [2, 13.5,  30.0,  0.040, 0.54],
            [3, 30.0,  50.0,  0.080, 1.74],
            [4, 50.0,  70.0,  0.135, 4.49],
            [5, 70.0,  90.0,  0.230, 11.14],
            [6, 90.0,  120.0, 0.304, 17.80],
            [7, 120.0, 310.0, 0.350, 23.32],  // Fix 2: rebaja correcta (continuidad con tramo 6 en 120 UTM)
            [8, 310.0, null,  0.400, 38.82],  // Fix 2: rebaja correcta (continuidad con tramo 7 en 310 UTM)
        ];

        foreach ($tramos as [$orden, $desde, $hasta, $tasa, $factor]) {
            TablaImpuestoUnico::updateOrCreate(
                ['anio' => 2026, 'orden' => $orden],
                [
                    'desde_utm' => $desde,
                    'hasta_utm' => $hasta,
                    'tasa' => $tasa,
                    'factor_deduccion_utm' => $factor,
                    'fuente' => 'SII — Impuesto Único 2ª Categoría 2026',
                ],
            );
        }
    }

    private function indicadoresMensuales(): void
    {
        // Indicadores de ejemplo. En producción se cargan mes a mes desde CMF/SII/Previred.
        $indicadores = [
            [2026, 1, 39200.00, 68923.00],
            [2026, 2, 39400.00, 69265.00],
            [2026, 3, 39600.00, 69604.00],
            [2026, 4, 39700.00, 69945.00],
            [2026, 5, 39800.00, 71161.00],
            [2026, 6, 39850.00, 71506.00],
        ];

        foreach ($indicadores as [$anio, $mes, $uf, $utm]) {
            IndicadorMensual::updateOrCreate(
                ['anio' => $anio, 'mes' => $mes],
                [
                    'uf_valor' => $uf,
                    'utm_valor' => $utm,
                    'uta_valor' => $utm * 12,
                    'fuente' => 'Referencia 2026 — verificar en CMF (UF) y SII (UTM)',
                ],
            );
        }
    }
}
