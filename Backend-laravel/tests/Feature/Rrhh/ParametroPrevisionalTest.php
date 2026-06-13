<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Core\Models\Rol;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R2 — Parámetros previsionales: vigencias sin solapamiento + protección global.
 */
class ParametroPrevisionalTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function payloadBase(string $desde): array
    {
        return [
            'vigente_desde' => $desde,
            'afp_cotizacion_pct' => 10.0,
            'afp_comisiones_json' => ['Habitat' => 1.27],
            'afp_sis_pct' => 1.62,
            'tope_imponible_uf' => 90.0,
            'salud_fonasa_pct' => 7.0,
            'afc_indefinido_trabajador_pct' => 0.6,
            'afc_indefinido_empleador_pct' => 2.4,
            'afc_plazo_fijo_trabajador_pct' => 0.0,
            'afc_plazo_fijo_empleador_pct' => 3.0,
            'afc_tope_imponible_uf' => 135.2,
            'imm' => 539000,
        ];
    }

    /** Crea un usuario Super Admin (jerarquía 100) sin empresa asociada. */
    private function crearSuperAdmin(): \App\Domains\Core\Models\User
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        return $this->crearUsuario($empresa, $this->rolSuperAdmin);
    }

    // ── Tests de seguridad: protección de tablas globales ────────────────────

    public function test_admin_empresa_no_puede_escribir_parametro_global(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin(); // jerarquía 80

        $response = $this->actingAs($admin)
            ->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));

        $response->assertStatus(403);
    }

    public function test_admin_empresa_no_puede_escribir_indicador_global(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin(); // jerarquía 80

        $response = $this->actingAs($admin)
            ->postJson('/api/rrhh/indicadores', [
                'anio' => 2026, 'mes' => 6,
                'uf_valor' => 39850, 'utm_valor' => 71506,
            ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_puede_crear_parametro(): void
    {
        $superAdmin = $this->crearSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_superadmin_puede_crear_indicador(): void
    {
        $superAdmin = $this->crearSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->postJson('/api/rrhh/indicadores', [
                'anio' => 2026, 'mes' => 6,
                'uf_valor' => 39850, 'utm_valor' => 71506,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // ── Tests de vigencia sin solapamiento ───────────────────────────────────

    public function test_crea_primer_parametro_sin_anterior(): void
    {
        $superAdmin = $this->crearSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('parametros_previsionales', [
            'vigente_hasta' => null,
        ]);
        $this->assertEquals(
            '2026-01-01',
            ParametroPrevisional::whereNull('vigente_hasta')->first()?->vigente_desde->toDateString()
        );
    }

    public function test_cierra_vigencia_del_parametro_anterior_al_crear_nuevo(): void
    {
        $superAdmin = $this->crearSuperAdmin();

        $this->actingAs($superAdmin)->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));
        $anterior = ParametroPrevisional::first();
        $this->assertNull($anterior->vigente_hasta);

        $this->actingAs($superAdmin)->postJson('/api/rrhh/parametros', $this->payloadBase('2026-07-01'))
            ->assertStatus(201);

        $anterior->refresh();
        $this->assertEquals('2026-06-30', $anterior->vigente_hasta->toDateString(),
            'El parámetro anterior debe cerrarse el día previo al nuevo vigente_desde.');

        $nuevo = ParametroPrevisional::whereNull('vigente_hasta')->first();
        $this->assertNotNull($nuevo);
        $this->assertEquals('2026-07-01', $nuevo->vigente_desde->toDateString());
    }

    public function test_rechaza_vigente_desde_igual_al_del_parametro_actual(): void
    {
        $superAdmin = $this->crearSuperAdmin();

        $this->actingAs($superAdmin)->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));

        $response = $this->actingAs($superAdmin)
            ->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));

        $response->assertStatus(422);
    }

    public function test_rechaza_vigente_desde_anterior_al_del_parametro_actual(): void
    {
        $superAdmin = $this->crearSuperAdmin();

        $this->actingAs($superAdmin)->postJson('/api/rrhh/parametros', $this->payloadBase('2026-06-01'));

        $response = $this->actingAs($superAdmin)
            ->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));

        $response->assertStatus(422);
    }

    public function test_requiere_permiso_para_crear_parametro(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $rolSin = Rol::create([
            'nombre' => 'Sin Permiso', 'jerarquia' => 10, 'permisos' => [],
        ]);
        $usuario = $this->crearUsuario($empresa, $rolSin);

        $response = $this->actingAs($usuario)
            ->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));

        $response->assertStatus(403);
    }

    public function test_cierre_de_vigencia_es_atomico(): void
    {
        $superAdmin = $this->crearSuperAdmin();

        $this->actingAs($superAdmin)->postJson('/api/rrhh/parametros', $this->payloadBase('2026-01-01'));

        // Payload inválido (falta imm) → la transacción debe revertirse
        $payload = $this->payloadBase('2026-07-01');
        unset($payload['imm']);

        $this->actingAs($superAdmin)->postJson('/api/rrhh/parametros', $payload)
            ->assertStatus(422);

        // El parámetro anterior no debe haber sido cerrado
        $parametro = ParametroPrevisional::first();
        $this->assertNull($parametro->vigente_hasta,
            'Si el nuevo parámetro falla validación, el anterior no debe cerrarse.');
    }
}
