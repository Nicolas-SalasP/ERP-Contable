<?php

namespace Tests\Feature\Sii\Xml\Ted;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Caf\CafSerializerService;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use App\Domains\Sii\Services\Xml\Ted\TedBuilder;
use App\Domains\Sii\Services\Xml\Ted\TedSignerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use LogicException;
use Tests\Concerns\GeneraParRsaParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class TedBuilderTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraParRsaParaTests;

    private TedBuilder $builder;
    private TedSignerService $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $cafService    = new CafService(new CafXmlParser());
        $this->signer  = new TedSignerService($cafService);
        $this->builder = new TedBuilder(new CafSerializerService(), $this->signer);
    }

    private function xmlCafReal(int $tipoDte = 33, int $desde = 1, int $hasta = 50): string
    {
        return <<<XML
<?xml version="1.0"?>
<AUTORIZACION>
  <CAF version="1.0">
    <DA>
      <RE>76123456-7</RE>
      <RS>EMPRESA TED TEST</RS>
      <TD>{$tipoDte}</TD>
      <RNG><D>{$desde}</D><H>{$hasta}</H></RNG>
      <FA>2026-01-15</FA>
      <RSAPK><M>MMMM</M><E>Aw==</E></RSAPK>
      <IDK>123</IDK>
    </DA>
    <FRMA algoritmo="SHA1withRSA">RklSTUFfREVMX1NJSV9CQVNFNjQ=</FRMA>
  </CAF>
</AUTORIZACION>
XML;
    }

    private function cafConPar(string $pemSk, string $pemPk, int $tipoDte = 33, int $desde = 1, int $hasta = 50): SiiCaf
    {
        return SiiCaf::factory()->create([
            'tipo_dte'             => $tipoDte,
            'folio_desde'          => $desde,
            'folio_hasta'          => $hasta,
            'folio_actual'         => $desde,
            'rsa_sk_cifrada'       => Crypt::encryptString($pemSk),
            'rsa_pubk'             => $pemPk,
            'xml_completo_cifrado' => Crypt::encryptString($this->xmlCafReal($tipoDte, $desde, $hasta)),
        ]);
    }

    private function dteConDetalle(int $tipoDte = 33, int $folio = 10): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->create([
            'tipo_dte'    => $tipoDte,
            'folio'       => $folio,
            'monto_total' => 1190,
            'monto_neto'  => 1000,
            'iva'         => 190,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Producto X',
        ]);

        return $dte->fresh(['detalles']);
    }

    public function test_build_firmado_retorna_ted_con_version(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteConDetalle();

        $ted = $this->builder->buildFirmado($dte, $caf);

        $this->assertStringStartsWith('<TED version="1.0">', $ted);
        $this->assertStringEndsWith('</TED>', $ted);
    }

    public function test_build_firmado_contiene_dd_con_orden_correcto(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteConDetalle();

        $ted = $this->builder->buildFirmado($dte, $caf);

        $posRE    = strpos($ted, '<RE>');
        $posTD    = strpos($ted, '<TD>');
        $posF     = strpos($ted, '<F>');
        $posFE    = strpos($ted, '<FE>');
        $posRR    = strpos($ted, '<RR>');
        $posRSR   = strpos($ted, '<RSR>');
        $posMNT   = strpos($ted, '<MNT>');
        $posIT1   = strpos($ted, '<IT1>');
        $posCAF   = strpos($ted, '<CAF ');
        $posTSTED = strpos($ted, '<TSTED>');

        $this->assertNotFalse($posRE);
        $this->assertLessThan($posTD,    $posRE);
        $this->assertLessThan($posF,     $posTD);
        $this->assertLessThan($posFE,    $posF);
        $this->assertLessThan($posRR,    $posFE);
        $this->assertLessThan($posRSR,   $posRR);
        $this->assertLessThan($posMNT,   $posRSR);
        $this->assertLessThan($posIT1,   $posMNT);
        $this->assertLessThan($posCAF,   $posIT1);
        $this->assertLessThan($posTSTED, $posCAF);
    }

    public function test_build_firmado_contiene_frmt_con_algoritmo_y_firma_base64(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteConDetalle();

        $ted = $this->builder->buildFirmado($dte, $caf);

        $this->assertMatchesRegularExpression(
            '#<FRMT algoritmo="SHA1withRSA">[A-Za-z0-9+/=]+</FRMT>#',
            $ted
        );
    }

    public function test_firma_del_ted_verifica_contra_rsa_pubk_del_caf(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteConDetalle();

        $ted = $this->builder->buildFirmado($dte, $caf);

        // Extraer DD y FRMT del TED real (los bytes exactos firmados).
        $okDd = preg_match('#(<DD>.*</DD>)#s', $ted, $mDd);
        $okFr = preg_match('#<FRMT algoritmo="SHA1withRSA">([A-Za-z0-9+/=]+)</FRMT>#', $ted, $mFr);

        $this->assertSame(1, $okDd);
        $this->assertSame(1, $okFr);

        $this->assertTrue(
            $this->signer->verificarFirma($mDd[1], $mFr[1], $caf),
            'La firma del TED debe verificar contra rsa_pubk del CAF.'
        );
    }

    public function test_dd_se_construye_en_iso_8859_1(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);

        $dte = SiiDteEmitido::factory()->create([
            'tipo_dte'              => 33,
            'folio'                 => 5,
            'receptor_razon_social' => 'Compañía Niños Ácida',
            'monto_total'           => 100,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Pingüino ñ',
        ]);
        $dte = $dte->fresh(['detalles']);

        $ted = $this->builder->buildFirmado($dte, $caf);

        // El TED esta en bytes ISO-8859-1: caracteres acentuados ocupan 1 byte.
        // En UTF-8 ocuparian 2 bytes; verificamos no apareciendo secuencias UTF-8
        // de los caracteres usados.
        $this->assertStringNotContainsString("\xC3\xB1", $ted, 'No debe haber ñ en UTF-8 (0xC3 0xB1).');
        $this->assertStringNotContainsString("\xC3\xA1", $ted, 'No debe haber á en UTF-8 (0xC3 0xA1).');
    }

    public function test_falla_si_tipo_dte_del_caf_no_coincide_con_dte(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $cafTipo33 = $this->cafConPar($sk, $pk, 33);
        $dteTipo39 = $this->dteConDetalle(39);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('tipo_dte');

        $this->builder->buildFirmado($dteTipo39, $cafTipo33);
    }

    public function test_falla_si_folio_dte_esta_fuera_del_rango_del_caf(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk, 33, 1, 10);
        $dte = $this->dteConDetalle(33, 999);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('rango');

        $this->builder->buildFirmado($dte, $caf);
    }

    public function test_dos_invocaciones_con_mismo_dte_pueden_diferir_solo_en_tsted(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteConDetalle();

        $ted1 = $this->builder->buildFirmado($dte, $caf);
        $ted2 = $this->builder->buildFirmado($dte, $caf);

        // Quitamos TSTED de ambos: el resto debe ser identico (mismo DD => misma firma).
        $sinTsted = fn (string $s) => preg_replace('#<TSTED>[^<]+</TSTED>#', '<TSTED/>', $s);

        $this->assertSame($sinTsted($ted1), $sinTsted($ted2));
    }

    public function test_bloque_caf_del_dd_no_contiene_rsask(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteConDetalle();

        $ted = $this->builder->buildFirmado($dte, $caf);

        $this->assertStringNotContainsString('<RSASK>', $ted);
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $ted);
    }
}
