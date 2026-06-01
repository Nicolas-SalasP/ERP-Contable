<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteXsdValidatorTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private DteXsdValidator $validator;
    private DteXmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->validator = new DteXsdValidator();
        $this->builder   = new DteXmlBuilder($this->validator);
    }

    private function dteValido(): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->factura()->create([
            'emisor_acteco'   => 471910,
            'emisor_giro'     => 'Comercio General',
            'emisor_direccion' => 'Calle 1',
            'emisor_comuna'   => 'Santiago',
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Producto test',
        ]);

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);
    }

    public function test_valida_xml_dte_correcto_no_lanza(): void
    {
        $xml = $this->builder->build($this->dteValido());

        // No debe lanzar (el builder ya valida internamente, llamada explicita es no-op)
        $this->validator->validar($xml);
        $this->assertTrue(true);
    }

    public function test_lanza_si_falta_nodo_obligatorio(): void
    {
        $xmlIncompleto = '<?xml version="1.0" encoding="ISO-8859-1"?><DTE xmlns="http://www.sii.cl/SiiDte" version="1.0"></DTE>';

        $this->expectException(DteXmlInvalidException::class);
        $this->validator->validar($xmlIncompleto);
    }

    public function test_lanza_si_atributo_version_falta(): void
    {
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?><DTE xmlns="http://www.sii.cl/SiiDte"><Documento ID="D1"></Documento></DTE>';

        $this->expectException(DteXmlInvalidException::class);
        $this->validator->validar($xml);
    }

    public function test_obtener_errores_retorna_vacio_si_valido(): void
    {
        $xml = $this->builder->build($this->dteValido());
        $this->assertSame([], $this->validator->obtenerErrores($xml));
    }

    public function test_obtener_errores_retorna_estructura_libxml_si_invalido(): void
    {
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?><DTE xmlns="http://www.sii.cl/SiiDte" version="1.0"></DTE>';
        $errores = $this->validator->obtenerErrores($xml);

        $this->assertNotEmpty($errores);
        $this->assertInstanceOf(\LibXMLError::class, $errores[0]);
    }
}
