<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\HaberDescuentoContrato;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Valida que el motor de liquidación respete los topes del Art. 58 Código del Trabajo:
 *   - Descuentos voluntarios ≤ 15% del total imponible
 *   - Descuentos totales ≤ 45% del total de haberes (imponibles + no imponibles)
 */
class LiquidacionTopeArt58Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private LiquidacionService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->servicio = app(LiquidacionService::class);
        $this->cargarParametrosLegales();
    }

    private function cargarParametrosLegales(): void
    {
        ParametroPrevisional::create([
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'afp_cotizacion_pct' => 10.0,
            'afp_comisiones_json' => ['Habitat' => 1.27, 'Modelo' => 0.58, 'Capital' => 1.44],
            'afp_sis_pct' => 1.62,
            'tope_imponible_uf' => 90.0,
            'salud_fonasa_pct' => 7.0,
            'afc_indefinido_trabajador_pct' => 0.6,
            'afc_indefinido_empleador_pct' => 2.4,
            'afc_plazo_fijo_trabajador_pct' => 0.0,
            'afc_plazo_fijo_empleador_pct' => 3.0,
            'afc_tope_imponible_uf' => 135.2,
            'imm' => 539000,
            'gratificacion_tope_mensual_factor' => 4.75,
            'cotizacion_adicional_empleador_pct' => 1.0,
            'mutual_cotizacion_basica_pct' => 0.9,
            'asignacion_familiar_tramos_json' => [
                ['hasta_pesos' => 620251, 'monto_por_carga' => 22007],
                ['hasta_pesos' => null, 'monto_por_carga' => 0],
            ],
            'fuente' => 'test',
        ]);

        IndicadorMensual::create([
            'anio' => 2026, 'mes' => 6,
            'uf_valor' => 39850, 'utm_valor' => 71506, 'uta_valor' => 71506 * 12,
        ]);

        $tramos = [
            [1, 0.0,   13.5,  0.000, 0.00],
            [2, 13.5,  30.0,  0.040, 0.54],
            [3, 30.0,  50.0,  0.080, 1.74],
            [4, 50.0,  70.0,  0.135, 4.49],
            [5, 70.0,  90.0,  0.230, 11.14],
            [6, 90.0,  120.0, 0.304, 17.80],
            [7, 120.0, 310.0, 0.350, 23.32],
            [8, 310.0, null,  0.400, 38.82],
        ];
        foreach ($tramos as [$o, $d, $h, $t, $f]) {
            TablaImpuestoUnico::create([
                'anio' => 2026, 'orden' => $o, 'desde_utm' => $d, 'hasta_utm' => $h,
                'tasa' => $t, 'factor_deduccion_utm' => $f,
            ]);
        }
    }

    private function empleadoConContrato(int $empresaId, float $sueldoBase): Empleado
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.345.678-' . rand(0, 9),
            'nombres' => 'Trabajador',
            'apellido_paterno' => 'Test',
            'afp' => 'Habitat',
            'tipo_salud' => 'FONASA',
        ]);

        Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => $sueldoBase,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        return $empleado->fresh();
    }

    /**
     * Art. 58 CT — tope descuentos voluntarios: no puede superar el 15% del imponible.
     *
     * Sueldo 1.000.000 → imponible ≈ 1.213.354 (con gratificación).
     * Tope voluntarios = 15% × 1.213.354 = 182.003.
     * Descuento voluntario configurado = 200.000 → debe lanzar excepción.
     */
    public function test_lanza_excepcion_cuando_descuentos_voluntarios_superan_15_pct_del_imponible(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1_000_000);
        $contrato = $empleado->contratoActivo->first();

        HaberDescuentoContrato::create([
            'empresa_id' => $empresa->id,
            'contrato_id' => $contrato->id,
            'nombre' => 'Préstamo empresa',
            'tipo' => 'DESCUENTO_VOLUNTARIO',
            'modalidad_valor' => 'MONTO_FIJO',
            'monto' => 200_000,
            'activo' => true,
            'vigente_desde' => '2026-01-01',
        ]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('Art. 58 CT');

        $this->servicio->calcular($empresa->id, $empleado->id, 2026, 6);
    }

    /**
     * Art. 58 CT — tope descuentos TOTALES: no puede superar el 45% del total de haberes.
     *
     * Este test valida el segundo check del Art. 58 CT de forma aislada.
     * El descuento voluntario es 0 (pasa el primer check sin problema).
     * Los descuentos LEGALES solos superan el 45% gracias a una cotización AFP artificialmente
     * alta (40%) configurada en un parámetro previsional específico para este test
     * (vigente_desde='2026-05-01', que gana por orderByDesc sobre el de '2026-01-01').
     *
     * Matemática verificada:
     *   Sueldo base: 1.000.000
     *   Gratificación Art.50: min(250.000, 213.354) = 213.354
     *   Total imponible: 1.213.354
     *   AFP cotización (40%): ~485.342
     *   AFP comisión Habitat (1,27%): ~15.410
     *   Salud FONASA (7%): ~84.935
     *   AFC indefinido trabajador (0,6%): ~7.280
     *   Impuesto único: 0 (base tributable ~620.387 → tramo 0%)
     *   Total descuentos legales: ~592.967
     *   Descuentos voluntarios: 0 → check 15% pasa (0 ≤ 182.003)
     *   Tope 45% de 1.213.354: ~546.009
     *   592.967 > 546.009 → excepción Art. 58 CT (segundo check)
     */
    public function test_lanza_excepcion_cuando_descuentos_totales_superan_45_pct_de_haberes(): void
    {
        // Parámetro con AFP=40% para este test; vigente_desde más reciente tiene prioridad.
        // Nota: se usa '2026-05-01' (no '2026-06-01') para evitar la comparación de igualdad
        // exacta con el período, que falla en SQLite al almacenar fechas como
        // '2026-06-01 00:00:00' y comparar con '2026-06-01' (la cadena más larga no es ≤).
        ParametroPrevisional::create([
            'vigente_desde' => '2026-05-01',
            'vigente_hasta' => null,
            'afp_cotizacion_pct' => 40.0,
            'afp_comisiones_json' => ['Habitat' => 1.27, 'Modelo' => 0.58, 'Capital' => 1.44],
            'afp_sis_pct' => 1.62,
            'tope_imponible_uf' => 90.0,
            'salud_fonasa_pct' => 7.0,
            'afc_indefinido_trabajador_pct' => 0.6,
            'afc_indefinido_empleador_pct' => 2.4,
            'afc_plazo_fijo_trabajador_pct' => 0.0,
            'afc_plazo_fijo_empleador_pct' => 3.0,
            'afc_tope_imponible_uf' => 135.2,
            'imm' => 539000,
            'gratificacion_tope_mensual_factor' => 4.75,
            'cotizacion_adicional_empleador_pct' => 1.0,
            'mutual_cotizacion_basica_pct' => 0.9,
            'asignacion_familiar_tramos_json' => [
                ['hasta_pesos' => 620251, 'monto_por_carga' => 22007],
                ['hasta_pesos' => null, 'monto_por_carga' => 0],
            ],
            'fuente' => 'test-art58-check2',
        ]);

        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1_000_000);

        // Sin descuentos voluntarios: el primer check del Art. 58 CT pasa sin problema.
        // Son solo los descuentos legales (AFP 40%) los que superan el 45% de los haberes.

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('Art. 58 CT');

        $this->servicio->calcular($empresa->id, $empleado->id, 2026, 6);
    }
}
