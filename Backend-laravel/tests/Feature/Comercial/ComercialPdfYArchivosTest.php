<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ComercialPdfYArchivosTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected $empresa;

    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 2, 'nombre' => 'Enviada'],
            ['id' => 3, 'nombre' => 'Aprobada'],
        ]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'PDF SpA']);
        $this->usuario = User::create(['nombre' => 'PDF', 'email' => 'pdf@c.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_generar_pdf_cotizacion_retorna_archivo_valido()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Cliente PDF', 'estado' => 'ACTIVO']);

        // Creamos la cotización con los datos necesarios para que el PDF no falle por falta de información
        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'estado_id' => 1,
            'numero_cotizacion' => 'COT-PDF1',
            'subtotal' => 100,
            'monto_neto' => 100,
            'monto_iva' => 19,
            'monto_total' => 119,
            'total' => 119,
            'fecha_emision' => now(),
        ]);

        $response = $this->actingAs($this->usuario)->get("/api/cotizaciones/pdf/{$cotizacion->id}");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_generar_pdf_limpia_nombres_de_clientes_con_caracteres_invalidos()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Hacker / \ : * ? " < > | Corp', 'estado' => 'ACTIVO']);
        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'estado_id' => 1,
            'numero_cotizacion' => 'COT-PDF2',
            'subtotal' => 100,
            'monto_neto' => 100,
            'monto_iva' => 19,
            'monto_total' => 119,
            'total' => 119,
            'fecha_emision' => now(),
        ]);

        $response = $this->actingAs($this->usuario)->get("/api/cotizaciones/pdf/{$cotizacion->id}");

        $response->assertStatus(200);

        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringNotContainsString('/', $contentDisposition);
        $this->assertStringNotContainsString('\\', $contentDisposition);
        $this->assertStringNotContainsString('*', $contentDisposition);
    }

    public function test_descargar_pdf_de_cotizacion_inexistente_retorna_json_con_error()
    {
        $response = $this->actingAs($this->usuario)->get('/api/cotizaciones/pdf/999999');

        $this->assertContains($response->getStatusCode(), [400, 404, 422, 500]);
        $response->assertJsonStructure(['success', 'message']);
    }

    public function test_seguridad_bloquea_pdf_de_cotizacion_de_otra_empresa()
    {
        $empresaRival = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Rival SpA']);
        $clienteRival = Cliente::create(['empresa_id' => $empresaRival->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Cliente Rival', 'estado' => 'ACTIVO']);
        $cotizacionRival = Cotizacion::create(['empresa_id' => $empresaRival->id, 'cliente_id' => $clienteRival->id, 'nombre_cliente' => $clienteRival->razon_social, 'estado_id' => 1, 'numero_cotizacion' => 'COT-RIVAL', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now()]);

        // Nuestro usuario (Empresa 1) intenta descargar la cotización de la Empresa Rival (Empresa 2)
        $response = $this->actingAs($this->usuario)->get("/api/cotizaciones/{$cotizacionRival->id}/pdf");

        // Debe fallar rotundamente
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_pdf_de_factura_de_otra_empresa_devuelve_404_limpio_sin_stack_trace()
    {
        // Regresion: descargarPdf() usaba findOrFail() sin try/catch -- una factura
        // ajena tiraba ModelNotFoundException cruda en vez de un 404 de dominio.
        $empresaRival = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Rival SpA']);
        $proveedorRival = Proveedor::create([
            'empresa_id' => $empresaRival->id, 'codigo_interno' => 'PR-RIVAL', 'rut' => '4.4.4.4-4',
            'razon_social' => 'Prov Rival', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP',
        ]);
        $facturaRival = Factura::create([
            'empresa_id' => $empresaRival->id, 'proveedor_id' => $proveedorRival->id,
            'numero_factura' => 'F-RIVAL', 'codigo_unico' => 999999, 'fecha_emision' => now(),
            'monto_bruto' => 100, 'monto_neto' => 100, 'monto_iva' => 0, 'tipo' => 'COMPRA',
        ]);

        $response = $this->actingAs($this->usuario)->getJson("/api/facturas/{$facturaRival->id}/pdf");

        $response->assertStatus(404)->assertJsonStructure(['success', 'message']);
        $this->assertFalse($response->json('success'));
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        $this->assertStringNotContainsString('ModelNotFoundException', (string) $response->getContent());
    }
}
