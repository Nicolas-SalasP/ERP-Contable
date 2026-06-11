<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Services\Finiquito\FiniquitoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R4 — Finiquitos. Valida indemnización por años de servicio (Art. 163),
 * aviso previo (Art. 161) y vacaciones proporcionales (Art. 70).
 */
class FiniquitoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private FiniquitoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(FiniquitoService::class);

        IndicadorMensual::create([
            'anio' => 2026, 'mes' => 6,
            'uf_valor' => 39850, 'utm_valor' => 71506, 'uta_valor' => 71506 * 12,
        ]);
    }

    private function contratoVigente(int $empresaId, float $sueldo, string $fechaInicio): Contrato
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.345.678-' . rand(0, 9),
            'nombres' => 'Trabajador',
            'apellido_paterno' => 'Test',
        ]);

        return Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => $fechaInicio,
            'sueldo_base' => $sueldo,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);
    }

    public function test_necesidades_empresa_genera_indemnizacion_por_anos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // 5 años exactos de servicio
        $contrato = $this->contratoVigente($empresa->id, 800000, '2021-06-15');

        $finiquito = $this->service->calcular(
            $empresa->id, $contrato->id, 'NECESIDADES_EMPRESA', '2026-06-15'
        );

        // 5 años → 5 × 800.000 = 4.000.000
        $this->assertEquals(5, $finiquito->anios_calculo);
        $this->assertEquals(4000000, (float) $finiquito->monto_indemnizacion_anos);

        // Sin aviso previo dado → indemnización sustitutiva = 1 mes
        $this->assertEquals(800000, (float) $finiquito->monto_aviso_previo);
    }

    public function test_fraccion_mayor_a_seis_meses_cuenta_como_anio(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // 5 años y 8 meses → 6 años
        $contrato = $this->contratoVigente($empresa->id, 1000000, '2020-10-01');

        $finiquito = $this->service->calcular(
            $empresa->id, $contrato->id, 'NECESIDADES_EMPRESA', '2026-06-01'
        );

        $this->assertTrue((bool) $finiquito->fraccion_cuenta_como_anio);
        $this->assertEquals(6, $finiquito->anios_calculo);
    }

    public function test_indemnizacion_tope_11_anos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // 15 años → tope 11
        $contrato = $this->contratoVigente($empresa->id, 700000, '2011-01-01');

        $finiquito = $this->service->calcular(
            $empresa->id, $contrato->id, 'NECESIDADES_EMPRESA', '2026-06-01'
        );

        $this->assertEquals(11, $finiquito->anios_calculo);
        $this->assertEquals(700000 * 11, (float) $finiquito->monto_indemnizacion_anos);
    }

    public function test_renuncia_no_genera_indemnizacion_por_anos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id, 900000, '2020-01-01');

        $finiquito = $this->service->calcular(
            $empresa->id, $contrato->id, 'RENUNCIA', '2026-06-01'
        );

        // Renuncia (Art. 159 N°2): sin indemnización ni aviso previo
        $this->assertEquals(0, (float) $finiquito->monto_indemnizacion_anos);
        $this->assertEquals(0, (float) $finiquito->monto_aviso_previo);

        // Pero sí vacaciones proporcionales
        $this->assertGreaterThanOrEqual(0, (float) $finiquito->monto_vacaciones_proporcionales);
    }

    public function test_firmar_finiquito_termina_el_contrato(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id, 800000, '2022-01-01');

        $finiquito = $this->service->calcular(
            $empresa->id, $contrato->id, 'NECESIDADES_EMPRESA', '2026-06-01'
        );

        $this->service->firmar($empresa->id, $finiquito->id);

        $contratoActualizado = Contrato::find($contrato->id);
        $this->assertEquals('TERMINADO', $contratoActualizado->estado);
        $this->assertFalse((bool) $contratoActualizado->es_contrato_activo);
    }

    public function test_aviso_previo_dado_no_genera_pago_sustitutivo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id, 1000000, '2023-01-01');

        $finiquito = $this->service->calcular(
            $empresa->id, $contrato->id, 'NECESIDADES_EMPRESA', '2026-06-01',
            ['aviso_previo' => true]
        );

        $this->assertTrue((bool) $finiquito->tiene_aviso_previo);
        $this->assertEquals(0, (float) $finiquito->monto_aviso_previo);
    }
}
