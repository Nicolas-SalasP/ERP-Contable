<?php

namespace Tests\Feature\Core;

use App\Domains\Contabilidad\Models\AsientoContable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * AnulacionController::anular() ya está muy cubierto indirectamente por tests de
 * otros dominios (ContabilidadTest, ReversaAsientoTest, CompensarPartidas*, etc.).
 * Pero AnulacionController::buscar() (GET /api/anulacion/buscar) — validación,
 * permiso, multitenant, tipo no soportado, 404 — nunca se ejercitó vía HTTP.
 */
class AnulacionControllerHttpTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearAsiento(int $empresaId, string $numero, array $overrides = []): AsientoContable
    {
        $asiento = AsientoContable::create(array_merge([
            'empresa_id' => $empresaId,
            'numero_comprobante' => $numero,
            'fecha' => '2026-06-15',
            'glosa' => 'Asiento de prueba',
            'tipo_asiento' => 'MANUAL',
            'estado' => 'MAYORIZADO',
        ], $overrides));

        $asiento->detalles()->create([
            'cuenta_contable' => '1101',
            'fecha' => '2026-06-15',
            'tipo_operacion' => 'DEBE',
            'debe' => 50000,
            'haber' => 0,
        ]);
        $asiento->detalles()->create([
            'cuenta_contable' => '2101',
            'fecha' => '2026-06-15',
            'tipo_operacion' => 'HABER',
            'debe' => 0,
            'haber' => 50000,
        ]);

        return $asiento;
    }

    public function test_buscar_asiento_existente_devuelve_detalles_y_total_debe()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->crearAsiento($empresa->id, '2606100001');

        $response = $this->actingAs($admin)->postJson('/api/anulacion/buscar', [
            'tipo_documento' => 'ASIENTO',
            'numero_documento' => '2606100001',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', 'ASIENTO')
            ->assertJsonPath('data.numero', '2606100001')
            ->assertJsonPath('data.total', 50000);
        $this->assertCount(2, $response->json('data.detalles'));
    }

    public function test_buscar_acepta_alias_comprobante_y_minusculas()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->crearAsiento($empresa->id, '2606100002');

        $response = $this->actingAs($admin)->postJson('/api/anulacion/buscar', [
            'tipo_documento' => 'comprobante',
            'numero_documento' => '2606100002',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.numero', '2606100002');
    }

    public function test_buscar_asiento_inexistente_devuelve_404()
    {
        [, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/anulacion/buscar', [
            'tipo_documento' => 'ASIENTO',
            'numero_documento' => '9999999999',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('9999999999', $response->json('message'));
    }

    public function test_buscar_tipo_documento_no_soportado_devuelve_404_con_mensaje_claro()
    {
        [, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/anulacion/buscar', [
            'tipo_documento' => 'FACTURA',
            'numero_documento' => '123',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('no está soportado', $response->json('message'));
    }

    public function test_buscar_valida_campos_requeridos_con_422()
    {
        [, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/anulacion/buscar', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['tipo_documento', 'numero_documento']);
    }

    public function test_anular_valida_campos_requeridos_con_422_y_errores_por_campo()
    {
        [, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/anulacion/anular', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_documento', 'documento_id', 'motivo', 'fecha_anulacion']);
    }

    public function test_buscar_no_filtra_asiento_de_otra_empresa_multitenant()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();

        // Mismo número de comprobante, pero en la empresa B.
        $this->crearAsiento($empresaB->id, '2606100003');

        $response = $this->actingAs($adminA)->postJson('/api/anulacion/buscar', [
            'tipo_documento' => 'ASIENTO',
            'numero_documento' => '2606100003',
        ]);

        $response->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_buscar_sin_permiso_devuelve_403()
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $this->crearAsiento($empresa->id, '2606100004');
        $usuarioSinPermiso = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $response = $this->actingAs($usuarioSinPermiso)->postJson('/api/anulacion/buscar', [
            'tipo_documento' => 'ASIENTO',
            'numero_documento' => '2606100004',
        ]);

        $response->assertStatus(403);
    }
}
