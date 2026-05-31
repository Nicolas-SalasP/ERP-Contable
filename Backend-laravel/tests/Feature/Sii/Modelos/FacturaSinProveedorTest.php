<?php

namespace Tests\Feature\Sii\Modelos;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class FacturaSinProveedorTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    public function test_se_puede_crear_factura_sin_proveedor_id(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();

        $cliente = Cliente::create([
            'empresa_id'   => $empresa->id,
            'rut'          => '11111111-1',
            'razon_social' => 'Cliente Receptor DTE',
        ]);

        $factura = Factura::create([
            'empresa_id'     => $empresa->id,
            'proveedor_id'   => null,
            'cliente_id'     => $cliente->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'numero_factura' => 'V-001',
            'tipo'           => 'VENTA',
            'tipo_dte'       => 33,
            'fecha_emision'  => '2026-05-23',
            'monto_neto'     => 10000,
            'monto_iva'      => 1900,
            'monto_bruto'    => 11900,
        ]);

        $persistida = Factura::find($factura->id);

        $this->assertNotNull($persistida, 'La factura de venta sin proveedor no se persistio.');
        $this->assertNull($persistida->proveedor_id);
        $this->assertSame($cliente->id, $persistida->cliente_id);
        $this->assertSame(33, $persistida->tipo_dte);
        $this->assertSame('VENTA', $persistida->tipo);
    }

    public function test_factura_de_compra_legacy_sigue_funcionando(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();

        $proveedor = Proveedor::create([
            'empresa_id'     => $empresa->id,
            'rut'            => '7.7.7.7-7',
            'razon_social'   => 'Proveedor Legacy',
            'codigo_interno' => 'P-LEGACY-' . uniqid(),
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $factura = Factura::create([
            'empresa_id'     => $empresa->id,
            'proveedor_id'   => $proveedor->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'numero_factura' => 'C-001',
            'tipo'           => 'COMPRA',
            'fecha_emision'  => '2026-05-23',
            'monto_neto'     => 5000,
            'monto_iva'      => 950,
            'monto_bruto'    => 5950,
        ]);

        $persistida = Factura::find($factura->id);

        $this->assertNotNull($persistida);
        $this->assertSame($proveedor->id, $persistida->proveedor_id);
        $this->assertNull($persistida->cliente_id);
        $this->assertSame('COMPRA', $persistida->tipo);
    }
}
