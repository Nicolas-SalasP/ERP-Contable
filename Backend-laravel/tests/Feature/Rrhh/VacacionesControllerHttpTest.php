<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\ProvisionVacaciones;
use App\Domains\Rrhh\Models\SolicitudVacaciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * SolicitudVacacionesTest ya cubre solicitar/aprobar/saldo vía HTTP. rechazar()
 * y anular() (esta última repone el saldo) nunca se ejercitaron por esa ruta:
 * validación de motivo, transición de estado inválida y multitenant.
 */
class VacacionesControllerHttpTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function contratoConSaldo(int $empresaId, float $diasDisponibles = 10.0): Contrato
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '11.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(0, 9),
            'nombres' => 'Trabajador Vac', 'apellido_paterno' => 'Test',
        ]);

        $contrato = Contrato::create([
            'empresa_id' => $empresaId, 'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO', 'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 600000, 'estado' => 'VIGENTE', 'es_contrato_activo' => true,
        ]);

        ProvisionVacaciones::create([
            'empresa_id' => $empresaId, 'empleado_id' => $empleado->id, 'contrato_id' => $contrato->id,
            'anio' => 2026, 'mes' => 6,
            'dias_devengados_mes' => $diasDisponibles, 'saldo_dias_habiles' => $diasDisponibles,
            'monto_devengado_mes' => 0, 'monto_provisionado_total' => 0, 'remuneracion_diaria' => 20000,
        ]);

        return $contrato;
    }

    private function solicitarPendiente($admin, int $empleadoId, string $desde = '2026-06-08', string $hasta = '2026-06-09'): array
    {
        return $this->actingAs($admin)->postJson('/api/rrhh/vacaciones/solicitudes', [
            'empleado_id' => $empleadoId, 'fecha_desde' => $desde, 'fecha_hasta' => $hasta,
        ])->assertStatus(201)->json('data');
    }

    // ── rechazar ──────────────────────────────────────────────────────────

    public function test_rechazar_via_http_cambia_estado_y_guarda_motivo()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id);
        $solicitud = $this->solicitarPendiente($admin, $contrato->empleado_id);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitud['id']}/rechazar", [
            'motivo' => 'No hay cobertura para esas fechas',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.estado', SolicitudVacaciones::ESTADO_RECHAZADA);
        $this->assertDatabaseHas('solicitudes_vacaciones', [
            'id' => $solicitud['id'], 'estado' => SolicitudVacaciones::ESTADO_RECHAZADA, 'motivo_rechazo' => 'No hay cobertura para esas fechas',
        ]);
    }

    public function test_rechazar_valida_motivo_requerido_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id);
        $solicitud = $this->solicitarPendiente($admin, $contrato->empleado_id);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitud['id']}/rechazar", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['motivo']);
    }

    public function test_rechazar_solicitud_ya_resuelta_falla_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id);
        $solicitud = $this->solicitarPendiente($admin, $contrato->empleado_id);
        $this->actingAs($admin)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitud['id']}/aprobar")->assertStatus(200);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitud['id']}/rechazar", [
            'motivo' => 'Cambio de opinión',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    // ── anular ────────────────────────────────────────────────────────────

    public function test_anular_via_http_repone_el_saldo()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id, 10.0);
        $solicitud = $this->solicitarPendiente($admin, $contrato->empleado_id);
        $this->actingAs($admin)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitud['id']}/aprobar")->assertStatus(200);

        $antesDeAnular = $this->actingAs($admin)->getJson("/api/rrhh/vacaciones/saldo/{$contrato->empleado_id}")->json('data.dias_disponibles');
        $this->assertEquals(8.0, $antesDeAnular);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitud['id']}/anular", [
            'motivo' => 'El empleado no pudo tomar los días',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.estado', SolicitudVacaciones::ESTADO_ANULADA);
        $saldoTrasAnular = $this->actingAs($admin)->getJson("/api/rrhh/vacaciones/saldo/{$contrato->empleado_id}")->json('data.dias_disponibles');
        $this->assertEquals(10.0, $saldoTrasAnular);
    }

    public function test_anular_solicitud_pendiente_falla_solo_se_anula_aprobada()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id);
        $solicitud = $this->solicitarPendiente($admin, $contrato->empleado_id);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitud['id']}/anular", [
            'motivo' => 'Intento inválido',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_anular_de_otra_empresa_devuelve_404_y_no_repone_saldo_ajeno()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB, $adminB] = $this->crearEmpresaConAdmin();
        $contratoB = $this->contratoConSaldo($empresaB->id);
        $solicitudB = $this->solicitarPendiente($adminB, $contratoB->empleado_id);
        $this->actingAs($adminB)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitudB['id']}/aprobar")->assertStatus(200);

        $response = $this->actingAs($adminA)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitudB['id']}/anular", [
            'motivo' => 'Intento cruzado',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('solicitudes_vacaciones', ['id' => $solicitudB['id'], 'estado' => SolicitudVacaciones::ESTADO_APROBADA]);
    }

    public function test_rechazar_sin_permiso_devuelve_403()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id);
        $solicitud = $this->solicitarPendiente($admin, $contrato->empleado_id);
        $sinPermiso = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $response = $this->actingAs($sinPermiso)->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitud['id']}/rechazar", [
            'motivo' => 'No autorizado',
        ]);

        $response->assertStatus(403);
    }
}
