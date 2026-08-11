<?php

namespace Tests\Feature\Core;

use App\Domains\Integraciones\Models\IntegracionApiKey;
use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Support\HmacFirma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cobertura de AdminIntegracionesController (`internal/web/integraciones/*`,
 * `internal/web/empresas/{id}/integraciones/*`), mismo canal service-to-service
 * (web.api.key) que AdminEmpresasController -- vista cross-empresa de SII y
 * API keys para el panel interno de Tenri.
 */
class AdminIntegracionesControllerTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private const SECRETO = 'secreto-admin-integraciones-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        config(['services.tenri_web.web_integration_key' => self::SECRETO]);
    }

    private function firmarGet(): array
    {
        return HmacFirma::headers(self::SECRETO, json_encode([]));
    }

    private function firmarConCuerpo(array $payload): array
    {
        return HmacFirma::headers(self::SECRETO, json_encode($payload));
    }

    private function crearCertificado(int $empresaId, array $overrides = []): SiiCertificadoEmpresa
    {
        return SiiCertificadoEmpresa::create(array_merge([
            'empresa_id' => $empresaId,
            'pfx_cifrado' => 'contenido-cifrado-test',
            'password_cifrada' => 'password-cifrada-test',
            'subject_common_name' => 'Empresa Test',
            'valido_desde' => now()->subYear(),
            'valido_hasta' => now()->addDays(45),
            'fingerprint_sha256' => str_repeat('a', 64),
            'estado' => SiiCertificadoEmpresa::ESTADO_ACTIVO,
        ], $overrides));
    }

    public function test_resumen_sin_firma_hmac_es_rechazado(): void
    {
        $this->getJson('/api/internal/web/integraciones/resumen')->assertStatus(401);
    }

    public function test_resumen_incluye_sii_y_conteo_de_api_keys_por_empresa(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $this->crearCertificado($empresa->id);
        IntegracionApiKey::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Key activa',
            'prefijo' => 'pfx_activa',
            'token_hash' => hash('sha256', 'secreto-activa'),
            'scopes' => ['inventario:leer'],
        ]);
        IntegracionApiKey::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Key revocada',
            'prefijo' => 'pfx_revocada',
            'token_hash' => hash('sha256', 'secreto-revocada'),
            'scopes' => ['inventario:leer'],
            'revocada_at' => now(),
        ]);

        $response = $this->getJson('/api/internal/web/integraciones/resumen', $this->firmarGet());

        $response->assertStatus(200);
        $fila = collect($response->json('empresas'))->firstWhere('empresa_id', $empresa->id);

        $this->assertNotNull($fila);
        $this->assertSame(1, $fila['api_keys_activas']);
        $this->assertSame('BAJA_T60', $fila['sii']['nivel_alerta']);
        $this->assertFalse($fila['previred']['integrado']);
    }

    public function test_sii_retorna_null_si_la_empresa_no_tiene_certificado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $response = $this->getJson("/api/internal/web/empresas/{$empresa->id}/integraciones/sii", $this->firmarGet());

        $response->assertStatus(200)->assertJsonPath('certificado', null);
    }

    public function test_sii_retorna_nivel_de_alerta_del_certificado_activo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $this->crearCertificado($empresa->id, ['valido_hasta' => now()->addDays(5)]);

        $response = $this->getJson("/api/internal/web/empresas/{$empresa->id}/integraciones/sii", $this->firmarGet());

        $response->assertStatus(200)->assertJsonPath('certificado.nivel_alerta', 'CRITICA_T7');
    }

    public function test_sii_empresa_inexistente_retorna_404(): void
    {
        $response = $this->getJson('/api/internal/web/empresas/999999/integraciones/sii', $this->firmarGet());

        $response->assertStatus(404);
    }

    public function test_api_keys_lista_las_de_la_empresa_sin_exponer_token_hash(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        IntegracionApiKey::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Key test',
            'prefijo' => 'pfx_test',
            'token_hash' => hash('sha256', 'secreto'),
            'scopes' => ['inventario:leer'],
        ]);

        $response = $this->getJson("/api/internal/web/empresas/{$empresa->id}/integraciones/api-keys", $this->firmarGet());

        $response->assertStatus(200);
        $data = $response->json('api_keys');
        $this->assertCount(1, $data);
        $this->assertArrayNotHasKey('token_hash', $data[0]);
    }

    public function test_crear_api_key_emite_token_una_sola_vez(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $payload = ['nombre' => 'Nueva key', 'scopes' => ['inventario:leer']];

        $response = $this->postJson(
            "/api/internal/web/empresas/{$empresa->id}/integraciones/api-keys",
            $payload,
            $this->firmarConCuerpo($payload)
        );

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('integracion_api_keys', [
            'empresa_id' => $empresa->id,
            'nombre' => 'Nueva key',
        ]);
    }

    public function test_crear_api_key_con_scope_invalido_es_rechazado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $payload = ['nombre' => 'Key', 'scopes' => ['scope:inexistente']];

        $response = $this->postJson(
            "/api/internal/web/empresas/{$empresa->id}/integraciones/api-keys",
            $payload,
            $this->firmarConCuerpo($payload)
        );

        $response->assertStatus(422);
    }

    public function test_rotar_api_key_invalida_el_token_anterior(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $key = IntegracionApiKey::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Key a rotar',
            'prefijo' => 'pfx_original',
            'token_hash' => hash('sha256', 'secreto-original'),
            'scopes' => ['inventario:leer'],
        ]);

        $response = $this->postJson(
            "/api/internal/web/empresas/{$empresa->id}/integraciones/api-keys/{$key->id}/rotar",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('token'));
        $key->refresh();
        $this->assertNotSame('pfx_original', $key->prefijo);
    }

    public function test_revocar_api_key_la_marca_revocada(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $key = IntegracionApiKey::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Key a revocar',
            'prefijo' => 'pfx_revocar',
            'token_hash' => hash('sha256', 'secreto'),
            'scopes' => ['inventario:leer'],
        ]);

        $response = $this->deleteJson(
            "/api/internal/web/empresas/{$empresa->id}/integraciones/api-keys/{$key->id}",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(200);
        $this->assertNotNull($key->fresh()->revocada_at);
    }

    public function test_revocar_api_key_de_otra_empresa_retorna_404(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();
        $key = IntegracionApiKey::create([
            'empresa_id' => $empresaA->id,
            'nombre' => 'Key de A',
            'prefijo' => 'pfx_de_a',
            'token_hash' => hash('sha256', 'secreto'),
            'scopes' => ['inventario:leer'],
        ]);

        $response = $this->deleteJson(
            "/api/internal/web/empresas/{$empresaB->id}/integraciones/api-keys/{$key->id}",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(404);
        $this->assertNull($key->fresh()->revocada_at);
    }
}
