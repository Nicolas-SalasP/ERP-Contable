<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EmisorSnapshotPdfTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $usuario;

    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = Empresa::create([
            'rut' => '77.777.777-7',
            'razon_social' => 'Nombre Original SpA',
        ]);
        $this->usuario = User::create([
            'nombre' => 'Vendedor',
            'email' => 'v@snapshot.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '1.1.1.1-1',
            'razon_social' => 'Cliente Snapshot',
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_factura_de_venta_congela_razon_social_del_emisor_al_crearse(): void
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $this->cliente->id,
            'numero_factura' => 'FV-SNAP-1',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $this->assertSame('Nombre Original SpA', $factura->emisor_razon_social);

        $this->empresa->update(['razon_social' => 'Nombre Nuevo SpA']);
        $factura->refresh();

        $this->assertSame('Nombre Original SpA', $factura->emisor_razon_social, 'El PDF ya emitido no debe cambiar retroactivamente');

        $response = $this->actingAs($this->usuario)->get("/api/facturas/{$factura->id}/comprobante");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_factura_de_compra_no_congela_emisor(): void
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'codigo_unico' => Factura::generarCodigoUnico(),
            'numero_factura' => 'FC-SNAP-1',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190,
            'estado' => 'REGISTRADA',
        ]);

        $this->assertNull($factura->emisor_razon_social);
    }

    public function test_cotizacion_congela_razon_social_del_emisor_al_crearse(): void
    {
        $estadoBorrador = EstadoCotizacion::firstOrCreate(['nombre' => 'Borrador']);

        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'nombre_cliente' => $this->cliente->razon_social,
            'estado_id' => $estadoBorrador->id,
            'numero_cotizacion' => 'CT-SNAP-1',
            'fecha_emision' => now()->format('Y-m-d'),
            'subtotal' => 100000,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_total' => 119000,
            'total' => 119000,
        ]);

        $this->assertSame('Nombre Original SpA', $cotizacion->emisor_razon_social);

        $this->empresa->update(['razon_social' => 'Nombre Nuevo SpA']);
        $cotizacion->refresh();

        $this->assertSame('Nombre Original SpA', $cotizacion->emisor_razon_social);
    }
}
