<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;

class ComercialClienteProveedorTest extends TestCase
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

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Catálogos SpA']);
        $this->usuario = User::create(['nombre' => 'Admin', 'email' => 'admin@cat.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_rechaza_creacion_de_cliente_con_rut_duplicado_en_la_misma_empresa()
    {
        $this->actingAs($this->usuario)->postJson('/api/clientes', ['rut' => '76.543.210-K', 'razon_social' => 'Original']);
        $response = $this->actingAs($this->usuario)->postJson('/api/clientes', ['rut' => '76.543.210-K', 'razon_social' => 'Clon']);

        $response->assertStatus(422)->assertSee('ya se encuentra registrado');
    }

    public function test_rechaza_creacion_de_proveedor_con_rut_duplicado()
    {
        Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'PR-1', 'rut' => '77.123.456-7', 'razon_social' => 'Prov Original', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $response = $this->actingAs($this->usuario)->postJson('/api/proveedores', ['rut' => '77.123.456-7', 'razon_social' => 'Prov Clon']);

        $response->assertStatus(422)->assertSee('ya se encuentra registrado');
    }

    public function test_proveedor_genera_codigo_interno_automaticamente_al_crear()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/proveedores', ['rut' => '99.999.999-9', 'razon_social' => 'Dist Central']);
        $response->assertStatus(201);
        $this->assertStringStartsWith('PROV-', $response->json('codigo_generado'));
    }

    public function test_inactivar_cliente_cambia_su_estado_sin_eliminarlo_de_bd()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '44.444.444-4', 'razon_social' => 'Cliente A Borrar', 'estado' => 'ACTIVO']);
        $this->actingAs($this->usuario)->deleteJson("/api/clientes/{$cliente->id}")->assertStatus(200);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'estado' => 'INACTIVO']);
    }

    public function test_restaurar_cliente_inactivo_vuelve_a_estado_activo()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Muerto', 'estado' => 'INACTIVO']);
        $response = $this->actingAs($this->usuario)->patchJson("/api/clientes/{$cliente->id}/reactivar");

        $response->assertStatus(200);
        $this->assertEquals('ACTIVO', $cliente->fresh()->estado);
    }
}