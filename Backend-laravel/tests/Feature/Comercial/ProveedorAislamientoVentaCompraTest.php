<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\AnticipoProveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Regresion (auditoria post-fix de FacturaController::historial): cuando una empresa es a la
 * vez Proveedor y Cliente (mismo RUT), CotizacionService::facturar reusa el mismo proveedor_id
 * "espejo" en sus facturas de VENTA. Sin filtrar tipo='COMPRA', tanto la ficha del proveedor
 * como la compensacion de partidas (cruzar-documentos) mezclaban/afectaban facturas de VENTA
 * ajenas al historial de compras real.
 */
class ProveedorAislamientoVentaCompraTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = $this->crearEmpresa([
            'rut' => '76.333.333-3',
            'razon_social' => 'Aislamiento Venta Compra SpA',
        ]);
        $this->usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'nombre' => 'Contador Aislamiento',
            'email' => 'c@aislamiento.cl',
        ]);
        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '4.4.4.4-4',
            'razon_social' => 'Contraparte Mixta SpA',
            'codigo_interno' => 'P-MIX',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '352105',
            'nombre' => 'Proveedores', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '110205',
            'nombre' => 'Anticipos a Proveedores', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true,
        ]);
    }

    private function crearFacturaCompra(string $numero): Factura
    {
        return Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => $numero,
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 84034,
            'monto_iva' => 15966,
            'monto_bruto' => 100000,
            'estado' => 'REGISTRADA',
        ]);
    }

    private function crearFacturaVentaEspejo(string $numero): Factura
    {
        // Simula el caso real: proveedor_id apunta a la MISMA fila de Proveedor (entidad
        // espejo autogenerada por RUT en CotizacionService::facturar), pero es una venta.
        return Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->proveedor->id,
            'cliente_id' => null,
            'numero_factura' => $numero,
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 84034,
            'monto_iva' => 15966,
            'monto_bruto' => 100000,
            'estado' => 'REGISTRADA',
        ]);
    }

    public function test_ficha_de_proveedor_no_incluye_facturas_de_venta_espejo(): void
    {
        $facturaCompra = $this->crearFacturaCompra('F-MIX-COMPRA');
        $facturaVenta = $this->crearFacturaVentaEspejo('F-MIX-VENTA');

        $respuesta = $this->actingAs($this->usuario)
            ->getJson("/api/proveedores/ficha/{$this->proveedor->id}")
            ->assertOk();

        $numeros = collect($respuesta->json('data.facturas'))->pluck('numero_factura');
        $this->assertTrue($numeros->contains('F-MIX-COMPRA'));
        $this->assertFalse($numeros->contains('F-MIX-VENTA'));
    }

    public function test_cruzar_documentos_no_compensa_una_factura_de_venta_espejo(): void
    {
        $facturaVenta = $this->crearFacturaVentaEspejo('F-MIX-VENTA-2');

        $anticipo = AnticipoProveedor::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'monto' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'PENDIENTE',
        ]);

        // La factura de VENTA no es deuda de compra: al no matchear tipo=COMPRA, totalDeuda=0
        // y 0 < totalAFavor (100000) -> debe rechazar la compensacion, no aplicarla.
        $this->actingAs($this->usuario)
            ->postJson("/api/proveedores/{$this->proveedor->id}/cruzar-documentos", [
                'facturas_ids' => [$facturaVenta->id],
                'anticipos_ids' => [$anticipo->id],
            ])
            ->assertStatus(422);

        $facturaVenta->refresh();
        $anticipo->refresh();
        $this->assertSame('REGISTRADA', $facturaVenta->estado, 'La factura de VENTA no debe ser tocada por una compensacion de compras.');
        $this->assertNull($facturaVenta->asiento_pago_id);
        $this->assertSame('PENDIENTE', $anticipo->estado, 'El anticipo no debe consumirse si la compensacion fue rechazada.');
    }

    public function test_cruzar_documentos_compensa_solo_la_factura_de_compra_real_ignorando_la_de_venta(): void
    {
        $facturaCompra = $this->crearFacturaCompra('F-MIX-COMPRA-3');
        $facturaVenta = $this->crearFacturaVentaEspejo('F-MIX-VENTA-3');

        $anticipo = AnticipoProveedor::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'monto' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'PENDIENTE',
        ]);

        // Intencionalmente se mandan AMBOS ids -- solo la de COMPRA debe contar como deuda.
        $this->actingAs($this->usuario)
            ->postJson("/api/proveedores/{$this->proveedor->id}/cruzar-documentos", [
                'facturas_ids' => [$facturaCompra->id, $facturaVenta->id],
                'anticipos_ids' => [$anticipo->id],
            ])
            ->assertStatus(200);

        $facturaCompra->refresh();
        $facturaVenta->refresh();

        $this->assertSame('PAGADA', $facturaCompra->estado);
        $this->assertNotNull($facturaCompra->asiento_pago_id);

        $this->assertSame('REGISTRADA', $facturaVenta->estado, 'La factura de VENTA nunca debe quedar PAGADA por una compensacion de compras.');
        $this->assertNull($facturaVenta->asiento_pago_id);
    }

    public function test_verificar_duplicado_no_confunde_numero_de_factura_de_venta_con_uno_de_compra(): void
    {
        $this->crearFacturaVentaEspejo('DUP-001');

        $respuesta = $this->actingAs($this->usuario)
            ->getJson("/api/facturas/check?proveedor_id={$this->proveedor->id}&numero_factura=DUP-001")
            ->assertOk();

        $this->assertFalse($respuesta->json('exists'));
    }
}
