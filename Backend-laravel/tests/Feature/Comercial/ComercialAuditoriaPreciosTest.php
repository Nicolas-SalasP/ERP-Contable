<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Rol;

class ComercialAuditoriaPreciosTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;
    protected $cliente;
    protected function setUp(): void
    {
        parent::setUp();

        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Auditoria SpA']);
        $this->usuario = User::create(['nombre' => 'Auditor', 'email' => 'a@audit.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);

        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '1.1.1.1-1',
            'razon_social' => 'Cliente Auditoria',
            'estado' => 'ACTIVO'
        ]);

        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 3, 'nombre' => 'Aprobada']
        ]);
    }

    public function test_bloquea_actualizacion_de_precios_en_cotizacion_aprobada()
    {
        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'nombre_cliente' => 'Test',
            'estado_id' => 3,
            'numero_cotizacion' => 'COT-LOCK',
            'subtotal' => 1000,
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_total' => 1190,
            'total' => 1190,
            'fecha_emision' => now()
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/cotizaciones/{$cotizacion->id}", [
            'detalles' => [['producto_nombre' => 'Hack', 'cantidad' => 1, 'precio_unitario' => 999999]]
        ]);

        $response->assertStatus(400);
    }

    public function test_sistema_detecta_y_previene_montos_negativos_en_detalles_de_compra()
    {
        // Prueba de inyección de valores negativos para intentar "restar" deuda al proveedor
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => 1,
            'detalles' => [['producto_nombre' => 'Fraude', 'cantidad' => 1, 'precio_unitario' => -500000]]
        ]);

        $response->assertStatus(422);
    }
}