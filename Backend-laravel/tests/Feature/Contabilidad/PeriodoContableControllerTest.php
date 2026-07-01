<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Models\PeriodoContable;
use App\Domains\Contabilidad\Services\PeriodoContableService;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Tests HTTP del controlador PeriodoContableController.
 *
 * Complementan a BloqueoPeriodoContableTest (que cubre la capa de servicio y observer).
 * Aquí se verifica: autenticación, autorización por permiso, respuestas JSON y
 * aislamiento multitenant a nivel de endpoint.
 */
class PeriodoContableControllerTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private Empresa $empresa;
    private User $admin;
    private User $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = Empresa::create(['rut' => '76.100.001-1', 'razon_social' => 'Ctrl Test SpA']);

        // SuperAdmin → todos los permisos (jerarquía 100)
        $this->admin = User::create([
            'nombre' => 'Admin Cierre',
            'email' => 'admin.cierre@test.cl',
            'password' => bcrypt('x'),
            'empresa_id' => $this->empresa->id,
            'empresa_activa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        // Contador → contabilidad.ver pero NO contabilidad.cerrar_periodo
        $this->contador = User::create([
            'nombre' => 'Contador Test',
            'email' => 'contador.cierre@test.cl',
            'password' => bcrypt('x'),
            'empresa_id' => $this->empresa->id,
            'empresa_activa_id' => $this->empresa->id,
            'rol_id' => $this->rolContador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    // ──────────────────────────────────────────────
    // GET /contabilidad/periodos
    // ──────────────────────────────────────────────

    public function test_listar_sin_autenticar_devuelve_401(): void
    {
        $this->getJson('/api/contabilidad/periodos')->assertStatus(401);
    }

    public function test_listar_con_permiso_ver_devuelve_200_y_lista_vacia(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/contabilidad/periodos')
            ->assertStatus(200)
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('data', []);
    }

    public function test_listar_devuelve_solo_periodos_de_la_empresa_autenticada(): void
    {
        $service = app(PeriodoContableService::class);
        $service->cerrar($this->empresa->id, 2026, 1, $this->admin->load('rol'));

        // Otra empresa — su período NO debe aparecer
        $otraEmpresa = Empresa::create(['rut' => '76.100.002-2', 'razon_social' => 'Otra SpA']);
        $otroAdmin = User::create([
            'nombre' => 'Otro Admin',
            'email' => 'otro@test.cl',
            'password' => bcrypt('x'),
            'empresa_id' => $otraEmpresa->id,
            'empresa_activa_id' => $otraEmpresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
        $service->cerrar($otraEmpresa->id, 2026, 2, $otroAdmin->load('rol'));

        $respuesta = $this->actingAs($this->admin)
            ->getJson('/api/contabilidad/periodos')
            ->assertStatus(200);

        $data = $respuesta->json('data');
        $this->assertCount(1, $data, 'Solo debe listar el período de la empresa autenticada');
        $this->assertSame(1, $data[0]['mes']);
    }

    public function test_listar_filtra_por_anio_cuando_se_pasa_parametro(): void
    {
        $service = app(PeriodoContableService::class);
        $service->cerrar($this->empresa->id, 2025, 6, $this->admin->load('rol'));
        $service->cerrar($this->empresa->id, 2026, 3, $this->admin->load('rol'));

        $respuesta = $this->actingAs($this->admin)
            ->getJson('/api/contabilidad/periodos?anio=2025')
            ->assertStatus(200);

        $data = $respuesta->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(2025, $data[0]['anio']);
    }

    // ──────────────────────────────────────────────
    // POST /contabilidad/periodos/cerrar
    // ──────────────────────────────────────────────

    public function test_cerrar_sin_autenticar_devuelve_401(): void
    {
        $this->postJson('/api/contabilidad/periodos/cerrar', ['anio' => 2026, 'mes' => 5])
            ->assertStatus(401);
    }

    public function test_cerrar_sin_permiso_cerrar_periodo_devuelve_403(): void
    {
        $this->actingAs($this->contador)
            ->postJson('/api/contabilidad/periodos/cerrar', ['anio' => 2026, 'mes' => 5])
            ->assertStatus(403);
    }

    public function test_cerrar_con_permiso_guarda_periodo_en_base_de_datos(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/cerrar', [
                'anio' => 2026,
                'mes' => 4,
                'motivo' => 'Cierre mensual ordinario',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('periodos_contables', [
            'empresa_id' => $this->empresa->id,
            'anio' => 2026,
            'mes' => 4,
            'estado' => PeriodoContable::ESTADO_CERRADO,
        ]);
    }

    public function test_cerrar_requiere_anio_y_mes(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/cerrar', [])
            ->assertStatus(422);
    }

    public function test_cerrar_mes_fuera_de_rango_devuelve_422(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/cerrar', ['anio' => 2026, 'mes' => 13])
            ->assertStatus(422);
    }

    public function test_cerrar_periodo_ya_cerrado_es_idempotente(): void
    {
        $service = app(PeriodoContableService::class);
        $service->cerrar($this->empresa->id, 2026, 7, $this->admin->load('rol'));

        $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/cerrar', ['anio' => 2026, 'mes' => 7])
            ->assertStatus(200);

        $this->assertSame(1, PeriodoContable::where('empresa_id', $this->empresa->id)
            ->where('anio', 2026)->where('mes', 7)->count());
    }

    public function test_cerrar_devuelve_datos_del_periodo_creado(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/cerrar', ['anio' => 2026, 'mes' => 8, 'motivo' => 'Test'])
            ->assertStatus(200);

        $respuesta->assertJsonPath('data.anio', 2026);
        $respuesta->assertJsonPath('data.mes', 8);
        $respuesta->assertJsonPath('data.estado', PeriodoContable::ESTADO_CERRADO);
    }

    // ──────────────────────────────────────────────
    // POST /contabilidad/periodos/reabrir
    // ──────────────────────────────────────────────

    public function test_reabrir_sin_autenticar_devuelve_401(): void
    {
        $this->postJson('/api/contabilidad/periodos/reabrir', ['anio' => 2026, 'mes' => 1, 'motivo' => 'x'])
            ->assertStatus(401);
    }

    public function test_reabrir_sin_permiso_devuelve_403(): void
    {
        $this->actingAs($this->contador)
            ->postJson('/api/contabilidad/periodos/reabrir', ['anio' => 2026, 'mes' => 1, 'motivo' => 'x'])
            ->assertStatus(403);
    }

    public function test_reabrir_sin_motivo_devuelve_422(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/reabrir', ['anio' => 2026, 'mes' => 1])
            ->assertStatus(422);
    }

    public function test_reabrir_periodo_cerrado_cambia_estado(): void
    {
        $service = app(PeriodoContableService::class);
        $service->cerrar($this->empresa->id, 2026, 9, $this->admin->load('rol'));

        $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/reabrir', [
                'anio' => 2026,
                'mes' => 9,
                'motivo' => 'Corrección autorizada por gerencia',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('periodos_contables', [
            'empresa_id' => $this->empresa->id,
            'anio' => 2026,
            'mes' => 9,
            'estado' => PeriodoContable::ESTADO_ABIERTO,
        ]);
    }

    public function test_reabrir_periodo_no_cerrado_devuelve_403(): void
    {
        // Período mes 11 no existe ni está cerrado → servicio lanza AuthorizationException → 403
        $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/reabrir', [
                'anio' => 2026,
                'mes' => 11,
                'motivo' => 'No existe',
            ])
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────
    // Aislamiento multitenant
    // ──────────────────────────────────────────────

    public function test_cierre_via_endpoint_es_aislado_por_empresa(): void
    {
        $otraEmpresa = Empresa::create(['rut' => '76.100.003-3', 'razon_social' => 'Otra Empresa SpA']);
        $otroAdmin = User::create([
            'nombre' => 'Otro',
            'email' => 'otro2@test.cl',
            'password' => bcrypt('x'),
            'empresa_id' => $otraEmpresa->id,
            'empresa_activa_id' => $otraEmpresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        // Admin de empresa A cierra octubre
        $this->actingAs($this->admin)
            ->postJson('/api/contabilidad/periodos/cerrar', ['anio' => 2026, 'mes' => 10])
            ->assertStatus(200);

        // Admin de empresa B lista sus períodos → debe ver 0
        $respuesta = $this->actingAs($otroAdmin)
            ->getJson('/api/contabilidad/periodos')
            ->assertStatus(200);

        $this->assertCount(0, $respuesta->json('data'));
    }
}
