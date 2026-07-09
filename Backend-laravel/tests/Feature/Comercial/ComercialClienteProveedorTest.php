<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ComercialClienteProveedorTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected $empresa;

    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Catálogos SpA']);
        $this->usuario = User::create(['nombre' => 'Admin', 'email' => 'admin@cat.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_rechaza_creacion_de_cliente_con_rut_duplicado_en_la_misma_empresa()
    {
        $this->actingAs($this->usuario)->postJson('/api/clientes', ['rut' => '76.543.210-3', 'razon_social' => 'Original']);
        $response = $this->actingAs($this->usuario)->postJson('/api/clientes', ['rut' => '76.543.210-3', 'razon_social' => 'Clon']);

        $response->assertStatus(422)->assertSee('ya se encuentra registrado');
    }

    public function test_rechaza_creacion_de_proveedor_con_rut_duplicado()
    {
        Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'PR-1', 'rut' => '77.123.456-9', 'razon_social' => 'Prov Original', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $response = $this->actingAs($this->usuario)->postJson('/api/proveedores', ['rut' => '77.123.456-9', 'razon_social' => 'Prov Clon']);

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

    public function test_ficha_cliente_devuelve_cliente_y_sus_facturas_de_venta()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '55.555.555-5', 'razon_social' => 'Cliente Ficha', 'estado' => 'ACTIVO']);
        Factura::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'numero_factura' => 'V-001', 'codigo_unico' => 111222, 'fecha_emision' => now(), 'monto_bruto' => 50000, 'monto_neto' => 42017, 'monto_iva' => 7983, 'tipo' => 'VENTA', 'tipo_documento' => 'FACTURA', 'estado' => 'REGISTRADA']);

        $response = $this->actingAs($this->usuario)->getJson("/api/clientes/ficha/{$cliente->id}");

        $response->assertOk();
        $response->assertJsonPath('data.cliente.id', $cliente->id);
        $response->assertJsonCount(1, 'data.facturas');
        $response->assertJsonPath('data.facturas.0.numero_factura', 'V-001');
    }

    public function test_ficha_cliente_de_otra_empresa_no_encontrada()
    {
        $otraEmpresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Otra SpA']);
        $clienteAjeno = Cliente::create(['empresa_id' => $otraEmpresa->id, 'rut' => '66.666.666-6', 'razon_social' => 'Cliente Ajeno', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->usuario)->getJson("/api/clientes/ficha/{$clienteAjeno->id}");

        $response->assertStatus(404);
    }

    public function test_inactivar_proveedor_cambia_su_estado_sin_eliminarlo_de_bd()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'PR-2', 'rut' => '33.333.333-3', 'razon_social' => 'Proveedor A Bloquear', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP', 'estado' => 'ACTIVO']);
        $this->actingAs($this->usuario)->deleteJson("/api/proveedores/{$proveedor->id}")->assertStatus(200);
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'estado' => 'INACTIVO']);
    }

    public function test_activar_proveedor_inactivo_vuelve_a_estado_activo()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'PR-3', 'rut' => '22.222.222-2', 'razon_social' => 'Proveedor Bloqueado', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP', 'estado' => 'INACTIVO']);
        $response = $this->actingAs($this->usuario)->putJson("/api/proveedores/{$proveedor->id}/activar");

        $response->assertStatus(200);
        $this->assertEquals('ACTIVO', $proveedor->fresh()->estado);
    }

    public function test_proveedor_bloqueado_no_puede_usarse_para_registrar_factura_de_compra()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'PR-4', 'rut' => '11.111.111-1', 'razon_social' => 'Proveedor Inactivo', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP', 'estado' => 'INACTIVO']);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'C-001',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now()->toDateString(),
            'monto_neto' => 10000,
            'monto_iva' => 1900,
            'monto_bruto' => 11900,
            'cuentaDestino' => '600100',
        ]);

        $response->assertStatus(422)->assertSee('proveedor inactivo');
    }
}
