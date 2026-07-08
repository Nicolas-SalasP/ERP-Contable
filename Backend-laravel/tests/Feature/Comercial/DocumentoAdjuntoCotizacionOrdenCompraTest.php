<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\OrdenCompra;

/**
 * Regresión: DocumentoAdjunto ahora también soporta Cotizacion y OrdenCompra
 * (además de Factura), reusando el mismo servicio/controller.
 */
class DocumentoAdjuntoCotizacionOrdenCompraTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $cotizacion;
    protected $ordenCompra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Adjuntos SpA']);
        $this->usuario = User::create(['nombre' => 'Admin', 'email' => 'admin@adj.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
        ]);
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Cliente Adj', 'estado' => 'ACTIVO']);
        $this->cotizacion = Cotizacion::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => $cliente->razon_social, 'estado_id' => 1, 'numero_cotizacion' => 'COT-ADJ', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now()]);

        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Prov OC', 'codigo_interno' => 'P-OC', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $this->ordenCompra = OrdenCompra::create(['empresa_id' => $this->empresa->id, 'proveedor_id' => $proveedor->id, 'numero_oc' => 'OC-ADJ', 'fecha_emision' => now()]);

        Storage::fake('local');
    }

    // --- Cotización ---

    public function test_sube_lista_y_elimina_adjunto_de_una_cotizacion()
    {
        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');

        $subida = $this->actingAs($this->usuario)->post("/api/cotizaciones/{$this->cotizacion->id}/adjuntos", [
            'documentos' => [$pdf],
        ]);
        $subida->assertStatus(201);
        $this->assertDatabaseCount('documentos_adjuntos', 1);
        $this->assertDatabaseHas('documentos_adjuntos', ['cotizacion_id' => $this->cotizacion->id, 'factura_id' => null, 'orden_compra_id' => null]);

        $adjuntoId = $subida->json('data.0.id');
        $ruta = $subida->json('data.0.ruta');
        Storage::disk('local')->assertExists($ruta);

        $listado = $this->actingAs($this->usuario)->getJson("/api/cotizaciones/{$this->cotizacion->id}/adjuntos");
        $listado->assertOk();
        $listado->assertJsonCount(1, 'data');

        $eliminacion = $this->actingAs($this->usuario)->deleteJson("/api/cotizaciones/{$this->cotizacion->id}/adjuntos/{$adjuntoId}");
        $eliminacion->assertOk();
        $this->assertDatabaseMissing('documentos_adjuntos', ['id' => $adjuntoId]);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_no_puede_ver_adjunto_de_cotizacion_de_otra_empresa()
    {
        $otraEmpresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Otra']);
        $otroCliente = Cliente::create(['empresa_id' => $otraEmpresa->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Cliente Ajeno', 'estado' => 'ACTIVO']);
        $cotizacionAjena = Cotizacion::create(['empresa_id' => $otraEmpresa->id, 'cliente_id' => $otroCliente->id, 'nombre_cliente' => $otroCliente->razon_social, 'estado_id' => 1, 'numero_cotizacion' => 'COT-AJENA', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->getJson("/api/cotizaciones/{$cotizacionAjena->id}/adjuntos");
        $response->assertStatus(404);

        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');
        $response = $this->actingAs($this->usuario)->post("/api/cotizaciones/{$cotizacionAjena->id}/adjuntos", ['documentos' => [$pdf]]);
        $response->assertStatus(404);
    }

    // --- Orden de compra ---

    public function test_sube_lista_y_elimina_adjunto_de_una_orden_de_compra()
    {
        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');

        $subida = $this->actingAs($this->usuario)->post("/api/comercial/ordenes-compra/{$this->ordenCompra->id}/adjuntos", [
            'documentos' => [$pdf],
        ]);
        $subida->assertStatus(201);
        $this->assertDatabaseCount('documentos_adjuntos', 1);
        $this->assertDatabaseHas('documentos_adjuntos', ['orden_compra_id' => $this->ordenCompra->id, 'factura_id' => null, 'cotizacion_id' => null]);

        $adjuntoId = $subida->json('data.0.id');
        $ruta = $subida->json('data.0.ruta');
        Storage::disk('local')->assertExists($ruta);

        $listado = $this->actingAs($this->usuario)->getJson("/api/comercial/ordenes-compra/{$this->ordenCompra->id}/adjuntos");
        $listado->assertOk();
        $listado->assertJsonCount(1, 'data');

        $eliminacion = $this->actingAs($this->usuario)->deleteJson("/api/comercial/ordenes-compra/{$this->ordenCompra->id}/adjuntos/{$adjuntoId}");
        $eliminacion->assertOk();
        $this->assertDatabaseMissing('documentos_adjuntos', ['id' => $adjuntoId]);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_no_puede_ver_ni_eliminar_adjunto_de_orden_de_compra_de_otra_empresa()
    {
        $otraEmpresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Otra']);
        $otroProveedor = Proveedor::create(['empresa_id' => $otraEmpresa->id, 'rut' => '4.4.4.4-4', 'razon_social' => 'Prov Ajeno', 'codigo_interno' => 'P-AJ', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $ordenAjena = OrdenCompra::create(['empresa_id' => $otraEmpresa->id, 'proveedor_id' => $otroProveedor->id, 'numero_oc' => 'OC-AJENA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->getJson("/api/comercial/ordenes-compra/{$ordenAjena->id}/adjuntos");
        $response->assertStatus(404);

        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');
        $response = $this->actingAs($this->usuario)->post("/api/comercial/ordenes-compra/{$ordenAjena->id}/adjuntos", ['documentos' => [$pdf]]);
        $response->assertStatus(404);
    }
}
