<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class FacturaComprobantePdfTest extends TestCase
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

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Comprobante SpA']);
        $this->usuario = User::create([
            'nombre' => 'Vendedor',
            'email' => 'v@comprobante.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '1.1.1.1-1',
            'razon_social' => 'Cliente Comprobante',
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearFacturaVenta(): Factura
    {
        return Factura::create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $this->cliente->id,
            'numero_factura' => 'FV-COMP-1',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);
    }

    public function test_genera_comprobante_pdf_original_sin_marca_de_copia(): void
    {
        $factura = $this->crearFacturaVenta();

        $response = $this->actingAs($this->usuario)->get("/api/facturas/{$factura->id}/comprobante");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringNotContainsString('COPIA', $response->headers->get('content-disposition'));
    }

    public function test_genera_comprobante_pdf_como_copia_con_marca_visible(): void
    {
        $factura = $this->crearFacturaVenta();

        $response = $this->actingAs($this->usuario)->get("/api/facturas/{$factura->id}/comprobante?copia=1");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('COPIA', $response->headers->get('content-disposition'));
    }

    public function test_comprobante_de_factura_de_compra_no_existe(): void
    {
        $facturaCompra = Factura::create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'codigo_unico' => Factura::generarCodigoUnico(),
            'numero_factura' => 'F-100',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190,
            'estado' => 'REGISTRADA',
        ]);

        $response = $this->actingAs($this->usuario)->getJson("/api/facturas/{$facturaCompra->id}/comprobante");

        $response->assertStatus(404);
    }

    public function test_bloquea_comprobante_de_factura_de_otra_empresa(): void
    {
        $factura = $this->crearFacturaVenta();

        $otraEmpresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Otra SpA']);
        $otroUsuario = User::create([
            'nombre' => 'Otro',
            'email' => 'otro@comprobante.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $otraEmpresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        $response = $this->actingAs($otroUsuario)->getJson("/api/facturas/{$factura->id}/comprobante");

        $response->assertStatus(404);
    }
}
