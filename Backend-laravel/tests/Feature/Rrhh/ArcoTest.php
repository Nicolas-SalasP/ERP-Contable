<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Core\Models\SolicitudArco;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Fase 5 — Ley 21.719: derechos ARCO+ (acceso, bloqueo, supresión) sobre
 * titulares empleados, operados por el DPO/administrador.
 */
class ArcoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearEmpleado(int $empresaId, array $overrides = []): Empleado
    {
        return Empleado::create(array_merge([
            'empresa_id'       => $empresaId,
            'rut'              => $this->rutAleatorio(),
            'nombres'          => 'Juan Carlos',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'Soto',
            'email'            => 'juan@test.cl',
            'telefono'         => '+56911112222',
            'direccion'        => 'Calle Falsa 123',
            'estado'           => 'ACTIVO',
        ], $overrides));
    }

    private function crearContrato(Empleado $empleado, array $overrides = []): Contrato
    {
        return Contrato::create(array_merge([
            'empresa_id'         => $empleado->empresa_id,
            'empleado_id'        => $empleado->id,
            'tipo'               => 'INDEFINIDO',
            'fecha_inicio'       => '2025-01-01',
            'sueldo_base'        => 900000,
            'estado'             => 'VIGENTE',
            'es_contrato_activo' => true,
        ], $overrides));
    }

    private function crearLiquidacion(Empleado $empleado, Contrato $contrato, int $anio, int $mes): Liquidacion
    {
        return Liquidacion::create([
            'empresa_id'      => $empleado->empresa_id,
            'empleado_id'     => $empleado->id,
            'contrato_id'     => $contrato->id,
            'anio'            => $anio,
            'mes'             => $mes,
            'liquido_a_pagar' => 750000,
            'estado'          => Liquidacion::ESTADO_EMITIDA,
        ]);
    }

    public function test_export_devuelve_identidad_contratos_y_liquidaciones(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);
        $contrato = $this->crearContrato($empleado);
        $this->crearLiquidacion($empleado, $contrato, 2026, 3);

        $response = $this->actingAs($admin)
            ->getJson("/api/rrhh/empleados/{$empleado->id}/datos-personales");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.identidad.nombres', 'Juan Carlos');
        $response->assertJsonPath('data.contacto.email', 'juan@test.cl');
        $this->assertCount(1, $response->json('data.contratos'));
        $this->assertCount(1, $response->json('data.liquidaciones'));
        $response->assertJsonPath('data.liquidaciones.0.periodo', '2026-03');

        $this->assertDatabaseHas('solicitudes_arco', [
            'titular_id' => $empleado->id,
            'tipo'       => SolicitudArco::TIPO_ACCESO,
            'estado'     => SolicitudArco::ESTADO_COMPLETADA,
        ]);
    }

    public function test_bloqueo_marca_bloqueado_at_y_registra_solicitud(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $response = $this->actingAs($admin)
            ->postJson("/api/rrhh/empleados/{$empleado->id}/bloquear", [
                'motivo' => 'Solicitud del titular',
            ]);

        $response->assertStatus(200);
        $this->assertNotNull($empleado->fresh()->bloqueado_at);

        $this->assertDatabaseHas('solicitudes_arco', [
            'titular_id' => $empleado->id,
            'tipo'       => SolicitudArco::TIPO_BLOQUEO,
            'estado'     => SolicitudArco::ESTADO_COMPLETADA,
        ]);
    }

    public function test_supresion_bajo_retencion_es_parcial(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);
        $contrato = $this->crearContrato($empleado);
        $this->crearLiquidacion($empleado, $contrato, 2026, 3);

        $rutOriginal = $empleado->rut;

        $response = $this->actingAs($admin)
            ->postJson("/api/rrhh/empleados/{$empleado->id}/anonimizar", [
                'motivo' => 'Derecho de supresión',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.modo', 'parcial');

        $fresco = Empleado::withTrashed()->find($empleado->id);
        $this->assertNull($fresco->email);
        $this->assertNull($fresco->telefono);
        $this->assertNull($fresco->direccion);
        $this->assertNotNull($fresco->bloqueado_at);
        // Identidad mínima CONSERVADA (retención legal)
        $this->assertEquals($rutOriginal, $fresco->rut);
        $this->assertEquals('Juan Carlos', $fresco->nombres);
        // No fue eliminado
        $this->assertNull($fresco->deleted_at);

        $this->assertDatabaseHas('solicitudes_arco', [
            'titular_id' => $empleado->id,
            'tipo'       => SolicitudArco::TIPO_SUPRESION,
            'estado'     => SolicitudArco::ESTADO_PARCIAL_RETENCION,
        ]);
    }

    public function test_supresion_vencido_plazo_es_total(): void
    {
        // Forzar "vencido el plazo": ventana de retención 0 años.
        config(['arco.retencion_anios' => 0]);

        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id, ['estado' => 'INACTIVO']);
        // Contrato terminado en el pasado (no activo, fuera de la ventana).
        $contrato = $this->crearContrato($empleado, [
            'estado'             => 'TERMINADO',
            'es_contrato_activo' => false,
            'fecha_termino'      => '2018-12-31',
            'fecha_termino_real' => '2018-12-31',
        ]);
        // Liquidación antigua, conservada como registro tributario.
        $liquidacion = $this->crearLiquidacion($empleado, $contrato, 2018, 6);

        $response = $this->actingAs($admin)
            ->postJson("/api/rrhh/empleados/{$empleado->id}/anonimizar", [
                'motivo' => 'Derecho de supresión',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.modo', 'total');

        $fresco = Empleado::withTrashed()->find($empleado->id);
        $this->assertEquals('ANON-' . $empleado->id, $fresco->rut);
        $this->assertEquals('ANONIMIZADO', $fresco->nombres);
        $this->assertNull($fresco->apellido_paterno);
        $this->assertNotNull($fresco->anonimizado_at);
        // Empleado soft-deleted
        $this->assertNotNull($fresco->deleted_at);

        // La liquidación se conserva como registro retenido
        $this->assertDatabaseHas('liquidaciones', ['id' => $liquidacion->id]);

        $this->assertDatabaseHas('solicitudes_arco', [
            'titular_id' => $empleado->id,
            'tipo'       => SolicitudArco::TIPO_SUPRESION,
            'estado'     => SolicitudArco::ESTADO_COMPLETADA,
        ]);
    }

    public function test_usuario_sin_permiso_no_puede_anonimizar_ni_bloquear(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        // El rol Contador NO tiene usuarios.gestionar
        $contador = $this->crearUsuario($empresa, $this->rolContador, [
            'email' => 'contador@test.cl',
        ]);

        $this->actingAs($contador)
            ->postJson("/api/rrhh/empleados/{$empleado->id}/bloquear")
            ->assertStatus(403);

        $this->actingAs($contador)
            ->postJson("/api/rrhh/empleados/{$empleado->id}/anonimizar")
            ->assertStatus(403);
    }

    public function test_export_honra_permiso_rrhh_empleados_ver(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        // Usuario básico sin permisos
        $basico = $this->crearUsuario($empresa, $this->rolUsuarioBasico, [
            'email' => 'basico@test.cl',
        ]);

        $this->actingAs($basico)
            ->getJson("/api/rrhh/empleados/{$empleado->id}/datos-personales")
            ->assertStatus(403);
    }
}
