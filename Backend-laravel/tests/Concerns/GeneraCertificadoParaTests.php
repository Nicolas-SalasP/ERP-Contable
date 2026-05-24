<?php

namespace Tests\Concerns;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use Illuminate\Support\Facades\Crypt;

/**
 * Genera certificados digitales self-signed y los persiste como
 * SiiCertificadoEmpresa activo, listos para que los services de F4.3
 * los consuman via CertificadoService::extraerParPemDeEmpresa.
 *
 * Requiere [[GeneraParRsaParaTests]] para resolucion del openssl.cnf en XAMPP.
 */
trait GeneraCertificadoParaTests
{
    use GeneraParRsaParaTests;

    /**
     * Crea un .pfx self-signed con CN dado (tipico: "EMPRESA TEST 76123456-7").
     * Persiste el cert como activo para $empresa y retorna [$certPem, $privKeyPem].
     *
     * @return array{0: SiiCertificadoEmpresa, 1: string, 2: string}
     */
    protected function crearCertActivoParaEmpresa(Empresa $empresa, ?string $cn = null): array
    {
        $cn = $cn ?? ('TEST EMPRESA ' . $empresa->rut);

        $configPath = $this->localizarOpensslCnf();
        $config = ['digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if ($configPath !== null) {
            $config['config'] = $configPath;
        }

        $pkey = openssl_pkey_new($config);
        if ($pkey === false) {
            $this->fail('No se pudo generar par RSA para cert. Error: ' . (string) openssl_error_string());
        }

        $csr = openssl_csr_new(['commonName' => $cn], $pkey, $config);
        if ($csr === false) {
            $this->fail('openssl_csr_new fallo. Error: ' . (string) openssl_error_string());
        }

        $cert = openssl_csr_sign($csr, null, $pkey, 365, $config);
        if ($cert === false) {
            $this->fail('openssl_csr_sign fallo. Error: ' . (string) openssl_error_string());
        }

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($pkey, $privKeyPem, null, $configPath !== null ? ['config' => $configPath] : null);

        $pfxBinary = '';
        if (! openssl_pkcs12_export($cert, $pfxBinary, $pkey, 'pwd_test_123')) {
            $this->fail('openssl_pkcs12_export fallo: ' . (string) openssl_error_string());
        }

        $certService = app(CertificadoService::class);
        $modelo = $certService->cargar($empresa->id, $pfxBinary, 'pwd_test_123');

        return [$modelo, $certPem, $privKeyPem];
    }

    /**
     * Crea un CAF activo con par RSA real para una empresa+tipo+rango.
     * El xml_completo_cifrado embebe un bloque <CAF> minimo pero parseable.
     *
     * @return array{0: \App\Domains\Sii\Models\SiiCaf, 1: string, 2: string}
     */
    protected function crearCafActivoParaEmpresa(
        Empresa $empresa,
        int $tipoDte = 33,
        int $desde = 1,
        int $hasta = 50
    ): array {
        [$skCaf, $pkCaf] = $this->generarParRsa();

        $rut = $empresa->rut;
        $xmlCaf = <<<XML
<?xml version="1.0"?>
<AUTORIZACION>
  <CAF version="1.0">
    <DA>
      <RE>{$rut}</RE>
      <RS>EMPRESA CAF TEST</RS>
      <TD>{$tipoDte}</TD>
      <RNG><D>{$desde}</D><H>{$hasta}</H></RNG>
      <FA>2026-01-15</FA>
      <RSAPK><M>MMMM</M><E>Aw==</E></RSAPK>
      <IDK>{$this->idkAleatorio()}</IDK>
    </DA>
    <FRMA algoritmo="SHA1withRSA">RklSTUFfQ0FGX1RFU1Q=</FRMA>
  </CAF>
</AUTORIZACION>
XML;

        $caf = SiiCaf::factory()->create([
            'empresa_id'           => $empresa->id,
            'tipo_dte'             => $tipoDte,
            'folio_desde'          => $desde,
            'folio_hasta'          => $hasta,
            'folio_actual'         => $desde,
            'rut_empresa_caf'      => $rut,
            'rsa_sk_cifrada'       => Crypt::encryptString($skCaf),
            'xml_completo_cifrado' => Crypt::encryptString($xmlCaf),
            'rsa_pubk'             => $pkCaf,
        ]);

        return [$caf, $skCaf, $pkCaf];
    }

    private function idkAleatorio(): int
    {
        return random_int(100, 999999);
    }
}
