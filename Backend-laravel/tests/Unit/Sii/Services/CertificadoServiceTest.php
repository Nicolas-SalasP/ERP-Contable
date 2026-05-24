<?php

namespace Tests\Unit\Sii\Services;

use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * NOTA: este test vive en tests/Unit/ por la convencion de OT-F2.1, pero
 * tecnicamente es Feature porque ejecuta migraciones (RefreshDatabase) y
 * persiste en sii_certificado_empresa.
 */
class CertificadoServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private CertificadoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEntornoBase();
        $this->service = new CertificadoService();

        if ($this->pathOpensslConfig() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado: imposible generar .pfx de prueba en este entorno.');
        }
    }

    public function test_cargar_con_pfx_y_password_valido_extrae_metadatos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $pfx       = $this->crearPfxDePrueba('contrasena_123', 'Empresa Test 76086428-5');

        $cert = $this->service->cargar($empresa->id, $pfx, 'contrasena_123');

        $this->assertNotNull($cert->id);
        $this->assertSame($empresa->id, $cert->empresa_id);
        $this->assertSame('76086428-5', $cert->subject_rut);
        $this->assertSame('Empresa Test 76086428-5', $cert->subject_common_name);
        $this->assertNotNull($cert->fingerprint_sha256);
        $this->assertSame(64, strlen($cert->fingerprint_sha256));
        $this->assertSame(SiiCertificadoEmpresa::ESTADO_ACTIVO, $cert->estado);
        $this->assertTrue($cert->valido_hasta->isFuture());
    }

    public function test_cargar_con_password_incorrecto_lanza_excepcion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $pfx       = $this->crearPfxDePrueba('correcta');

        $this->expectException(CertificadoInvalidoException::class);

        $this->service->cargar($empresa->id, $pfx, 'incorrecta');
    }

    public function test_cargar_con_binario_corrupto_lanza_excepcion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->expectException(CertificadoInvalidoException::class);

        $this->service->cargar($empresa->id, 'esto no es un pkcs12 valido', 'cualquier');
    }

    public function test_cargar_reemplaza_el_activo_anterior_a_cuarentena(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $pfx1 = $this->crearPfxDePrueba('pwd1', 'Empresa Primero 76086428-5');
        $cert1 = $this->service->cargar($empresa->id, $pfx1, 'pwd1');

        $this->assertSame(SiiCertificadoEmpresa::ESTADO_ACTIVO, $cert1->estado);

        $pfx2 = $this->crearPfxDePrueba('pwd2', 'Empresa Segundo 76086428-5');
        $cert2 = $this->service->cargar($empresa->id, $pfx2, 'pwd2');

        $this->assertSame(SiiCertificadoEmpresa::ESTADO_ACTIVO, $cert2->fresh()->estado);
        $this->assertSame(SiiCertificadoEmpresa::ESTADO_CUARENTENA, $cert1->fresh()->estado);
    }

    public function test_extraer_plano_devuelve_pfx_y_password_descifrados(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $pfxOriginal = $this->crearPfxDePrueba('mi_pwd', 'Empresa Test 76086428-5');

        $cert = $this->service->cargar($empresa->id, $pfxOriginal, 'mi_pwd');

        $plano = $this->service->extraerPlano($cert);

        $this->assertSame($pfxOriginal, $plano['pfx']);
        $this->assertSame('mi_pwd', $plano['password']);
        $this->assertStringContainsString('-----BEGIN CERTIFICATE-----', $plano['cert_pem']);
        $this->assertStringContainsString('-----BEGIN', $plano['private_key_pem']);
    }

    public function test_verificar_integridad_retorna_true_para_cert_valido(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $pfx       = $this->crearPfxDePrueba('pwd');

        $cert = $this->service->cargar($empresa->id, $pfx, 'pwd');

        $this->assertTrue($this->service->verificarIntegridad($cert));
    }

    public function test_revocar_cambia_estado_pero_no_elimina_fisicamente(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $pfx       = $this->crearPfxDePrueba('pwd');
        $cert      = $this->service->cargar($empresa->id, $pfx, 'pwd');

        $idAntes = $cert->id;

        $this->service->revocar($cert);

        $this->assertSame(SiiCertificadoEmpresa::ESTADO_REVOCADO, $cert->fresh()->estado);
        $this->assertNotNull(SiiCertificadoEmpresa::find($idAntes), 'El registro no debe eliminarse fisicamente.');
    }

    public function test_hidden_fields_no_aparecen_en_to_json(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $pfx       = $this->crearPfxDePrueba('pwd');
        $cert      = $this->service->cargar($empresa->id, $pfx, 'pwd');

        $json = json_decode($cert->toJson(), true);

        $this->assertArrayNotHasKey('pfx_cifrado', $json);
        $this->assertArrayNotHasKey('password_cifrada', $json);
        $this->assertArrayHasKey('subject_common_name', $json);
        $this->assertArrayHasKey('fingerprint_sha256', $json);
    }

    public function test_cuando_subject_rut_no_coincide_con_empresa_se_logea_pero_no_falla(): void
    {
        // Empresa con rut distinto al del cert.
        [$empresa] = $this->crearEmpresaConAdmin([], []);
        $empresa->update(['rut' => '99999999-9']);

        $pfx = $this->crearPfxDePrueba('pwd', 'Empresa Otra 76086428-5');

        // No debe lanzar excepcion; el log warning queda en canal 'sii'.
        $cert = $this->service->cargar($empresa->id, $pfx, 'pwd');

        $this->assertNotNull($cert->id);
        $this->assertSame('76086428-5', $cert->subject_rut);
        $this->assertSame(SiiCertificadoEmpresa::ESTADO_ACTIVO, $cert->estado);
    }

    public function test_persistencia_cifrada_no_almacena_pfx_ni_password_en_claro(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $pfx       = $this->crearPfxDePrueba('pwd_secreto');
        $cert      = $this->service->cargar($empresa->id, $pfx, 'pwd_secreto');

        // Lectura cruda desde BD (sin pasar por $hidden).
        $crudo = \DB::table('sii_certificado_empresa')->where('id', $cert->id)->first();

        $this->assertNotEquals($pfx, $crudo->pfx_cifrado, 'El pfx NO debe persistirse en claro.');
        $this->assertNotEquals('pwd_secreto', $crudo->password_cifrada, 'La passphrase NO debe persistirse en claro.');

        // Pero debe ser descifrable con APP_KEY actual.
        $this->assertSame($pfx, Crypt::decryptString($crudo->pfx_cifrado));
        $this->assertSame('pwd_secreto', Crypt::decryptString($crudo->password_cifrada));
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

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

    private function crearPfxDePrueba(string $password, string $cn = 'Empresa Test 76086428-5', int $diasValidez = 365): string
    {
        $config = [
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config'           => $this->pathOpensslConfig(),
        ];

        $pkey = openssl_pkey_new($config);
        $csr  = openssl_csr_new(['commonName' => $cn], $pkey, $config);
        $cert = openssl_csr_sign($csr, null, $pkey, $diasValidez, $config);

        $pfxBinary = '';
        if (! openssl_pkcs12_export($cert, $pfxBinary, $pkey, $password)) {
            $this->fail('No se pudo generar .pfx de prueba: ' . openssl_error_string());
        }

        return $pfxBinary;
    }
}
