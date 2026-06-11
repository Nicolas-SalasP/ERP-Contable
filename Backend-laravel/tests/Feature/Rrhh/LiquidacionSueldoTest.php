<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R3 — Motor de liquidación. Valida los cálculos previsionales chilenos:
 * AFP 10% + comisión, salud 7%, AFC 0,6%, impuesto único por tabla, tope imponible.
 */
class LiquidacionSueldoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private LiquidacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(LiquidacionService::class);
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
            [1, 0.0, 13.5, 0.0, 0.0],
            [2, 13.5, 30.0, 0.04, 0.54],
            [3, 30.0, 50.0, 0.08, 1.74],
            [4, 50.0, 70.0, 0.135, 4.49],
            [5, 70.0, 90.0, 0.23, 11.14],
            [6, 90.0, 120.0, 0.304, 17.80],
            [7, 120.0, 310.0, 0.35, 23.80],
            [8, 310.0, null, 0.40, 39.30],
        ];
        foreach ($tramos as [$o, $d, $h, $t, $f]) {
            TablaImpuestoUnico::create([
                'anio' => 2026, 'orden' => $o, 'desde_utm' => $d, 'hasta_utm' => $h,
                'tasa' => $t, 'factor_deduccion_utm' => $f,
            ]);
        }
    }

    private function empleadoConContrato(int $empresaId, float $sueldoBase, string $tipo = 'INDEFINIDO', string $afp = 'Habitat'): Empleado
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.345.678-' . rand(0, 9),
            'nombres' => 'Trabajador',
            'apellido_paterno' => 'Test',
            'afp' => $afp,
            'tipo_salud' => 'FONASA',
        ]);

        Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => $tipo,
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => $sueldoBase,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        return $empleado->fresh();
    }

    public function test_calcula_descuentos_previsionales_basicos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // Imponible = sueldo base 1.000.000 + gratificación
        // Gratificación = min(25% × 1.000.000, 4.75 × 539000 / 12) = min(250000, 213354) = 213354
        $gratificacion = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::GRATIFICACION);
        $this->assertEquals(213354, (float) $gratificacion->monto);

        $imponible = 1000000 + 213354; // 1.213.354
        $this->assertEquals($imponible, (float) $liq->total_haberes_imponibles);

        // AFP 10% sobre imponible (bajo tope 90 UF = 3.586.500)
        $afp = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFP_COTIZACION);
        $this->assertEquals(round($imponible * 0.10), (float) $afp->monto);

        // AFP comisión Habitat 1.27%
        $comision = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFP_COMISION);
        $this->assertEquals(round($imponible * 0.0127), (float) $comision->monto);

        // Salud 7%
        $salud = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::SALUD);
        $this->assertEquals(round($imponible * 0.07), (float) $salud->monto);

        // AFC trabajador indefinido 0.6%
        $afc = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFC_TRABAJADOR);
        $this->assertEquals(round($imponible * 0.006), (float) $afc->monto);
    }

    public function test_aplica_tope_imponible_afp_90_uf(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Sueldo muy alto para superar el tope (90 UF × 39850 = 3.586.500)
        $empleado = $this->empleadoConContrato($empresa->id, 5000000, 'INDEFINIDO', 'Modelo');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $topeEsperado = round(90 * 39850); // 3.586.500
        $this->assertEquals($topeEsperado, (float) $liq->base_imponible);

        // AFP se calcula sobre el tope, no sobre el imponible real
        $afp = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFP_COTIZACION);
        $this->assertEquals(round($topeEsperado * 0.10), (float) $afp->monto);
    }

    public function test_contrato_plazo_fijo_no_descuenta_afc_trabajador(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 900000, 'PLAZO_FIJO', 'Modelo');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // Plazo fijo: 0% AFC trabajador → no aparece la línea
        $afc = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFC_TRABAJADOR);
        $this->assertNull($afc);

        // Pero el empleador sí aporta 3%
        $this->assertGreaterThan(0, (float) $liq->aporte_empleador_afc);
    }

    public function test_calcula_impuesto_unico_para_sueldo_alto(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 3000000, 'INDEFINIDO', 'Modelo');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // Con base tributable alta debe haber impuesto único
        $impuesto = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::IMPUESTO_UNICO);
        $this->assertNotNull($impuesto);
        $this->assertGreaterThan(0, (float) $impuesto->monto);
    }

    public function test_sueldo_minimo_no_paga_impuesto_unico(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 539000, 'INDEFINIDO', 'Modelo');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // Base tributable bajo 13.5 UTM → exento
        $impuesto = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::IMPUESTO_UNICO);
        $this->assertNull($impuesto);
    }

    public function test_liquido_a_pagar_es_haberes_menos_descuentos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $esperado = (float) $liq->total_haberes - (float) $liq->total_descuentos;
        $this->assertEquals($esperado, (float) $liq->liquido_a_pagar);
        $this->assertGreaterThan(0, (float) $liq->liquido_a_pagar);
    }

    public function test_recalcular_reemplaza_borrador_anterior(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
        $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // Solo debe quedar una liquidación para el período
        $count = Liquidacion::where('empresa_id', $empresa->id)
            ->where('empleado_id', $empleado->id)
            ->where('anio', 2026)->where('mes', 6)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_falla_sin_parametros_del_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('indicadores mensuales');

        // Período sin indicadores cargados
        $this->service->calcular($empresa->id, $empleado->id, 2027, 3);
    }
}
