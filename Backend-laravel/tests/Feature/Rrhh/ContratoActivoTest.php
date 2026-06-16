<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Verifica que contratoActivo() filtra correctamente por vigencia de fecha_termino.
 */
class ContratoActivoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearEmpleado(int $empresaId): Empleado
    {
        return Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.' . rand(100, 999) . '.' . rand(100, 999) . '-' . rand(0, 9),
            'nombres' => 'Test',
            'apellido_paterno' => 'Empleado',
            'afp' => 'Habitat',
            'tipo_salud' => 'FONASA',
        ]);
    }

    public function test_contrato_plazo_fijo_vencido_no_aparece_como_activo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'PLAZO_FIJO',
            'fecha_inicio' => now()->subMonths(3)->toDateString(),
            'fecha_termino' => now()->subDay()->toDateString(),
            'sueldo_base' => 700000,
            'es_contrato_activo' => true,
        ]);

        $resultado = $empleado->contratoActivo()->get();

        $this->assertCount(0, $resultado, 'Un contrato con fecha_termino en el pasado no debe aparecer como activo.');
    }

    public function test_contrato_indefinido_sin_fecha_termino_sigue_activo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $contrato = Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => now()->subYear()->toDateString(),
            'fecha_termino' => null,
            'sueldo_base' => 800000,
            'es_contrato_activo' => true,
        ]);

        $resultado = $empleado->contratoActivo()->get();

        $this->assertCount(1, $resultado);
        $this->assertEquals($contrato->id, $resultado->first()->id);
    }

    public function test_contrato_plazo_fijo_vigente_aparece_como_activo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $contrato = Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'PLAZO_FIJO',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_termino' => now()->addDay()->toDateString(),
            'sueldo_base' => 750000,
            'es_contrato_activo' => true,
        ]);

        $resultado = $empleado->contratoActivo()->get();

        $this->assertCount(1, $resultado);
        $this->assertEquals($contrato->id, $resultado->first()->id);
    }
}
