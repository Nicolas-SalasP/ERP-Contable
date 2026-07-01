<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Services\ImpuestosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class F29ExcluyeExteriorTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private ImpuestosService $service;
    private $empresa;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa] = $this->crearEmpresaConAdmin();
        $this->service = app(ImpuestosService::class);

        // pais_iso 'CL' existe porque prepararPaises() lo crea en setUp.
        $this->proveedor = Proveedor::create([
            'empresa_id'      => $this->empresa->id,
            'rut'             => '76111222-3',
            'razon_social'    => 'Proveedor Exterior Test',
            'codigo_interno'  => 'PEXT',
            'pais_iso'        => 'CL',
            'moneda_defecto'  => 'USD',
        ]);
    }

    public function test_f29_no_incluye_facturas_del_exterior_en_credito_fiscal(): void
    {
        // Factura NACIONAL (debe incluirse en crédito fiscal)
        Factura::create([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => 'NAC-001',
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => 111111,
            'fecha_emision'         => '2026-06-10',
            'monto_bruto'           => 119000,
            'monto_neto'            => 100000,
            'monto_iva'             => 19000,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => false,
        ]);

        // Factura DEL EXTERIOR (no debe entrar al crédito fiscal)
        Factura::create([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => 'RX1GPPWH-0001',
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => 222222,
            'fecha_emision'         => '2026-06-10',
            'monto_bruto'           => 22610,
            'monto_neto'            => 22610,
            'monto_iva'             => 0,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => true,
        ]);

        $resultado = $this->service->simularF29($this->empresa->id, 6, 2026);

        $this->assertEquals(1, $resultado['compras']['cantidad'], 'Solo debe contar 1 factura (la nacional)');
        $this->assertEquals(19000, $resultado['compras']['iva_credito'], 'IVA crédito debe ser solo el de la factura nacional');
        $this->assertEquals(100000, $resultado['compras']['neto'], 'Neto compras debe ser solo el de la factura nacional');
    }

    public function test_f29_excluye_totalmente_cuando_solo_hay_documentos_del_exterior(): void
    {
        Factura::create([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => 'EXT-SOLO',
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => 333333,
            'fecha_emision'         => '2026-06-10',
            'monto_bruto'           => 22610,
            'monto_neto'            => 22610,
            'monto_iva'             => 0,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => true,
        ]);

        $resultado = $this->service->simularF29($this->empresa->id, 6, 2026);

        $this->assertEquals(0, $resultado['compras']['cantidad'], 'No debe incluir facturas del exterior');
        $this->assertEquals(0, (float) $resultado['compras']['iva_credito'], 'IVA crédito debe ser 0 si solo hay exterior');
    }

    public function test_f29_nacional_no_se_altera_por_presencia_de_facturas_exterior(): void
    {
        // Solo factura nacional
        Factura::create([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => 'NAC-SOLO',
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => 444444,
            'fecha_emision'         => '2026-06-10',
            'monto_bruto'           => 119000,
            'monto_neto'            => 100000,
            'monto_iva'             => 19000,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => false,
        ]);

        $sinExterior = $this->service->simularF29($this->empresa->id, 6, 2026);

        // Agregar una factura exterior como "ruido"
        Factura::create([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => 'EXT-RUIDO',
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => 555555,
            'fecha_emision'         => '2026-06-10',
            'monto_bruto'           => 50000,
            'monto_neto'            => 50000,
            'monto_iva'             => 0,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => true,
        ]);

        $conExterior = $this->service->simularF29($this->empresa->id, 6, 2026);

        $this->assertEquals(
            $sinExterior['compras']['iva_credito'],
            $conExterior['compras']['iva_credito'],
            'El IVA crédito no debe cambiar al agregar una factura del exterior'
        );
        $this->assertEquals(
            $sinExterior['compras']['cantidad'],
            $conExterior['compras']['cantidad'],
            'El conteo de compras no debe cambiar al agregar una factura del exterior'
        );
    }
}
