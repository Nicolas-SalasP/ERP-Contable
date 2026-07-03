<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\ProvisionVacaciones;
use App\Domains\Rrhh\Models\SolicitudVacaciones;
use App\Domains\Rrhh\Services\Provisiones\VacacionesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Solicitud/aprobacion de vacaciones: el saldo real debe decrementarse al
 * aprobar (antes de este fix, VacacionesService::saldoActual solo crecia,
 * sin ningun flujo que lo consumiera).
 */
class SolicitudVacacionesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private VacacionesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(VacacionesService::class);
    }

    private function contratoConSaldo(int $empresaId, float $diasDisponibles): Contrato
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '11.111.111-' . rand(0, 9),
            'nombres' => 'Trabajador',
            'apellido_paterno' => 'Vac',
        ]);

        $contrato = Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 600000,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        ProvisionVacaciones::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'contrato_id' => $contrato->id,
            'anio' => 2026,
            'mes' => 6,
            'dias_devengados_mes' => $diasDisponibles,
            'saldo_dias_habiles' => $diasDisponibles,
            'monto_devengado_mes' => 0,
            'monto_provisionado_total' => 0,
            'remuneracion_diaria' => 20000,
        ]);

        return $contrato;
    }

    public function test_dias_habiles_entre_cuenta_solo_lunes_a_viernes(): void
    {
        // Lunes 2026-06-08 a viernes 2026-06-12 = 5 dias habiles
        $dias = $this->service->diasHabilesEntre(
            \Carbon\Carbon::parse('2026-06-08'),
            \Carbon\Carbon::parse('2026-06-12')
        );
        $this->assertEquals(5, $dias);

        // Cruzando un fin de semana: lunes a lunes siguiente = 6 dias habiles
        $dias2 = $this->service->diasHabilesEntre(
            \Carbon\Carbon::parse('2026-06-08'),
            \Carbon\Carbon::parse('2026-06-15')
        );
        $this->assertEquals(6, $dias2);
    }

    public function test_solicitar_y_aprobar_decrementa_saldo_disponible(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id, 10.0);

        $saldoInicial = $this->service->saldoActual($empresa->id, $contrato->empleado_id);
        $this->assertEquals(10.0, $saldoInicial['dias_disponibles']);

        $solicitud = $this->service->solicitar(
            $empresa->id,
            $contrato->empleado_id,
            '2026-06-08', // lunes
            '2026-06-12', // viernes: 5 dias habiles
            $usuario->id
        );

        $this->assertEquals(SolicitudVacaciones::ESTADO_PENDIENTE, $solicitud->estado);
        $this->assertEquals(5.0, (float) $solicitud->dias_habiles);

        // Pendiente todavia no descuenta.
        $saldoTrasSolicitar = $this->service->saldoActual($empresa->id, $contrato->empleado_id);
        $this->assertEquals(10.0, $saldoTrasSolicitar['dias_disponibles']);

        $this->service->aprobar($empresa->id, $solicitud->id, $usuario->id);

        // Aprobada si descuenta.
        $saldoTrasAprobar = $this->service->saldoActual($empresa->id, $contrato->empleado_id);
        $this->assertEquals(5.0, $saldoTrasAprobar['dias_disponibles']);
    }

    public function test_solicitar_con_saldo_insuficiente_falla(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id, 2.0);

        $this->expectExceptionMessage('Saldo insuficiente');

        // 5 dias habiles solicitados, solo 2 disponibles.
        $this->service->solicitar(
            $empresa->id,
            $contrato->empleado_id,
            '2026-06-08',
            '2026-06-12',
            $usuario->id
        );
    }

    public function test_aprobar_dos_veces_falla(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id, 10.0);

        $solicitud = $this->service->solicitar(
            $empresa->id, $contrato->empleado_id, '2026-06-08', '2026-06-09', $usuario->id
        );
        $this->service->aprobar($empresa->id, $solicitud->id, $usuario->id);

        $this->expectExceptionMessage('ya fue resuelta');
        $this->service->aprobar($empresa->id, $solicitud->id, $usuario->id);
    }

    public function test_rechazar_solicitud_no_descuenta_saldo(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id, 10.0);

        $solicitud = $this->service->solicitar(
            $empresa->id, $contrato->empleado_id, '2026-06-08', '2026-06-09', $usuario->id
        );
        $this->service->rechazar($empresa->id, $solicitud->id, $usuario->id, 'Sin cobertura en el equipo');

        $saldo = $this->service->saldoActual($empresa->id, $contrato->empleado_id);
        $this->assertEquals(10.0, $saldo['dias_disponibles']);
    }

    public function test_anular_solicitud_aprobada_repone_saldo(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id, 10.0);

        $solicitud = $this->service->solicitar(
            $empresa->id, $contrato->empleado_id, '2026-06-08', '2026-06-09', $usuario->id
        );
        $this->service->aprobar($empresa->id, $solicitud->id, $usuario->id);

        $saldoTrasAprobar = $this->service->saldoActual($empresa->id, $contrato->empleado_id);
        $this->assertEquals(8.0, $saldoTrasAprobar['dias_disponibles']);

        $this->service->anular($empresa->id, $solicitud->id, $usuario->id, 'Empleado no pudo tomar los dias');

        $saldoTrasAnular = $this->service->saldoActual($empresa->id, $contrato->empleado_id);
        $this->assertEquals(10.0, $saldoTrasAnular['dias_disponibles']);
    }

    public function test_api_solicitar_y_aprobar_via_endpoints(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoConSaldo($empresa->id, 10.0);

        Sanctum::actingAs($usuario);

        $solicitudResp = $this->postJson('/api/rrhh/vacaciones/solicitudes', [
            'empleado_id' => $contrato->empleado_id,
            'fecha_desde' => '2026-06-08',
            'fecha_hasta' => '2026-06-09',
        ]);
        $solicitudResp->assertStatus(201)->assertJson(['success' => true]);
        $solicitudId = $solicitudResp->json('data.id');

        $aprobarResp = $this->postJson("/api/rrhh/vacaciones/solicitudes/{$solicitudId}/aprobar");
        $aprobarResp->assertOk()->assertJson(['success' => true]);

        $saldoResp = $this->getJson("/api/rrhh/vacaciones/saldo/{$contrato->empleado_id}");
        $saldoResp->assertOk();
        $this->assertEquals(8.0, $saldoResp->json('data.dias_disponibles'));
    }
}
