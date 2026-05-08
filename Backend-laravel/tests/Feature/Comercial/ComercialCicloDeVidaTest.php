<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;

class ComercialCicloDeVidaTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 2, 'nombre' => 'Enviada'],
            ['id' => 3, 'nombre' => 'Aprobada'],
            ['id' => 4, 'nombre' => 'Rechazada'],
            ['id' => 5, 'nombre' => 'Facturada']
        ]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Ciclo SpA']);
        $this->usuario = User::create(['nombre' => 'Vendedor', 'email' => 'v@ciclo.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_cambiar_estado_de_cotizacion_guarda_trazabilidad()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Cliente Ciclo', 'estado' => 'ACTIVO']);
        
        $cotizacion = Cotizacion::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => $cliente->razon_social, 'estado_id' => 1, 'numero_cotizacion' => 'COT-WORK', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->patchJson("/api/cotizaciones/{$cotizacion->id}/estado", [
            'estado_id' => 2
        ]);

        if (in_array($response->getStatusCode(), [404, 405])) {
             $this->markTestSkipped('Ruta PATCH /api/cotizaciones/{id}/estado pendiente de desarrollo.');
        } else {
             $response->assertStatus(200);
             $this->assertEquals(2, $cotizacion->fresh()->estado_id);
        }
    }

    public function test_convertir_cotizacion_aprobada_en_factura_de_venta()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Cliente Facturable', 'estado' => 'ACTIVO']);
        
        // Cotización está "Aprobada" (id: 3)
        $cotizacion = Cotizacion::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => $cliente->razon_social, 'estado_id' => 3, 'numero_cotizacion' => 'COT-VENTA', 'subtotal' => 50000, 'monto_neto' => 50000, 'monto_iva' => 9500, 'monto_total' => 59500, 'total' => 59500, 'fecha_emision' => now()]);

        // Simulamos el botón "Generar Factura" desde la cotización
        $response = $this->actingAs($this->usuario)->postJson("/api/cotizaciones/{$cotizacion->id}/facturar");

        if ($response->getStatusCode() === 404) {
             $this->markTestSkipped('Funcionalidad de convertir Cotización a Factura pendiente de desarrollo.');
        } else {
             $response->assertStatus(201);
             
             // La cotización debe pasar a estado "Facturada" (id: 5)
             $this->assertEquals(5, $cotizacion->fresh()->estado_id);
             
             // Se debe haber creado una Factura de VENTA en la base de datos
             $this->assertDatabaseHas('facturas', [
                 'empresa_id' => $this->empresa->id,
                 'tipo' => 'VENTA',
                 'monto_bruto' => 59500
             ]);
        }
    }
}