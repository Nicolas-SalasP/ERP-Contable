<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Jobs\GenerarReporteContableJob;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\ReporteContableSolicitado;
use App\Domains\Contabilidad\Notifications\ReporteContableGeneradoNotification;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ReporteExportacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresaA;
    protected $usuarioContador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $rol = Rol::create(['nombre' => 'Contador', 'jerarquia' => 50, 'permisos' => $this->permisosOperativosCompletos()]);

        $this->empresaA = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Finanzas Claras SpA']);

        $this->usuarioContador = User::create([
            'nombre' => 'Contador Jefe',
            'email' => 'contador@claras.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $rol->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    public function test_solicitar_exportacion_encola_job_y_devuelve_202()
    {
        Queue::fake();

        $response = $this->actingAs($this->usuarioContador)->postJson('/api/contabilidad/reportes/exportar', [
            'tipo_reporte' => 'libro_diario',
            'fecha_inicio' => '2020-01-01',
            'fecha_fin' => '2025-12-31',
        ]);

        $response->assertStatus(202)->assertJsonPath('success', true);

        $this->assertDatabaseHas('reportes_contables_solicitados', [
            'empresa_id' => $this->empresaA->id,
            'usuario_id' => $this->usuarioContador->id,
            'tipo_reporte' => 'libro_diario',
            'estado' => ReporteContableSolicitado::ESTADO_PENDIENTE,
            'email_destino' => 'contador@claras.cl',
        ]);

        Queue::assertPushed(GenerarReporteContableJob::class);
    }

    public function test_solicitar_exportacion_rechaza_rango_mayor_a_10_anios()
    {
        $response = $this->actingAs($this->usuarioContador)->postJson('/api/contabilidad/reportes/exportar', [
            'tipo_reporte' => 'libro_diario',
            'fecha_inicio' => '2000-01-01',
            'fecha_fin' => '2025-12-31',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['fecha_fin']);
    }

    public function test_solicitar_exportacion_exige_cuenta_contable_para_libro_mayor()
    {
        $response = $this->actingAs($this->usuarioContador)->postJson('/api/contabilidad/reportes/exportar', [
            'tipo_reporte' => 'libro_mayor',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-12-31',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['cuenta_contable']);
    }

    public function test_solicitar_exportacion_permite_email_custom_distinto_al_del_usuario()
    {
        Queue::fake();

        $response = $this->actingAs($this->usuarioContador)->postJson('/api/contabilidad/reportes/exportar', [
            'tipo_reporte' => 'libro_diario',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-12-31',
            'email' => 'otro@destino.cl',
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('reportes_contables_solicitados', ['email_destino' => 'otro@destino.cl']);
    }

    public function test_job_genera_libro_diario_lo_envia_por_correo_y_marca_enviado()
    {
        NotificationFacade::fake();
        Storage::fake('local');

        $cuenta = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1001', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $asiento = AsientoContable::create([
            'empresa_id' => $this->empresaA->id,
            'numero_comprobante' => 'C-EXPORT-01',
            'fecha' => '2025-06-10',
            'glosa' => 'Asiento para exportar',
            'estado' => 'MAYORIZADO',
        ]);
        DetalleAsiento::create(['asiento_id' => $asiento->id, 'cuenta_contable' => $cuenta->codigo, 'debe' => 5000, 'haber' => 0, 'fecha' => '2025-06-10', 'tipo_operacion' => 'DEBE']);
        DetalleAsiento::create(['asiento_id' => $asiento->id, 'cuenta_contable' => $cuenta->codigo, 'debe' => 0, 'haber' => 5000, 'fecha' => '2025-06-10', 'tipo_operacion' => 'HABER']);

        $solicitud = ReporteContableSolicitado::create([
            'empresa_id' => $this->empresaA->id,
            'usuario_id' => $this->usuarioContador->id,
            'tipo_reporte' => 'libro_diario',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-12-31',
            'filtro' => 1,
            'email_destino' => 'contador@claras.cl',
            'estado' => ReporteContableSolicitado::ESTADO_PENDIENTE,
        ]);

        (new GenerarReporteContableJob($solicitud->id))->handle(app(\App\Domains\Contabilidad\Services\ReporteContableService::class));

        $solicitud->refresh();
        $this->assertEquals(ReporteContableSolicitado::ESTADO_ENVIADO, $solicitud->estado);
        $this->assertNotNull($solicitud->enviado_at);

        NotificationFacade::assertSentOnDemand(ReporteContableGeneradoNotification::class);
    }

    public function test_job_marca_error_si_libro_mayor_referencia_cuenta_inexistente()
    {
        NotificationFacade::fake();

        $solicitud = ReporteContableSolicitado::create([
            'empresa_id' => $this->empresaA->id,
            'usuario_id' => $this->usuarioContador->id,
            'tipo_reporte' => 'libro_mayor',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-12-31',
            'filtro' => 1,
            'cuenta_contable' => '999999',
            'email_destino' => 'contador@claras.cl',
            'estado' => ReporteContableSolicitado::ESTADO_PENDIENTE,
        ]);

        (new GenerarReporteContableJob($solicitud->id))->handle(app(\App\Domains\Contabilidad\Services\ReporteContableService::class));

        $solicitud->refresh();
        $this->assertEquals(ReporteContableSolicitado::ESTADO_ERROR, $solicitud->estado);
        $this->assertNotNull($solicitud->error_mensaje);

        NotificationFacade::assertNothingSent();
    }

    public function test_historial_exportaciones_aislado_por_empresa_multitenant()
    {
        $empresaB = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Empresa B']);
        $rolB = Rol::create(['nombre' => 'ContadorB', 'jerarquia' => 50, 'permisos' => $this->permisosOperativosCompletos()]);
        $usuarioB = User::create(['nombre' => 'Conta B', 'email' => 'cb@b.cl', 'password' => bcrypt('123'), 'empresa_id' => $empresaB->id, 'rol_id' => $rolB->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        ReporteContableSolicitado::create([
            'empresa_id' => $this->empresaA->id,
            'usuario_id' => $this->usuarioContador->id,
            'tipo_reporte' => 'libro_diario',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-12-31',
            'email_destino' => 'contador@claras.cl',
        ]);

        $response = $this->actingAs($usuarioB)->getJson('/api/contabilidad/reportes/exportar');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }
}
