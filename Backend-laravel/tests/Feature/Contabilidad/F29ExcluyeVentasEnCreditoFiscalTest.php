<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Services\ImpuestosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class F29ExcluyeVentasEnCreditoFiscalTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private ImpuestosService $service;
    private $empresa;
    private Proveedor $proveedor;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa] = $this->crearEmpresaConAdmin();
        $this->service = app(ImpuestosService::class);

        $this->proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'rut'            => '76111222-3',
            'razon_social'   => 'Proveedor Test',
            'codigo_interno' => 'PTEST',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->cliente = Cliente::create([
            'empresa_id'  => $this->empresa->id,
            'rut'         => '12345678-9',
            'razon_social' => 'Cliente Test',
            'estado'      => 'ACTIVO',
        ]);
    }

    public function test_f29_no_incluye_facturas_de_venta_en_credito_fiscal(): void
    {
        // Factura COMPRA: debe entrar al crédito fiscal
        Factura::create([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => 'FC-001',
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => Factura::generarCodigoUnico(),
            'fecha_emision'         => '2026-06-10',
            'monto_bruto'           => 119000,
            'monto_neto'            => 100000,
            'monto_iva'             => 19000,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => false,
        ]);

        // Factura VENTA: NO debe entrar al crédito fiscal (era el bug)
        Factura::create([
            'empresa_id'    => $this->empresa->id,
            'cliente_id'    => $this->cliente->id,
            'numero_factura' => 'FV-001',
            'tipo'          => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'tipo_dte'      => 33,
            'codigo_unico'  => Factura::generarCodigoUnico(),
            'fecha_emision' => '2026-06-15',
            'monto_bruto'   => 595000,
            'monto_neto'    => 500000,
            'monto_iva'     => 95000,
            'estado'        => 'REGISTRADA',
        ]);

        $resultado = $this->service->simularF29($this->empresa->id, 6, 2026);

        $this->assertEquals(1, $resultado['compras']['cantidad'], 'Solo debe contar 1 factura de compra');
        $this->assertEquals(19000, $resultado['compras']['iva_credito'], 'IVA crédito solo de la compra, no de la venta');
        $this->assertEquals(100000, $resultado['compras']['neto'], 'Neto compras solo de la COMPRA');
    }

    public function test_f29_compras_y_ventas_mismo_periodo_no_se_mezclan(): void
    {
        // 2 compras nacionales
        foreach (['FC-A', 'FC-B'] as $i => $num) {
            Factura::create([
                'empresa_id'            => $this->empresa->id,
                'proveedor_id'          => $this->proveedor->id,
                'numero_factura'        => $num,
                'tipo'                  => 'COMPRA',
                'codigo_unico'          => Factura::generarCodigoUnico(),
                'fecha_emision'         => '2026-06-0' . ($i + 1),
                'monto_bruto'           => 119000,
                'monto_neto'            => 100000,
                'monto_iva'             => 19000,
                'estado'                => 'REGISTRADA',
                'es_documento_exterior' => false,
            ]);
        }

        // 3 ventas — no deben contaminar el crédito fiscal
        foreach (['FV-A', 'FV-B', 'FV-C'] as $num) {
            Factura::create([
                'empresa_id'    => $this->empresa->id,
                'cliente_id'    => $this->cliente->id,
                'numero_factura' => $num,
                'tipo'          => 'VENTA',
                'tipo_documento' => 'FACTURA',
                'tipo_dte'      => 33,
                'codigo_unico'  => Factura::generarCodigoUnico(),
                'fecha_emision' => '2026-06-20',
                'monto_bruto'   => 238000,
                'monto_neto'    => 200000,
                'monto_iva'     => 38000,
                'estado'        => 'REGISTRADA',
            ]);
        }

        $resultado = $this->service->simularF29($this->empresa->id, 6, 2026);

        $this->assertEquals(2, $resultado['compras']['cantidad'], '2 compras, no 5');
        $this->assertEquals(38000, $resultado['compras']['iva_credito'], 'IVA crédito = 2 × 19.000, no contaminado por ventas');
    }
}
