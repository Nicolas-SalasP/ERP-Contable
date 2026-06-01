<?php

namespace Tests\Feature\Sii\Modelos;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class FacturaSiiTraitTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private function crearProveedor(Empresa $empresa, string $rutSufijo = '1'): Proveedor
    {
        return Proveedor::create([
            'empresa_id'      => $empresa->id,
            'rut'             => "9.9.9.9-{$rutSufijo}",
            'razon_social'    => 'Proveedor de Test',
            'codigo_interno'  => 'P-' . uniqid(),
            'pais_iso'        => 'CL',
            'moneda_defecto'  => 'CLP',
        ]);
    }

    public function test_factura_puede_guardar_campos_sii(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa, '1');

        $cliente = Cliente::create([
            'empresa_id'   => $empresa->id,
            'rut'          => '11111111-1',
            'razon_social' => 'Cliente Receptor DTE',
        ]);

        $factura = Factura::create([
            'empresa_id'        => $empresa->id,
            'proveedor_id'      => $proveedor->id,
            'codigo_unico'      => Factura::generarCodigoUnico(),
            'numero_factura'    => 'F-001',
            'fecha_emision'     => '2026-05-23',
            'monto_neto'        => 10000.00,
            'monto_iva'         => 1900.00,
            'monto_bruto'       => 11900.00,
            'cliente_id'        => $cliente->id,
            'tipo_dte'          => 33,
            'forma_pago_codigo' => 1,
            'condicion_pago'    => '30 dias',
            'moneda'            => 'CLP',
            'monto_exento'      => 0.00,
        ]);

        $persistida = Factura::find($factura->id);

        $this->assertSame($cliente->id, $persistida->cliente_id);
        $this->assertSame(33, $persistida->tipo_dte);
        $this->assertSame(1, $persistida->forma_pago_codigo);
        $this->assertSame('30 dias', $persistida->condicion_pago);
        $this->assertSame('CLP', $persistida->moneda);
    }

    public function test_relacion_detalles_retorna_hasmany_de_factura_detalle(): void
    {
        $factura  = new Factura();
        $relacion = $factura->detalles();

        $this->assertInstanceOf(HasMany::class, $relacion);
    }

    public function test_emitir_dte_automatico_default_es_false(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa, '2');

        $factura = Factura::create([
            'empresa_id'     => $empresa->id,
            'proveedor_id'   => $proveedor->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'numero_factura' => 'F-002',
            'fecha_emision'  => '2026-05-23',
            'monto_neto'     => 1000,
            'monto_iva'      => 190,
            'monto_bruto'    => 1190,
        ]);

        $this->assertFalse($factura->fresh()->emitir_dte_automatico);
    }

    public function test_moneda_default_es_clp(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa, '3');

        $factura = Factura::create([
            'empresa_id'     => $empresa->id,
            'proveedor_id'   => $proveedor->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'numero_factura' => 'F-003',
            'fecha_emision'  => '2026-05-23',
            'monto_neto'     => 1000,
            'monto_iva'      => 190,
            'monto_bruto'    => 1190,
        ]);

        $this->assertSame('CLP', $factura->fresh()->moneda);
    }

    public function test_tipo_dte_se_castea_a_entero(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa, '4');

        $factura = Factura::create([
            'empresa_id'     => $empresa->id,
            'proveedor_id'   => $proveedor->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'numero_factura' => 'F-004',
            'fecha_emision'  => '2026-05-23',
            'monto_neto'     => 0,
            'monto_iva'      => 0,
            'monto_bruto'    => 0,
            'tipo_dte'       => '33', // como string
        ]);

        $this->assertSame(33, $factura->fresh()->tipo_dte);
        $this->assertIsInt($factura->fresh()->tipo_dte);
    }
}
