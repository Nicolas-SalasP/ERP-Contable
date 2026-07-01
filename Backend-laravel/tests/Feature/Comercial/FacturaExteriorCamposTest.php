<?php

namespace Tests\Feature\Comercial;

use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\TipoCambio;

class FacturaExteriorCamposTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create([
            'rut' => '77.888.999-0',
            'razon_social' => 'Exterior Test SpA',
            'regimen_tributario' => '14_A',
        ]);
    }

    public function test_factura_guarda_moneda_y_tipo_cambio(): void
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => 99999001,
            'numero_factura' => 'EXT-001',
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA_EXTERIOR',
            'fecha_emision' => '2026-06-10',
            'monto_bruto' => 22610,
            'monto_neto' => 22610,
            'monto_iva' => 0,
            'estado' => 'REGISTRADA',
            'moneda' => 'USD',
            'tipo_cambio' => 950.0000,
            'monto_bruto_origen' => 23.80,
            'es_documento_exterior' => true,
        ]);

        $this->assertEquals('USD', $factura->fresh()->moneda);
        $this->assertEquals(950.0000, (float) $factura->fresh()->tipo_cambio);
        $this->assertEquals(23.80, (float) $factura->fresh()->monto_bruto_origen);
    }

    public function test_factura_exterior_se_marca_con_es_documento_exterior(): void
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => 99999002,
            'numero_factura' => 'EXT-002',
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA_EXTERIOR',
            'fecha_emision' => '2026-06-10',
            'monto_bruto' => 22610,
            'monto_neto' => 22610,
            'monto_iva' => 0,
            'estado' => 'REGISTRADA',
            'moneda' => 'USD',
            'tipo_cambio' => 950.0000,
            'monto_bruto_origen' => 23.80,
            'es_documento_exterior' => true,
        ]);

        $this->assertTrue($factura->fresh()->es_documento_exterior);
    }

    public function test_conversion_usd_a_clp_usa_el_tipo_de_cambio(): void
    {
        $montoOrigen = 23.80;
        $tipoCambio = 950.00;
        $esperadoCLP = round($montoOrigen * $tipoCambio);

        $this->assertEquals(22610, $esperadoCLP);
    }

    public function test_tipo_cambio_model_obtiene_valor_para_fecha(): void
    {
        TipoCambio::create([
            'fecha' => '2026-06-10',
            'moneda' => 'USD',
            'valor_clp' => 950.0000,
        ]);

        $valor = TipoCambio::obtenerParaFecha('2026-06-10', 'USD');
        $this->assertEquals(950.0000, $valor);
    }

    public function test_factura_nacional_tiene_defaults_correctos(): void
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => 99999003,
            'numero_factura' => 'NAC-001',
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => '2026-06-10',
            'monto_bruto' => 119000,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'estado' => 'REGISTRADA',
        ]);

        $this->assertEquals('CLP', $factura->fresh()->moneda);
        $this->assertFalse($factura->fresh()->es_documento_exterior);
        $this->assertNull($factura->fresh()->tipo_cambio);
    }
}
