<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Finiquito;
use App\Domains\Rrhh\Models\Liquidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class LiquidacionSoftDeleteTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_liquidacion_soft_delete_no_borra_fisicamente(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut'        => '11.111.111-1',
            'nombres'    => 'Test',
            'apellido_paterno' => 'Softdelete',
        ]);

        $contrato = Contrato::create([
            'empresa_id'       => $empresa->id,
            'empleado_id'      => $empleado->id,
            'tipo'             => 'INDEFINIDO',
            'fecha_inicio'     => '2024-01-01',
            'sueldo_base'      => 500000,
            'estado'           => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $liquidacion = Liquidacion::create([
            'empresa_id'  => $empresa->id,
            'empleado_id' => $empleado->id,
            'contrato_id' => $contrato->id,
            'anio'        => 2026,
            'mes'         => 6,
            'estado'      => Liquidacion::ESTADO_EMITIDA,
        ]);

        $id = $liquidacion->id;

        $liquidacion->delete();

        $this->assertNull(Liquidacion::find($id));
        $this->assertNotNull(Liquidacion::withTrashed()->find($id));
        $this->assertNotNull(Liquidacion::withTrashed()->find($id)->deleted_at);
        $this->assertDatabaseHas('liquidaciones', ['id' => $id]);
    }

    public function test_finiquito_soft_delete_no_borra_fisicamente(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut'        => '22.222.222-2',
            'nombres'    => 'Test',
            'apellido_paterno' => 'Softdelete',
        ]);

        $contrato = Contrato::create([
            'empresa_id'       => $empresa->id,
            'empleado_id'      => $empleado->id,
            'tipo'             => 'INDEFINIDO',
            'fecha_inicio'     => '2023-01-01',
            'sueldo_base'      => 600000,
            'estado'           => 'TERMINADO',
            'es_contrato_activo' => false,
        ]);

        $finiquito = Finiquito::create([
            'empresa_id'                     => $empresa->id,
            'empleado_id'                    => $empleado->id,
            'contrato_id'                    => $contrato->id,
            'causal'                         => 'NECESIDADES_EMPRESA',
            'fecha_termino'                  => '2026-06-15',
            'fecha_inicio_contrato'          => '2023-01-01',
            'ultima_remuneracion_mensual'    => 600000,
            'promedio_remuneraciones_variables' => 0,
            'remuneracion_base_calculo'      => 600000,
            'anios_servicio'                 => 3,
            'meses_fraccion'                 => 5,
            'fraccion_cuenta_como_anio'      => false,
            'anios_calculo'                  => 3,
            'monto_indemnizacion_anos'       => 1800000,
            'tiene_aviso_previo'             => false,
            'monto_aviso_previo'             => 0,
            'dias_vacaciones_proporcionales' => 10,
            'monto_vacaciones_proporcionales' => 200000,
            'haberes_pendientes'             => 0,
            'descuentos_pendientes'          => 0,
            'total_bruto'                    => 2000000,
            'total_neto'                     => 2000000,
            'estado'                         => 'EMITIDO',
        ]);

        $id = $finiquito->id;

        $finiquito->delete();

        $this->assertNull(Finiquito::find($id));
        $this->assertNotNull(Finiquito::withTrashed()->find($id));
        $this->assertNotNull(Finiquito::withTrashed()->find($id)->deleted_at);
        $this->assertDatabaseHas('finiquitos', ['id' => $id]);
    }

    public function test_liquidacion_borrador_eliminado_con_force_delete_no_queda_en_bd(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut'        => '33.333.333-3',
            'nombres'    => 'Borrador',
            'apellido_paterno' => 'Forzado',
        ]);

        $contrato = Contrato::create([
            'empresa_id'       => $empresa->id,
            'empleado_id'      => $empleado->id,
            'tipo'             => 'INDEFINIDO',
            'fecha_inicio'     => '2024-01-01',
            'sueldo_base'      => 400000,
            'estado'           => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $borrador = Liquidacion::create([
            'empresa_id'  => $empresa->id,
            'empleado_id' => $empleado->id,
            'contrato_id' => $contrato->id,
            'anio'        => 2026,
            'mes'         => 5,
            'estado'      => Liquidacion::ESTADO_BORRADOR,
        ]);

        $id = $borrador->id;

        Liquidacion::where('empresa_id', $empresa->id)
            ->where('empleado_id', $empleado->id)
            ->where('estado', Liquidacion::ESTADO_BORRADOR)
            ->forceDelete();

        $this->assertNull(Liquidacion::withTrashed()->find($id));
        $this->assertDatabaseMissing('liquidaciones', ['id' => $id]);
    }
}
