<?php

namespace Tests\Feature\Sii\Concerns;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * F6.1 — Verifica las 3 extensiones del trait HasSiiAttributesFactura:
 * cliente(), dteEmitido() y puedeEmitirDte().
 */
class HasSiiAttributesFacturaExtensionTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create(['rut' => '76123456-7', 'razon_social' => 'EMP']);
    }

    private function crearCliente(int $empresaId): Cliente
    {
        return Cliente::create([
            'rut' => '11222333-4', 'razon_social' => 'CLI',
            'empresa_id' => $empresaId, 'estado' => 'ACTIVO',
        ]);
    }

    private function crearFacturaBase(int $empresaId, ?int $clienteId, array $overrides = []): Factura
    {
        return Factura::create(array_merge([
            'empresa_id'     => $empresaId,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'cliente_id'     => $clienteId,
            'numero_factura' => 'F-' . random_int(1000, 99999),
            'tipo'           => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'tipo_dte'       => 33,
            'fecha_emision'  => now()->toDateString(),
            'monto_neto'     => 1000,
            'monto_iva'      => 190,
            'monto_bruto'    => 1190,
            'estado'         => 'REGISTRADA',
        ], $overrides));
    }

    public function test_factura_tiene_relacion_cliente_disponible_despues_de_trait(): void
    {
        $empresa = $this->crearEmpresa();
        $cliente = $this->crearCliente($empresa->id);
        $factura = $this->crearFacturaBase($empresa->id, $cliente->id);

        $this->assertInstanceOf(BelongsTo::class, $factura->cliente());
        $this->assertSame($cliente->id, $factura->cliente->id);
        $this->assertSame('CLI', $factura->cliente->razon_social);
    }

    public function test_factura_tiene_relacion_dte_emitido_via_FK_nueva(): void
    {
        $empresa = $this->crearEmpresa();
        $cliente = $this->crearCliente($empresa->id);
        $dte = SiiDteEmitido::factory()->create(['empresa_id' => $empresa->id]);
        $factura = $this->crearFacturaBase($empresa->id, $cliente->id, [
            'sii_dte_emitido_id' => $dte->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $factura->dteEmitido());
        $this->assertSame($dte->id, $factura->dteEmitido->id);
    }

    public function test_puedeEmitirDte_true_cuando_factura_completa(): void
    {
        $empresa = $this->crearEmpresa();
        $cliente = $this->crearCliente($empresa->id);
        $factura = $this->crearFacturaBase($empresa->id, $cliente->id);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000, 'monto_item' => 1000, 'exento' => false,
        ]);

        $this->assertTrue($factura->fresh()->puedeEmitirDte());
    }

    public function test_puedeEmitirDte_false_si_tipo_dte_null(): void
    {
        $empresa = $this->crearEmpresa();
        $cliente = $this->crearCliente($empresa->id);
        $factura = $this->crearFacturaBase($empresa->id, $cliente->id, ['tipo_dte' => null]);

        $this->assertFalse($factura->puedeEmitirDte());
    }

    public function test_puedeEmitirDte_false_si_ya_emitida(): void
    {
        $empresa = $this->crearEmpresa();
        $cliente = $this->crearCliente($empresa->id);
        $dte = SiiDteEmitido::factory()->create(['empresa_id' => $empresa->id]);
        $factura = $this->crearFacturaBase($empresa->id, $cliente->id, [
            'sii_dte_emitido_id' => $dte->id,
        ]);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000, 'monto_item' => 1000, 'exento' => false,
        ]);

        $this->assertFalse($factura->fresh()->puedeEmitirDte());
    }

    public function test_puedeEmitirDte_false_si_estado_ANULADA(): void
    {
        $empresa = $this->crearEmpresa();
        $cliente = $this->crearCliente($empresa->id);
        $factura = $this->crearFacturaBase($empresa->id, $cliente->id, ['estado' => 'ANULADA']);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000, 'monto_item' => 1000, 'exento' => false,
        ]);

        $this->assertFalse($factura->fresh()->puedeEmitirDte());
    }
}
