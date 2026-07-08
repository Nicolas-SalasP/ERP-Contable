<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\AnticipoCliente;
use App\Domains\Comercial\Models\AnticipoProveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Services\AnticipoClienteService;
use App\Domains\Comercial\Services\AnticipoProveedorService;

/**
 * Regresión del hallazgo de auditoría #11: anticipo_aplicaciones es una tabla
 * compartida entre proveedor y cliente; anular una factura de venta no debe
 * tocar jamás un anticipo de proveedor con el mismo ID.
 */
class ComercialAnticiposClienteTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $cliente;
    protected $prov;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = $this->crearEmpresa([
            'rut' => '88.888.888-8',
            'razon_social' => 'Anticipos Cliente SpA',
        ]);
        $this->usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'nombre' => 'Tesorero Cliente',
            'email' => 't@anticliente.cl',
        ]);
        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '2.2.2.2-2',
            'razon_social' => 'Cliente Anticipo',
        ]);
        // Facturas de VENTA en este dominio igual persisten proveedor_id (FK real);
        // se usa una entidad "espejo" tal como hace CotizacionService::facturar().
        $this->prov = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => $this->cliente->rut,
            'razon_social' => $this->cliente->razon_social,
            'codigo_interno' => 'CLI-' . $this->cliente->id,
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    public function test_registrar_anticipo_de_cliente_crea_el_registro_con_saldo_disponible()
    {
        $anticipo = app(AnticipoClienteService::class)->registrar($this->empresa->id, [
            'cliente_id' => $this->cliente->id,
            'monto' => 300000,
            'referencia' => 'Sobrepago detectado en conciliación',
        ]);

        $this->assertDatabaseHas('anticipos_clientes', [
            'id' => $anticipo->id,
            'cliente_id' => $this->cliente->id,
            'monto_original' => 300000,
            'saldo_disponible' => 300000,
            'estado' => 'DISPONIBLE',
        ]);
    }

    public function test_aplicar_anticipo_de_cliente_a_factura_de_venta_disminuye_el_saldo()
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->prov->id,
            'numero_factura' => 'FV-ANTICIPO-1',
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $anticipo = AnticipoCliente::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'monto' => 100000,
            'monto_original' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'DISPONIBLE',
        ]);

        app(AnticipoClienteService::class)->aplicarAFactura(
            $this->empresa->id, $anticipo->id, $factura->id, 40000
        );

        $this->assertEquals(60000, (float) $anticipo->fresh()->saldo_disponible);
    }

    public function test_aplicar_anticipo_de_cliente_a_factura_de_compra_es_rechazado()
    {
        $facturaCompra = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->prov->id,
            'numero_factura' => 'FC-ANTICIPO-1',
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $anticipo = AnticipoCliente::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'monto' => 100000,
            'monto_original' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'DISPONIBLE',
        ]);

        $this->expectExceptionMessage('Un anticipo de cliente solo puede aplicarse a una factura de venta.');

        app(AnticipoClienteService::class)->aplicarAFactura(
            $this->empresa->id, $anticipo->id, $facturaCompra->id, 40000
        );
    }

    public function test_anular_factura_de_venta_libera_el_anticipo_de_cliente_aplicado()
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->prov->id,
            'numero_factura' => 'FV-ANTICIPO-2',
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $anticipo = AnticipoCliente::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'monto' => 100000,
            'monto_original' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'DISPONIBLE',
        ]);

        app(AnticipoClienteService::class)->aplicarAFactura(
            $this->empresa->id, $anticipo->id, $factura->id, 40000
        );
        $this->assertEquals(60000, (float) $anticipo->fresh()->saldo_disponible);

        $this->actingAs($this->usuario)
            ->postJson("/api/facturas/{$factura->id}/anular", ['motivo' => 'Prueba de reversa de anticipo de cliente'])
            ->assertStatus(200);

        $this->assertEquals(
            100000,
            (float) $anticipo->fresh()->saldo_disponible,
            'El anticipo de cliente debe recuperar su saldo completo al anular la factura de venta a la que se aplicó.'
        );
        $this->assertEquals('DISPONIBLE', $anticipo->fresh()->estado);
    }

    /**
     * Test clave contra la colisión de IDs (auditoría #11): un anticipo de
     * proveedor con la misma factura y monto no debe verse afectado al anular
     * una factura de VENTA con anticipo de cliente aplicado.
     */
    public function test_anular_factura_de_venta_no_afecta_anticipos_de_proveedor_existentes()
    {
        $facturaVenta = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->prov->id,
            'numero_factura' => 'FV-ANTICIPO-3',
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $anticipoCliente = AnticipoCliente::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'monto' => 100000,
            'monto_original' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'DISPONIBLE',
        ]);

        app(AnticipoClienteService::class)->aplicarAFactura(
            $this->empresa->id, $anticipoCliente->id, $facturaVenta->id, 40000
        );

        // Anticipo de proveedor con el MISMO id que el anticipo de cliente (colisión deliberada)
        // y aplicado a una factura de COMPRA distinta, para verificar que no se cruzan.
        $facturaCompra = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->prov->id,
            'numero_factura' => 'FC-ANTICIPO-3',
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $anticipoProveedor = AnticipoProveedor::create([
            'id' => $anticipoCliente->id, // mismo id a propósito
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->prov->id,
            'monto' => 250000,
            'monto_original' => 250000,
            'saldo_disponible' => 250000,
            'estado' => 'DISPONIBLE',
        ]);

        app(AnticipoProveedorService::class)->aplicarAFactura(
            $this->empresa->id, $anticipoProveedor->id, $facturaCompra->id, 70000
        );
        $this->assertEquals(180000, (float) $anticipoProveedor->fresh()->saldo_disponible);

        // Se anula solo la factura de VENTA; el anticipo de proveedor (mismo id) no debe cambiar.
        $this->actingAs($this->usuario)
            ->postJson("/api/facturas/{$facturaVenta->id}/anular", ['motivo' => 'Verificar aislamiento proveedor/cliente'])
            ->assertStatus(200);

        $this->assertEquals(
            100000,
            (float) $anticipoCliente->fresh()->saldo_disponible,
            'El anticipo de cliente debe liberarse.'
        );
        $this->assertEquals(
            180000,
            (float) $anticipoProveedor->fresh()->saldo_disponible,
            'El anticipo de proveedor con el mismo ID NO debe alterarse al anular la factura de venta.'
        );
        $this->assertEquals('DISPONIBLE', $anticipoProveedor->fresh()->estado);
    }
}
