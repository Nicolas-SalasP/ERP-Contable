<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Services\ArcoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Verifica que exportarEmpleado() incluya la clave 'accesos' con el historial
 * de lecturas PII registradas en la tabla auditorias (Ley 21.719).
 */
class ArcoAccesosTest extends TestCase
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
            'empresa_id'       => $empresaId,
            'rut'              => $this->rutAleatorio(),
            'nombres'          => 'María',
            'apellido_paterno' => 'González',
            'apellido_materno' => 'Torres',
            'email'            => 'maria@test.cl',
            'telefono'         => '+56911110000',
            'direccion'        => 'Av. Libertad 456',
            'estado'           => 'ACTIVO',
        ]);
    }

    private function crearContrato(Empleado $empleado): Contrato
    {
        return Contrato::create([
            'empresa_id'         => $empleado->empresa_id,
            'empleado_id'        => $empleado->id,
            'tipo'               => 'INDEFINIDO',
            'fecha_inicio'       => '2025-01-01',
            'sueldo_base'        => 900000,
            'estado'             => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);
    }

    public function test_exportar_empleado_incluye_clave_accesos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado  = $this->crearEmpleado($empresa->id);
        $contrato  = $this->crearContrato($empleado);

        $liquidacion = Liquidacion::create([
            'empresa_id'      => $empresa->id,
            'empleado_id'     => $empleado->id,
            'contrato_id'     => $contrato->id,
            'anio'            => 2026,
            'mes'             => 3,
            'liquido_a_pagar' => 750000,
            'estado'          => Liquidacion::ESTADO_EMITIDA,
        ]);

        DB::table('auditorias')->insert([
            'auditable_type' => Liquidacion::class,
            'auditable_id'   => $liquidacion->id,
            'nombre_usuario' => 'admin@test.cl',
            'operacion'      => 'LECTURA',
            'detalle'        => 'Consulta de liquidación del empleado id=' . $empleado->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $resultado = app(ArcoService::class)->exportarEmpleado($empresa->id, $empleado->id);

        $this->assertArrayHasKey('accesos', $resultado, 'La exportación debe contener la clave accesos.');
        $this->assertCount(1, $resultado['accesos'], 'Debe haber exactamente un acceso registrado.');
        $this->assertEquals('admin@test.cl', $resultado['accesos'][0]['usuario']);
        $this->assertEquals('LECTURA', $resultado['accesos'][0]['operacion']);
        $this->assertStringContainsString((string) $empleado->id, $resultado['accesos'][0]['detalle']);
    }

    public function test_exportar_empleado_accesos_vacio_cuando_no_hay_lecturas(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado  = $this->crearEmpleado($empresa->id);
        $this->crearContrato($empleado);

        $resultado = app(ArcoService::class)->exportarEmpleado($empresa->id, $empleado->id);

        $this->assertArrayHasKey('accesos', $resultado);
        $this->assertCount(0, $resultado['accesos'], 'Sin lecturas registradas, accesos debe estar vacío.');
    }

    public function test_exportar_empleado_no_cruza_accesos_de_otro_empleado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleadoA = $this->crearEmpleado($empresa->id);
        $empleadoB = $this->crearEmpleado($empresa->id);
        $contratoA = $this->crearContrato($empleadoA);
        $this->crearContrato($empleadoB);

        $liquidacion = Liquidacion::create([
            'empresa_id'      => $empresa->id,
            'empleado_id'     => $empleadoA->id,
            'contrato_id'     => $contratoA->id,
            'anio'            => 2026,
            'mes'             => 3,
            'liquido_a_pagar' => 750000,
            'estado'          => Liquidacion::ESTADO_EMITIDA,
        ]);

        DB::table('auditorias')->insert([
            'auditable_type' => Liquidacion::class,
            'auditable_id'   => $liquidacion->id,
            'nombre_usuario' => 'admin@test.cl',
            'operacion'      => 'LECTURA',
            'detalle'        => 'Consulta de liquidación del empleado id=' . $empleadoA->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // La exportación de empleadoB no debe incluir los accesos de empleadoA
        $resultadoB = app(ArcoService::class)->exportarEmpleado($empresa->id, $empleadoB->id);

        $this->assertCount(0, $resultadoB['accesos'], 'Los accesos de otro empleado no deben filtrarse en la exportación.');
    }
}
