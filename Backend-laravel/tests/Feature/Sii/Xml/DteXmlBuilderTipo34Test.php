<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteXmlBuilderTipo34Test extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private DteXmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->builder = new DteXmlBuilder(new DteXsdValidator());
    }

    private function facturaExenta(): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->create([
            'tipo_dte'         => SiiDteEmitido::TIPO_FACTURA_EXENTA,
            'monto_neto'       => 0,
            'iva'              => 0,
            'monto_exento'     => 5000,
            'monto_total'      => 5000,
            'emisor_acteco'    => 471910,
            'emisor_giro'      => 'Servicios exentos',
            'emisor_direccion' => 'Calle 1',
            'emisor_comuna'    => 'Santiago',
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id'  => $dte->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Servicio exento',
            'exento'          => true,
            'cantidad'        => 1,
            'precio_unitario' => 5000,
            'monto_item'      => 5000,
        ]);

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera']);
    }

    public function test_factura_exenta_usa_MntExe_no_MntNeto(): void
    {
        $xml = $this->builder->build($this->facturaExenta());

        $this->assertStringContainsString('<MntExe>5000</MntExe>', $xml);
        $this->assertStringNotContainsString('<MntNeto>', $xml);
    }

    public function test_factura_exenta_no_incluye_TasaIVA_ni_IVA(): void
    {
        $xml = $this->builder->build($this->facturaExenta());

        $this->assertStringNotContainsString('<TasaIVA>', $xml);
        $this->assertStringNotContainsString('<IVA>', $xml);
    }

    public function test_pasa_xsd(): void
    {
        $xml = $this->builder->build($this->facturaExenta());
        $this->assertNotEmpty($xml);
    }
}
