<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CafServiceCargarTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private CafService $service;
    private string $rutEmpresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = new CafService(new CafXmlParser());

        $num = 76_123_456;
        $this->rutEmpresa = $num . '-' . RutHelper::calcularDv($num);
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut'          => $this->rutEmpresa,
            'razon_social' => 'Empresa CAF Test',
        ]);
    }

    private function xmlCaf(string $rut = null, string $idk = '300', int $tipo = 33, int $desde = 1, int $hasta = 50): string
    {
        $rut = $rut ?? $this->rutEmpresa;

        return <<<XML
<?xml version="1.0"?>
<AUTORIZACION>
  <CAF version="1.0">
    <DA>
      <RE>{$rut}</RE>
      <RS>EMPRESA CAF TEST</RS>
      <TD>{$tipo}</TD>
      <RNG><D>{$desde}</D><H>{$hasta}</H></RNG>
      <FA>2026-01-15</FA>
      <RSAPK><M>M</M><E>E</E></RSAPK>
      <IDK>{$idk}</IDK>
    </DA>
    <FRMA algoritmo="SHA1withRSA">FIRMA_DUMMY</FRMA>
  </CAF>
  <RSASK>-----BEGIN RSA PRIVATE KEY-----
DUMMY_PRIV
-----END RSA PRIVATE KEY-----</RSASK>
  <RSAPUBK>-----BEGIN PUBLIC KEY-----
DUMMY_PUB
-----END PUBLIC KEY-----</RSAPUBK>
</AUTORIZACION>
XML;
    }

    public function test_cargar_caf_valido_persiste_con_rsa_cifrada(): void
    {
        $empresa = $this->crearEmpresa();

        $caf = $this->service->cargar($empresa->id, $this->xmlCaf());

        $this->assertNotNull($caf->id);
        $this->assertSame(33, $caf->tipo_dte);
        $this->assertSame(1, $caf->folio_desde);
        $this->assertSame(50, $caf->folio_hasta);
        $this->assertSame('300', $caf->sii_idk);
        $this->assertSame(SiiCaf::ESTADO_ACTIVO, $caf->estado);
    }

    public function test_rsa_sk_cifrada_no_es_legible_directamente_en_db(): void
    {
        $empresa = $this->crearEmpresa();
        $caf     = $this->service->cargar($empresa->id, $this->xmlCaf());

        $crudo = DB::table('sii_caf')->where('id', $caf->id)->first();

        $this->assertStringNotContainsString('DUMMY_PRIV', $crudo->rsa_sk_cifrada);
        $this->assertStringContainsString('DUMMY_PRIV', Crypt::decryptString($crudo->rsa_sk_cifrada));
    }

    public function test_xml_completo_se_persiste_cifrado(): void
    {
        $empresa = $this->crearEmpresa();
        $xml     = $this->xmlCaf();
        $caf     = $this->service->cargar($empresa->id, $xml);

        $crudo = DB::table('sii_caf')->where('id', $caf->id)->first();

        $this->assertStringNotContainsString('<AUTORIZACION>', $crudo->xml_completo_cifrado);
        $this->assertSame($xml, Crypt::decryptString($crudo->xml_completo_cifrado));
    }

    public function test_falla_si_rut_caf_no_coincide_con_empresa(): void
    {
        $empresa = $this->crearEmpresa(); // 76123456-7
        $otroRut = '99999999-' . RutHelper::calcularDv(99_999_999);

        $this->expectException(CafInvalidoException::class);
        $this->service->cargar($empresa->id, $this->xmlCaf($otroRut));
    }

    public function test_falla_si_caf_con_mismo_sii_idk_ya_existe(): void
    {
        $empresa = $this->crearEmpresa();
        $this->service->cargar($empresa->id, $this->xmlCaf(null, '300'));

        $this->expectException(CafInvalidoException::class);
        $this->service->cargar($empresa->id, $this->xmlCaf(null, '300', 33, 100, 200));
    }

    public function test_falla_si_empresa_no_existe(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->service->cargar(99_999, $this->xmlCaf());
    }

    public function test_hidden_no_expone_rsa_ni_xml_en_json(): void
    {
        $empresa = $this->crearEmpresa();
        $caf     = $this->service->cargar($empresa->id, $this->xmlCaf());

        $json = json_decode($caf->toJson(), true);

        $this->assertArrayNotHasKey('rsa_sk_cifrada', $json);
        $this->assertArrayNotHasKey('xml_completo_cifrado', $json);
        $this->assertArrayHasKey('rsa_pubk', $json);
        $this->assertArrayHasKey('firma_caf', $json);
    }

    public function test_folio_actual_inicia_en_folio_desde(): void
    {
        $empresa = $this->crearEmpresa();
        $caf     = $this->service->cargar($empresa->id, $this->xmlCaf(null, '301', 33, 100, 200));

        $this->assertSame(100, $caf->folio_actual);
        $this->assertSame(100, $caf->folio_desde);
        $this->assertSame(200, $caf->folio_hasta);
    }

    public function test_estado_inicial_es_activo_y_contadores_en_cero(): void
    {
        $empresa = $this->crearEmpresa();
        $caf     = $this->service->cargar($empresa->id, $this->xmlCaf());

        $this->assertSame('activo', $caf->estado);
        $this->assertSame(0, $caf->folios_usados);
        $this->assertSame(0, $caf->folios_huerfanos);
    }

    public function test_extraer_rsa_sk_descifra_correctamente(): void
    {
        $empresa = $this->crearEmpresa();
        $caf     = $this->service->cargar($empresa->id, $this->xmlCaf());

        $pem = $this->service->extraerRsaSk($caf);

        $this->assertStringContainsString('BEGIN RSA PRIVATE KEY', $pem);
        $this->assertStringContainsString('DUMMY_PRIV', $pem);
    }
}
