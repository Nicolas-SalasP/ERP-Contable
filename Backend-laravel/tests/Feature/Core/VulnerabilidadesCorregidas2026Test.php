<?php

namespace Tests\Feature\Core;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionEmpresa;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use App\Support\HmacFirma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Pruebas de regresión para las vulnerabilidades corregidas en junio 2026.
 *
 * 1. Escritura de IPC global denegada a rol con jerarquía 80 (Administrador).
 * 2. Tokens revocados inmediatamente al bloquear un usuario.
 * 3. Factura de compra con proveedor de otra empresa es rechazada.
 * 4. Onboarding con RUT duplicado es rechazado.
 */
class VulnerabilidadesCorregidas2026Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected Empresa $empresa;
    protected User $usuarioAdmin;
    protected User $usuarioSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa, $this->usuarioAdmin] = $this->crearEmpresaConAdmin();

        $this->usuarioSuperAdmin = $this->crearUsuario($this->empresa, $this->rolSuperAdmin);

        CmConfiguracionEmpresa::firstOrCreate(
            ['empresa_id' => $this->empresa->id],
            [
                'aplica_cm'                  => true,
                'modalidad'                  => 'anual',
                'mes_cierre'                 => 12,
                'cuenta_activos_codigo'      => '811001',
                'cuenta_depreciacion_codigo' => '821001',
                'cuenta_patrimonio_codigo'   => '311406',
                'cuenta_existencias_codigo'  => '811002',
                'cuenta_pasivos_codigo'      => '821002',
                'activo'                     => true,
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Fix 1: escritura IPC global restringida a jerarquía >= 100
    // -------------------------------------------------------------------------

    public function test_escritura_ipc_denegada_a_admin_empresa_jerarquia_80(): void
    {
        // rolAdministrador tiene jerarquía 80; debe recibir 403.
        Sanctum::actingAs($this->usuarioAdmin);

        $response = $this->postJson('/api/correccion-monetaria/indices', [
            'anio'      => 2026,
            'mes'       => 3,
            'variacion' => 0.42,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('cm_indices_ipc', ['anio' => 2026, 'mes' => 3]);
    }

    public function test_escritura_ipc_permitida_a_super_admin_jerarquia_100(): void
    {
        // rolSuperAdmin tiene jerarquía 100; debe poder escribir.
        Sanctum::actingAs($this->usuarioSuperAdmin);

        $response = $this->postJson('/api/correccion-monetaria/indices', [
            'anio'      => 2026,
            'mes'       => 4,
            'variacion' => 0.35,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('cm_indices_ipc', ['anio' => 2026, 'mes' => 4]);
    }

    // -------------------------------------------------------------------------
    // Fix 3: tokens revocados al bloquear usuario (interno web.api.key)
    // El middleware VerifyWebApiKey requiere TENRI_WEB_INTEGRATION_KEY; en tests
    // lo inyectamos vía config para no depender de .env.testing.
    // -------------------------------------------------------------------------

    public function test_tokens_revocados_al_bloquear_usuario(): void
    {
        $claveTest = 'clave-test-1234';
        config(['services.tenri_web.web_integration_key' => $claveTest]);

        // Crear token activo para el usuario.
        $this->usuarioAdmin->createToken('test-token');
        $this->assertSame(1, $this->usuarioAdmin->tokens()->count());

        $payload = ['hasta' => now()->addHour()->format('Y-m-d H:i:s')];
        $headers = HmacFirma::headers($claveTest, json_encode($payload));

        $response = $this->withHeaders($headers)
            ->postJson("/api/internal/web/usuarios/{$this->usuarioAdmin->id}/bloquear", $payload);

        $response->assertOk()->assertJson(['success' => true]);
        $this->usuarioAdmin->refresh();
        $this->assertSame(0, $this->usuarioAdmin->tokens()->count(), 'Los tokens deben revocarse al bloquear.');
    }

    // -------------------------------------------------------------------------
    // Fix 4: factura de compra con proveedor de otra empresa rechazada
    // -------------------------------------------------------------------------

    public function test_factura_compra_con_proveedor_de_otra_empresa_es_rechazada(): void
    {
        $empresaB = $this->crearEmpresa();
        $provAjeno = Proveedor::create([
            'empresa_id'      => $empresaB->id,
            'codigo_interno'  => 'PR-AJENO',
            'rut'             => '55.555.555-5',
            'razon_social'    => 'Proveedor Ajeno',
            'pais_iso'        => 'CL',
            'moneda_defecto'  => 'CLP',
        ]);

        Sanctum::actingAs($this->usuarioAdmin);

        $response = $this->postJson('/api/facturas', [
            'proveedor_id'   => $provAjeno->id,
            'numero_factura' => 'F-XTEN-001',
            'tipo_documento' => 'FACTURA',
            'fecha_emision'  => now()->format('Y-m-d'),
            'monto_neto'     => 100,
            'monto_iva'      => 19,
            'monto_bruto'    => 119,
            'cuentaDestino'  => '510001',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('proveedor', strtolower($response->json('message') ?? ''));
        $this->assertDatabaseMissing('facturas', ['numero_factura' => 'F-XTEN-001']);
    }

    // -------------------------------------------------------------------------
    // Fix 5: onboarding rechaza RUT duplicado
    // -------------------------------------------------------------------------

    public function test_onboarding_rut_duplicado_es_rechazado(): void
    {
        // Empresa ya existente con un RUT conocido.
        $rutExistente = '12345678-9';
        Empresa::create([
            'rut'          => $rutExistente,
            'razon_social' => 'Empresa Existente',
        ]);

        // Usuario nuevo sin empresa (listo para onboarding).
        $nuevoUsuario = User::create([
            'nombre'               => 'Nuevo Usuario',
            'email'                => 'nuevo@test.cl',
            'password'             => bcrypt('password123'),
            'empresa_id'           => null,
            'rol_id'               => $this->rolAdministrador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        Sanctum::actingAs($nuevoUsuario);

        $response = $this->postJson('/api/empresas/onboarding', [
            'empresa_rut'          => $rutExistente,
            'empresa_razon_social' => 'Intento Duplicado',
        ]);

        $response->assertStatus(422);
        // El usuario no debe haber quedado vinculado a ninguna empresa.
        $this->assertNull($nuevoUsuario->fresh()->empresa_id);
    }
}
