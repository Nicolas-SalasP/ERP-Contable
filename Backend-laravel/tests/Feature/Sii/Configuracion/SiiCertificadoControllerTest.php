<?php

namespace Tests\Feature\Sii\Configuracion;

use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCertificadoControllerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->pathOpensslConfig() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado: imposible generar .pfx de prueba.');
        }
    }

    public function test_post_sube_certificado_y_retorna_201_con_metadata_sin_pfx(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $pfx     = $this->crearPfxDePrueba('mi_pwd', 'Empresa Test 76086428-5');
        $archivo = UploadedFile::fake()->createWithContent('cert.pfx', $pfx);

        $response = $this->post(
            '/api/sii/certificado',
            ['archivo' => $archivo, 'password' => 'mi_pwd'],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'empresa_id', 'subject_rut', 'subject_common_name',
                'issuer_common_name', 'valido_desde', 'valido_hasta',
                'fingerprint_sha256', 'estado',
            ])
            ->assertJsonMissing(['pfx_cifrado'])
            ->assertJsonMissing(['password_cifrada']);

        $this->assertSame('76086428-5', $response->json('subject_rut'));
        $this->assertSame('activo', $response->json('estado'));
    }

    public function test_post_rechaza_archivo_no_pfx_con_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $archivo = UploadedFile::fake()->createWithContent('cert.txt', 'no es pfx');

        $this->post(
            '/api/sii/certificado',
            ['archivo' => $archivo, 'password' => 'pwd'],
            ['Accept' => 'application/json']
        )->assertStatus(422)
         ->assertJsonValidationErrors('archivo');
    }

    public function test_post_rechaza_archivo_demasiado_grande_con_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        // 60 KB > 50 KB limite
        $archivo = UploadedFile::fake()->createWithContent('cert.pfx', str_repeat('A', 60 * 1024));

        $this->post(
            '/api/sii/certificado',
            ['archivo' => $archivo, 'password' => 'pwd'],
            ['Accept' => 'application/json']
        )->assertStatus(422)
         ->assertJsonValidationErrors('archivo');
    }

    public function test_post_rechaza_password_incorrecto_con_422_mensaje_claro(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $pfx     = $this->crearPfxDePrueba('correcta');
        $archivo = UploadedFile::fake()->createWithContent('cert.pfx', $pfx);

        $response = $this->post(
            '/api/sii/certificado',
            ['archivo' => $archivo, 'password' => 'incorrecta'],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422)
            ->assertJsonStructure(['mensaje', 'motivo'])
            ->assertJson(['motivo' => 'password_incorrecta']);
    }

    public function test_get_devuelve_404_si_no_hay_cert_activo(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/certificado')->assertStatus(404);
    }

    public function test_get_devuelve_metadata_del_activo(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->cargarCertActivo($empresa->id);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/certificado')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'activo')
            ->assertJsonMissing(['pfx_cifrado'])
            ->assertJsonMissing(['password_cifrada']);
    }

    public function test_delete_revoca_cert_pero_no_lo_elimina(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $cert = $this->cargarCertActivo($empresa->id);

        Sanctum::actingAs($usuario);

        $this->deleteJson("/api/sii/certificado/{$cert->id}")
            ->assertStatus(204);

        $this->assertNotNull(SiiCertificadoEmpresa::find($cert->id));
        $this->assertSame('revocado', $cert->fresh()->estado);
    }

    public function test_delete_falla_404_para_cert_de_otra_empresa_idor(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB]            = $this->crearEmpresaConAdmin();

        $certDeB = $this->cargarCertActivo($empresaB->id);

        Sanctum::actingAs($usuarioA);

        $this->deleteJson("/api/sii/certificado/{$certDeB->id}")
            ->assertStatus(404);

        $this->assertSame('activo', $certDeB->fresh()->estado);
    }

    public function test_verificar_retorna_integridad_ok_para_cert_valido(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->cargarCertActivo($empresa->id);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/sii/certificado/verificar')
            ->assertStatus(200)
            ->assertJson(['integridad_ok' => true]);
    }

    public function test_subir_nuevo_cert_pasa_el_anterior_a_cuarentena(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $certViejo = $this->cargarCertActivo($empresa->id);

        Sanctum::actingAs($usuario);

        $pfxNuevo = $this->crearPfxDePrueba('pwd_nuevo');
        $archivo  = UploadedFile::fake()->createWithContent('nuevo.pfx', $pfxNuevo);

        $this->post(
            '/api/sii/certificado',
            ['archivo' => $archivo, 'password' => 'pwd_nuevo'],
            ['Accept' => 'application/json']
        )->assertStatus(201);

        $this->assertSame('cuarentena', $certViejo->fresh()->estado);
    }

    public function test_aislamiento_multitenant_no_permite_ver_cert_de_otra_empresa(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB]            = $this->crearEmpresaConAdmin();

        $certDeB = $this->cargarCertActivo($empresaB->id);

        Sanctum::actingAs($usuarioA);

        // Usuario A no tiene cert activo propio → 404, no le exponen el de B.
        $response = $this->getJson('/api/sii/certificado');
        $response->assertStatus(404);
    }

    public function test_endpoints_requieren_autenticacion_401(): void
    {
        $this->getJson('/api/sii/certificado')->assertStatus(401);
        $this->postJson('/api/sii/certificado/verificar')->assertStatus(401);
        $this->deleteJson('/api/sii/certificado/1')->assertStatus(401);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function cargarCertActivo(int $empresaId): SiiCertificadoEmpresa
    {
        $pfx = $this->crearPfxDePrueba('pwd_inicial', 'Empresa Test 76086428-5');

        return app(CertificadoService::class)->cargar($empresaId, $pfx, 'pwd_inicial');
    }

    private function pathOpensslConfig(): ?string
    {
        $candidatos = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            'C:\\xampp\\apache\\conf\\openssl.cnf',
            'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/local/etc/openssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
        ]);

        foreach ($candidatos as $p) {
            if (is_string($p) && file_exists($p)) {
                return $p;
            }
        }

        return null;
    }

    private function crearPfxDePrueba(string $password, string $cn = 'Empresa Test 76086428-5'): string
    {
        $config = [
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config'           => $this->pathOpensslConfig(),
        ];

        $pkey = openssl_pkey_new($config);
        $csr  = openssl_csr_new(['commonName' => $cn], $pkey, $config);
        $cert = openssl_csr_sign($csr, null, $pkey, 365, $config);

        $pfxBinary = '';
        if (! openssl_pkcs12_export($cert, $pfxBinary, $pkey, $password)) {
            $this->fail('No se pudo generar .pfx de prueba: ' . openssl_error_string());
        }

        return $pfxBinary;
    }
}
