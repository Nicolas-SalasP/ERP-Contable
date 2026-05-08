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
use App\Domains\Comercial\Models\EstadoCotizacion;

class ComercialPayloadsGigantesTest extends TestCase
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

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Carga SpA']);
        $this->usuario = User::create(['nombre' => 'Pesado', 'email' => 'p@c.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_soporta_crear_cotizacion_con_50_lineas_de_detalle()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '9.9.9.9-9', 'razon_social' => 'Mayorista', 'estado' => 'ACTIVO']);
        
        $detalles = [];
        for ($i = 1; $i <= 50; $i++) {
            $detalles[] = [
                'producto_nombre' => "Tornillo Tipo {$i}",
                'cantidad' => 10,
                'precio_unitario' => 50,
                'subtotal' => 500
            ];
        }

        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-MASIVA',
            'es_afecta' => true,
            'fecha_emision' => now()->format('Y-m-d'),
            'subtotal' => 25000,
            'monto_neto' => 25000,
            'monto_iva' => 4750,
            'monto_total' => 29750,
            'detalles' => $detalles
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('cotizacion_detalles', 50);
        $this->assertEquals(29750, $response->json('data.monto_total'));
    }

    public function test_rutas_inexistentes_en_el_dominio_comercial_devuelven_404_limpio()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/facturas/ruta-que-no-existe/magia');
        $response->assertStatus(404);
    }
}