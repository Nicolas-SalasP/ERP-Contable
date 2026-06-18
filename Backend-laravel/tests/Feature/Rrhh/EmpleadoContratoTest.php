<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Services\ContratoService;
use App\Domains\Rrhh\Services\EmpleadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R1 — Personal: ficha de empleado, contratos con histórico y datos bancarios
 * cifrados (Ley 21.719).
 */
class EmpleadoContratoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private EmpleadoService $empleados;
    private ContratoService $contratos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empleados = app(EmpleadoService::class);
        $this->contratos = app(ContratoService::class);
    }

    public function test_crea_empleado_con_datos_bancarios_cifrados(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut' => '12.345.678-9',
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'Soto',
            'afp' => 'Habitat',
            'tipo_salud' => 'FONASA',
            'banco_nombre' => 'Banco Estado',
            'banco_tipo_cuenta' => 'CUENTA_VISTA',
            'banco_numero_cuenta' => '00012345678',
        ]);

        $this->assertEquals('Juan Carlos Pérez Soto', $empleado->nombre_completo);

        // El número de cuenta se guardó cifrado, no en claro
        $crudo = $empleado->getAttributes()['banco_numero_cuenta_cifrado'];
        $this->assertNotEquals('00012345678', $crudo);
        $this->assertEquals('00012345678', Crypt::decryptString($crudo));

        // Y es accesible vía accessor
        $this->assertEquals('00012345678', $empleado->banco_numero_cuenta);

        // No aparece en la serialización JSON (dato sensible)
        $this->assertArrayNotHasKey('banco_numero_cuenta_cifrado', $empleado->toArray());
    }

    public function test_no_permite_rut_duplicado_en_misma_empresa(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->empleados->crear($empresa->id, [
            'rut' => '11.111.111-1',
            'nombres' => 'Ana',
            'apellido_paterno' => 'González',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ya existe un empleado con el RUT');

        $this->empleados->crear($empresa->id, [
            'rut' => '11.111.111-1',
            'nombres' => 'Otra Ana',
            'apellido_paterno' => 'Pérez',
        ]);
    }

    public function test_crear_segundo_contrato_desactiva_el_anterior(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleados->crear($empresa->id, [
            'rut' => '22.222.222-2',
            'nombres' => 'Pedro',
            'apellido_paterno' => 'Ramírez',
        ]);

        $c1 = $this->contratos->crear($empresa->id, $empleado->id, [
            'tipo' => 'PLAZO_FIJO',
            'fecha_inicio' => '2025-01-01',
            'fecha_termino' => '2025-12-31',
            'sueldo_base' => 800000,
        ]);

        $c2 = $this->contratos->crear($empresa->id, $empleado->id, [
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2026-01-01',
            'sueldo_base' => 950000,
        ]);

        $this->assertFalse(Contrato::find($c1->id)->es_contrato_activo);
        $this->assertTrue(Contrato::find($c2->id)->es_contrato_activo);

        // El histórico conserva ambos contratos
        $this->assertCount(2, $this->contratos->listarPorEmpleado($empresa->id, $empleado->id));
    }

    public function test_aislamiento_multitenant_empleados(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin([], ['email' => 'a@test.cl']);
        [$empresaB] = $this->crearEmpresaConAdmin([], ['email' => 'b@test.cl']);

        $this->empleados->crear($empresaA->id, [
            'rut' => '33.333.333-3', 'nombres' => 'Empleado', 'apellido_paterno' => 'A',
        ]);
        $this->empleados->crear($empresaB->id, [
            'rut' => '44.444.444-4', 'nombres' => 'Empleado', 'apellido_paterno' => 'B',
        ]);

        // El scope global filtra: contando sin auth deberían verse 2, pero por empresa 1 c/u
        $totalA = Empleado::where('empresa_id', $empresaA->id)->count();
        $totalB = Empleado::where('empresa_id', $empresaB->id)->count();
        $this->assertEquals(1, $totalA);
        $this->assertEquals(1, $totalB);
    }

    public function test_modelo_empleado_tiene_empresa_scope(): void
    {
        $empleado = new Empleado();
        $this->assertArrayHasKey(
            \App\Domains\Core\Scopes\EmpresaScope::class,
            $empleado->getGlobalScopes(),
            'Empleado debe tener EmpresaScope (dato sensible Ley 21.719).'
        );
    }
}
