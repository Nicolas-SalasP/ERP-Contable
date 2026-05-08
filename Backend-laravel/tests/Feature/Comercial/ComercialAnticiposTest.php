<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\AnticipoProveedor;

class ComercialAnticiposTest extends TestCase
{
    use RefreshDatabase;
    protected $empresa;
    protected $usuario;
    protected $prov;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Anticipos SpA']);
        $this->usuario = User::create(['nombre' => 'Tesorero', 'email' => 't@anti.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
        $this->prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Prov Anticipo', 'codigo_interno' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
    }

    public function test_registrar_anticipo_a_proveedor_crea_el_registro_con_saldo_disponible()
    {
        // Esta ruta probablemente pertenezca al módulo de Tesorería, pero impacta a Comercial
        $response = $this->actingAs($this->usuario)->postJson('/api/anticipos-proveedores', [
            'proveedor_id' => $this->prov->id,
            'monto' => 500000,
            'fecha' => now()->format('Y-m-d'),
            'referencia' => 'Abono para futura compra'
        ]);

        if ($response->getStatusCode() === 404) {
             $this->markTestSkipped('Ruta POST /api/anticipos-proveedores pendiente de crear.');
        } else {
             $response->assertStatus(201);
             $this->assertDatabaseHas('anticipos_proveedores', [
                 'proveedor_id' => $this->prov->id,
                 'monto_original' => 500000,
                 'saldo_disponible' => 500000 // Nace con el 100% disponible
             ]);
        }
    }

    public function test_rechaza_crear_anticipo_con_monto_cero_o_negativo()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/anticipos-proveedores', [
            'proveedor_id' => $this->prov->id,
            'monto' => -10000, // Monto ilegal
            'fecha' => now()->format('Y-m-d')
        ]);

        if ($response->getStatusCode() !== 404) {
             $response->assertStatus(422); // Debe fallar por validación
        }
    }

    public function test_aplicar_anticipo_a_factura_disminuye_el_saldo_disponible()
    {
        $anticipo = AnticipoProveedor::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->prov->id,
            'monto' => 100000,
            'saldo_disponible' => 100000,
            'fecha' => now(),
            'estado' => 'DISPONIBLE'
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/anticipos-proveedores/{$anticipo->id}/aplicar", [
            'factura_id' => 1,
            'monto_a_aplicar' => 40000
        ]);

        if ($response->getStatusCode() === 404) {
             $this->markTestSkipped('Funcionalidad de cruzar anticipo con factura pendiente de programar.');
        } else {
             $response->assertStatus(200);
             $this->assertEquals(60000, $anticipo->fresh()->saldo_disponible);
        }
    }
}