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
use App\Domains\Comercial\Models\Proveedor;

class ComercialMantenimientoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Mantenimiento SpA']);
        $this->usuario = User::create(['nombre' => 'Operador', 'email' => 'op@m.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_actualizar_cliente_permite_guardar_sin_cambiar_el_rut()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '10.100.100-1', 'razon_social' => 'Cliente A', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->usuario)->putJson("/api/clientes/{$cliente->id}", [
            'rut' => '10.100.100-1',
            'razon_social' => 'Cliente A Modificado',
            'telefono' => '+56911112222'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Cliente A Modificado', $cliente->fresh()->razon_social);
        $this->assertEquals('+56911112222', $cliente->fresh()->telefono);
    }

    public function test_actualizar_cliente_bloquea_si_intentas_usar_un_rut_de_otro_cliente()
    {
        $cliente1 = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Juan', 'estado' => 'ACTIVO']);
        $cliente2 = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Pedro', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->usuario)->putJson("/api/clientes/{$cliente1->id}", [
            'rut' => '2.2.2.2-2',
            'razon_social' => 'Juan'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rut']);
    }

    public function test_paginador_respeta_el_limite_solicitado_por_el_frontend()
    {
        for ($i = 1; $i <= 15; $i++) {
            Proveedor::create([
                'empresa_id' => $this->empresa->id,
                'rut' => "9.9.9.{$i}-9",
                'razon_social' => "Prov {$i}",
                'codigo_interno' => "P-{$i}",
                'pais_iso' => 'CL',
                'moneda_defecto' => 'CLP'
            ]);
        }

        $response = $this->actingAs($this->usuario)->getJson('/api/proveedores?limit=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(15, $response->json('pagination.total'));
        $this->assertEquals(3, $response->json('pagination.totalPages'));
    }
}