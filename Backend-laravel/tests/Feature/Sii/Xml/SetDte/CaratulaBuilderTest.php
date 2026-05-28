<?php

namespace Tests\Feature\Sii\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Xml\SetDte\CaratulaBuilder;
use App\Domains\Sii\Support\RutHelper;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CaratulaBuilderTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private CaratulaBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        $this->builder = app(CaratulaBuilder::class);
    }

    private function empresaConfigurada(string $rut = '76123456-7'): Empresa
    {
        return Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA CARATULA',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
    }

    private function dteFake(Empresa $empresa, int $tipo = 33): SiiDteEmitido
    {
        return SiiDteEmitido::factory()->create([
            'empresa_id' => $empresa->id,
            'tipo_dte'   => $tipo,
            'emisor_rut' => $empresa->rut,
        ]);
    }

    private function dom(): DOMDocument
    {
        return new DOMDocument('1.0', 'ISO-8859-1');
    }

    private function xpathSobreCaratula(\DOMElement $caratula): DOMXPath
    {
        $dom = $caratula->ownerDocument;
        return new DOMXPath($dom);
    }

    public function test_construye_Caratula_con_RutEmisor_correcto(): void
    {
        $empresa = $this->empresaConfigurada('76555444-3');
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);

        $dom = $this->dom();
        $dom->appendChild($this->builder->build($dom, $empresa, [$this->dteFake($empresa)]));

        $x = new DOMXPath($dom);
        $this->assertSame('76555444-3', $x->evaluate('string(/Caratula/RutEmisor)'));
    }

    public function test_RutEnvia_se_extrae_del_subject_del_certificado(): void
    {
        $empresa = $this->empresaConfigurada('76123456-7');
        $rutCertificado = '11111111-1';
        $this->crearCertActivoParaEmpresa($empresa, 'OPERADOR ' . $rutCertificado);

        $dom = $this->dom();
        $dom->appendChild($this->builder->build($dom, $empresa, [$this->dteFake($empresa)]));

        $x = new DOMXPath($dom);
        $rutEnvia = $x->evaluate('string(/Caratula/RutEnvia)');
        // El extractor normaliza puntos y guion; debe ser el RUT del cert, no el de la empresa.
        $this->assertSame(RutHelper::normalizar($rutCertificado), $rutEnvia);
        $this->assertNotSame($empresa->rut, $rutEnvia);
    }

    public function test_RutReceptor_es_60803000_K_constante(): void
    {
        $empresa = $this->empresaConfigurada();
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);

        $dom = $this->dom();
        $dom->appendChild($this->builder->build($dom, $empresa, [$this->dteFake($empresa)]));

        $x = new DOMXPath($dom);
        $this->assertSame('60803000-K', $x->evaluate('string(/Caratula/RutReceptor)'));
        $this->assertSame('60803000-K', CaratulaBuilder::RUT_RECEPTOR_SII);
    }

    public function test_SubTotDTE_agrupa_por_tipo_y_cuenta_correctamente(): void
    {
        $empresa = $this->empresaConfigurada();
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);

        $dtes = [
            $this->dteFake($empresa, 33),
            $this->dteFake($empresa, 33),
            $this->dteFake($empresa, 33),
            $this->dteFake($empresa, 61),
        ];

        $dom = $this->dom();
        $dom->appendChild($this->builder->build($dom, $empresa, $dtes));

        $x = new DOMXPath($dom);
        $sub33Tipo = $x->evaluate('string(/Caratula/SubTotDTE[1]/TpoDTE)');
        $sub33Nro  = $x->evaluate('string(/Caratula/SubTotDTE[1]/NroDTE)');
        $sub61Tipo = $x->evaluate('string(/Caratula/SubTotDTE[2]/TpoDTE)');
        $sub61Nro  = $x->evaluate('string(/Caratula/SubTotDTE[2]/NroDTE)');

        $this->assertSame('33', $sub33Tipo);
        $this->assertSame('3', $sub33Nro);
        $this->assertSame('61', $sub61Tipo);
        $this->assertSame('1', $sub61Nro);
    }

    public function test_lanza_si_empresa_no_tiene_resolucion_sii(): void
    {
        $empresa = Empresa::create([
            'rut'          => '76777888-K',
            'razon_social' => 'SIN RESOLUCION',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);

        $this->expectException(DteXmlInvalidException::class);
        $this->expectExceptionMessage('resolucion_sii_numero');

        $this->builder->build($this->dom(), $empresa, [$this->dteFake($empresa)]);
    }
}
