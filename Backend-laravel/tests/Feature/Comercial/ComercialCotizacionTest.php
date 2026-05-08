<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\Pais;

class ComercialCotizacionTest extends TestCase
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
            ['id' => 3, 'nombre' => 'Aprobada']
        ]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Cotizaciones SpA']);
        $this->usuario = User::create(['nombre' => 'Vendedor', 'email' => 'v@c.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_crear_cotizacion_guarda_correctamente_cabecera_y_detalles()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Nuevo', 'estado' => 'ACTIVO']);
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-01',
            'fecha_emision' => now()->format('Y-m-d'),
            'subtotal' => 20000,
            'monto_neto' => 20000,
            'monto_iva' => 3800,
            'monto_total' => 23800,
            'es_afecta' => true,
            'detalles' => [
                ['producto_nombre' => 'S1', 'cantidad' => 2, 'precio_unitario' => 5000, 'subtotal' => 10000],
                ['producto_nombre' => 'S2', 'cantidad' => 1, 'precio_unitario' => 10000, 'subtotal' => 10000]
            ]
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cotizaciones', ['numero_cotizacion' => 'COT-01', 'monto_total' => 23800]);
        $this->assertDatabaseCount('cotizacion_detalles', 2);
    }

    public function test_cotizacion_calcula_correctamente_descuentos_e_iva()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '5.5.5.5-5', 'razon_social' => 'Desc', 'estado' => 'ACTIVO']);
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'porcentaje_descuento' => 10,
            'es_afecta' => true,
            'detalles' => [['producto_nombre' => 'C', 'cantidad' => 1, 'precio_unitario' => 100000]]
        ]);

        $this->assertDatabaseHas('cotizaciones', [
            'id' => $response->json('data.id'),
            'subtotal' => 100000,
            'monto_descuento' => 10000,
            'monto_neto' => 90000,
            'monto_iva' => 17100,
            'monto_total' => 107100
        ]);
    }

    public function test_capa8_ignora_totales_falsos_del_frontend_y_recalcula_todo()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Hack Corp', 'estado' => 'ACTIVO']);
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-HACK',
            'subtotal' => 1,
            'monto_neto' => 1,
            'monto_iva' => 0,
            'monto_total' => 1, // Totales Falsos
            'detalles' => [['producto_nombre' => 'Servidor', 'cantidad' => 2, 'precio_unitario' => 500000]]
        ]);

        $this->assertDatabaseHas('cotizaciones', ['numero_cotizacion' => 'COT-HACK', 'subtotal' => 1000000, 'monto_total' => 1190000]);
    }

    public function test_prevencion_doble_clic_bloquea_cotizacion_con_numero_duplicado()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '10.100.100-1', 'razon_social' => 'Cliente Fast', 'estado' => 'ACTIVO']);
        $payload = [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-DOBLE',
            'fecha_emision' => now()->format('Y-m-d'),
            'detalles' => [['producto_nombre' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000]]
        ];

        $this->actingAs($this->usuario)->postJson('/api/cotizaciones', $payload)->assertStatus(201);
        $this->actingAs($this->usuario)->postJson('/api/cotizaciones', $payload)->assertStatus(422);
    }

    public function test_cotizacion_aprobada_no_puede_ser_modificada()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Aprobada', 'estado' => 'ACTIVO']);
        $cotizacion = Cotizacion::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => $cliente->razon_social, 'estado_id' => 3, 'numero_cotizacion' => 'COT-APROB', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->putJson("/api/cotizaciones/{$cotizacion->id}", [
            'detalles' => [['producto_nombre' => 'Nuevo', 'cantidad' => 1, 'precio_unitario' => 5000]]
        ]);

        $response->assertStatus(400)->assertSee('aprobada');
    }
}