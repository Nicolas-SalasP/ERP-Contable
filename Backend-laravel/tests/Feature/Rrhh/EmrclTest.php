<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EmrclTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // ------------------------------------------------------------------
    // Helpers de setup
    // ------------------------------------------------------------------

    private function crearLiquidacion(
        int $empresaId,
        string $rut,
        string $sexo = 'M',
        array $overrides = [],
        array $detalles = [],
    ): Liquidacion {
        $empleado = Empleado::create([
            'empresa_id'            => $empresaId,
            'rut'                   => $rut,
            'nombres'               => 'Test',
            'apellido_paterno'      => 'Worker',
            'sexo'                  => $sexo,
            'afp'                   => 'Capital',
            'tipo_salud'            => 'FONASA',
            'estado'                => 'ACTIVO',
            'fecha_ingreso_empresa' => '2024-01-01',
        ]);

        $contrato = Contrato::create([
            'empresa_id'         => $empresaId,
            'empleado_id'        => $empleado->id,
            'tipo'               => 'INDEFINIDO',
            'tipo_jornada'       => 'COMPLETA',
            'fecha_inicio'       => '2024-01-01',
            'sueldo_base'        => 800000,
            'horas_semana'       => 45,
            'estado'             => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $liq = Liquidacion::create(array_merge([
            'empresa_id'                    => $empresaId,
            'empleado_id'                   => $empleado->id,
            'contrato_id'                   => $contrato->id,
            'anio'                          => 2026,
            'mes'                           => 6,
            'estado'                        => Liquidacion::ESTADO_EMITIDA,
            'total_haberes'                 => 900000,
            'total_haberes_imponibles'      => 850000,
            'total_haberes_no_imponibles'   => 50000,
            'total_descuentos_legales'      => 180000,
            'total_descuentos'              => 180000,
            'liquido_a_pagar'               => 720000,
            'salud_legal'                   => 59500,
            'salud_adicional'               => 0,
            'aporte_empleador_afc'          => 7200,
            'aporte_empleador_sis'          => 4500,
            'aporte_empleador_mutual'       => 8500,
            'dias_trabajados'               => 30,
            'dias_licencia_medica'          => 0,
            'dias_vacaciones'               => 0,
            'base_imponible'                => 850000,
            'base_tributable'               => 850000,
            'total_descuentos_voluntarios'  => 0,
            'aporte_empleador_reforma'      => 0,
        ], $overrides));

        foreach ($detalles as $detalle) {
            LiquidacionDetalle::create(array_merge([
                'empresa_id'     => $empresaId,
                'liquidacion_id' => $liq->id,
                'orden'          => 1,
            ], $detalle));
        }

        return $liq;
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_cuadro1_cuenta_trabajadores_por_sexo(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $this->crearLiquidacion($empresa->id, '11111111-1', 'F');
        $this->crearLiquidacion($empresa->id, '22222222-2', 'F');
        $this->crearLiquidacion($empresa->id, '33333333-3', 'M');

        $response = $this->actingAs($admin)
            ->getJson('/api/rrhh/emrcl/2026/6');

        $response->assertOk();
        $this->assertEquals(2, $response->json('cuadro1_trabajadores.mujeres'));
        $this->assertEquals(1, $response->json('cuadro1_trabajadores.hombres'));
        $this->assertEquals(3, $response->json('cuadro1_trabajadores.total'));
    }

    public function test_cuadro2_suma_remuneraciones_brutas_por_sexo(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $this->crearLiquidacion($empresa->id, '11111111-1', 'F', [
            'total_haberes'           => 1000000,
            'aporte_empleador_afc'    => 8000,
            'aporte_empleador_sis'    => 5000,
            'aporte_empleador_mutual' => 9000,
        ]);
        $this->crearLiquidacion($empresa->id, '22222222-2', 'M', [
            'total_haberes'           => 800000,
            'aporte_empleador_afc'    => 6400,
            'aporte_empleador_sis'    => 4000,
            'aporte_empleador_mutual' => 7200,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/rrhh/emrcl/2026/6');

        $response->assertOk();
        $this->assertEquals(1000000, $response->json('cuadro2_remuneraciones.mujeres.remuneraciones_ordinarias'));
        $this->assertEquals(22000,   $response->json('cuadro2_remuneraciones.mujeres.aportes_patronales'));
        $this->assertEquals(800000,  $response->json('cuadro2_remuneraciones.hombres.remuneraciones_ordinarias'));
        $this->assertEquals(17600,   $response->json('cuadro2_remuneraciones.hombres.aportes_patronales'));
    }

    public function test_cuadro3_suma_deducciones_legales_por_sexo(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $this->crearLiquidacion($empresa->id, '11111111-1', 'F',
            ['salud_legal' => 59500],
            [
                ['codigo_concepto' => 'AFP_COTIZACION', 'tipo' => 'DESCUENTO_LEGAL', 'nombre_concepto' => 'AFP Cotización', 'monto' => 85000],
                ['codigo_concepto' => 'AFP_COMISION',   'tipo' => 'DESCUENTO_LEGAL', 'nombre_concepto' => 'AFP Comisión',   'monto' => 4250],
                ['codigo_concepto' => 'AFC_TRABAJADOR', 'tipo' => 'DESCUENTO_LEGAL', 'nombre_concepto' => 'AFC Trabajador', 'monto' => 6800],
                ['codigo_concepto' => 'IMPUESTO_UNICO', 'tipo' => 'DESCUENTO_LEGAL', 'nombre_concepto' => 'Impuesto Único', 'monto' => 0],
            ]
        );

        $response = $this->actingAs($admin)
            ->getJson('/api/rrhh/emrcl/2026/6');

        $response->assertOk();
        $this->assertEquals(89250, $response->json('cuadro3_deducciones.mujeres.afp'));
        $this->assertEquals(59500, $response->json('cuadro3_deducciones.mujeres.salud'));
        $this->assertEquals(6800,  $response->json('cuadro3_deducciones.mujeres.afc'));
        $this->assertEquals(0,     $response->json('cuadro3_deducciones.mujeres.impuesto'));
    }

    public function test_excluye_liquidaciones_en_estado_borrador(): void
    {
        // Las liquidaciones BORRADOR no deben incluirse en el reporte.
        // Los finiquitos son modelos separados (Finiquito) y nunca aparecen aquí.
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $this->crearLiquidacion($empresa->id, '11111111-1', 'M', ['total_haberes' => 900000]);
        $this->crearLiquidacion($empresa->id, '22222222-2', 'M', [
            'total_haberes' => 500000,
            'estado'        => 'BORRADOR',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/rrhh/emrcl/2026/6');

        $response->assertOk();
        $this->assertEquals(900000, $response->json('cuadro2_remuneraciones.hombres.remuneraciones_ordinarias'));
        $this->assertEquals(1,      $response->json('cuadro1_trabajadores.hombres'));
    }

    public function test_lanza_error_sin_liquidaciones_del_periodo(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/api/rrhh/emrcl/2026/6');

        $response->assertStatus(422);
        $response->assertJsonFragment(['mensaje' => 'No hay liquidaciones emitidas para el período 6/2026.']);
    }

    public function test_solo_incluye_datos_de_la_empresa_del_usuario(): void
    {
        [$empresa1, $admin1] = $this->crearEmpresaConAdmin();
        [$empresa2]          = $this->crearEmpresaConAdmin();

        $this->crearLiquidacion($empresa1->id, '11111111-1', 'M', ['total_haberes' => 900000]);
        $this->crearLiquidacion($empresa2->id, '22222222-2', 'M', ['total_haberes' => 500000]);

        $response = $this->actingAs($admin1)
            ->getJson('/api/rrhh/emrcl/2026/6');

        $response->assertOk();
        $this->assertEquals(1,      $response->json('cuadro1_trabajadores.total'));
        $this->assertEquals(900000, $response->json('cuadro2_remuneraciones.hombres.remuneraciones_ordinarias'));
    }
}
