<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;

class ComercialPdfYArchivosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 2, 'nombre' => 'Enviada'],
            ['id' => 3, 'nombre' => 'Aprobada']
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
            'fecha_emision' => now()
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
            'fecha_emision' => now()
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
        $response = $this->actingAs($this->usuario)->get("/api/cotizaciones/pdf/999999");
        
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
}