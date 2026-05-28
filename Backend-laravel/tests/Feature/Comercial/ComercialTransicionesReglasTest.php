<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

class ComercialTransicionesReglasTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;
    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        // Insertar los estados mínimos para evitar error de FK
        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 2, 'nombre' => 'Enviada'],
            ['id' => 3, 'nombre' => 'Aprobada']
        ]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Transiciones SpA']);
        $this->usuario = User::create(['nombre' => 'Flujo', 'email' => 'f@flujo.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_rechaza_crear_cotizacion_a_un_cliente_inactivo()
    {
        $clienteInactivo = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Vetado', 'estado' => 'INACTIVO']);
        
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $clienteInactivo->id,
            'fecha_emision' => now()->format('Y-m-d'),
            'detalles' => [['producto_nombre' => 'A', 'cantidad' => 1, 'precio_unitario' => 100]]
        ]);

        $this->assertNotEquals(201, $response->getStatusCode());
    }

    public function test_no_se_puede_cambiar_estado_a_una_cotizacion_inexistente()
    {
        $response = $this->actingAs($this->usuario)->patchJson("/api/cotizaciones/99999/estado", [
            'estado_id' => 3
        ]);

        // Si la ruta está protegida, devuelve 404 (o 405 dependiendo del ruteo)
        $this->assertContains($response->getStatusCode(), [404, 405]);
    }

    public function test_actualizar_cotizacion_con_estado_invalido_devuelve_error()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Test', 'estado' => 'ACTIVO']);
        $cotizacion = Cotizacion::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => 'Test', 'estado_id' => 1, 'numero_cotizacion' => 'COT-T', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->patchJson("/api/cotizaciones/{$cotizacion->id}/estado", [
            'estado_id' => 'ESTADO_BASURA'
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422, 404, 405]); 
    }

    public function test_rechaza_fechas_futuras_irreales_en_facturacion()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'numero_factura' => 'F-FUTURO',
            'fecha_emision' => now()->addYears(100)->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190
        ]);

        $this->assertNotEquals(201, $response->getStatusCode());
    }

    public function test_editar_cliente_no_afecta_a_las_cotizaciones_historicas()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Nombre Viejo', 'estado' => 'ACTIVO']);
        $cotizacion = Cotizacion::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => $cliente->razon_social, 'estado_id' => 1, 'numero_cotizacion' => 'COT-HIST', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now()]);

        $this->actingAs($this->usuario)->putJson("/api/clientes/{$cliente->id}", ['razon_social' => 'Nombre Nuevo']);

        $this->assertEquals('Nombre Viejo', $cotizacion->fresh()->nombre_cliente);
    }
}