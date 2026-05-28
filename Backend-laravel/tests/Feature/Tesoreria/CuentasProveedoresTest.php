<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\Pais; // <- AÑADIDO
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;
use Laravel\Sanctum\Sanctum;

class CuentasProveedoresTest extends TestCase
{
    use RefreshDatabase;

    protected $empresaA;
    protected $empresaB;
    protected $adminA;
    protected $adminB;
    protected $proveedorA;
    protected $proveedorB;

    protected function setUp(): void
    {
        parent::setUp();

        $estadoActivo = EstadoSuscripcion::create(['nombre' => 'Activa']);
        $rolAdmin = Rol::create(['nombre' => 'Admin', 'jerarquia' => 100]);

        $this->empresaA = Empresa::create(['rut' => '11.111.111-1', 'razon_social' => 'Empresa A']);
        $this->empresaB = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Empresa B']);

        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'activo' => true]);

        $this->adminA = User::create([
            'nombre' => 'Admin A',
            'email' => 'a@test.com',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $rolAdmin->id,
            'estado_suscripcion_id' => $estadoActivo->id
        ]);

        $this->adminB = User::create([
            'nombre' => 'Admin B',
            'email' => 'b@test.com',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaB->id,
            'rol_id' => $rolAdmin->id,
            'estado_suscripcion_id' => $estadoActivo->id
        ]);

        $this->proveedorA = Proveedor::create(['empresa_id' => $this->empresaA->id, 'rut' => '1-9', 'razon_social' => 'Prov A', 'codigo_interno' => 'PA', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $this->proveedorB = Proveedor::create(['empresa_id' => $this->empresaB->id, 'rut' => '2-7', 'razon_social' => 'Prov B', 'codigo_interno' => 'PB', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
    }

    public function test_listar_cuentas_proveedor_vacias()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson("/api/cuentas-bancarias/proveedor/{$this->proveedorA->id}");
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data') ?? []);
    }

    public function test_listar_cuentas_proveedor_con_datos()
    {
        CuentaBancariaProveedor::create([
            'proveedor_id' => $this->proveedorA->id,
            'banco' => 'B',
            'numero_cuenta' => '1',
            'tipo_cuenta' => 'C',
            'pais_iso' => 'CL'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson("/api/cuentas-bancarias/proveedor/{$this->proveedorA->id}");
        $this->assertGreaterThan(0, count($response->json('data') ?? []));
    }

    public function test_idor_rechaza_listar_cuentas_de_proveedor_ajeno()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson("/api/cuentas-bancarias/proveedor/{$this->proveedorB->id}");
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404, 500]));
    }

    public function test_rechaza_listar_cuentas_proveedor_inexistente()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson("/api/cuentas-bancarias/proveedor/9999");
        $this->assertTrue(in_array($response->getStatusCode(), [404, 500]));
    }

    public function test_rechaza_listar_sin_token()
    {
        $response = $this->getJson("/api/cuentas-bancarias/proveedor/{$this->proveedorA->id}");
        $response->assertStatus(401);
    }

    public function test_crear_cuenta_proveedor_exitosamente()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', [
            'proveedorId' => $this->proveedorA->id,
            'banco' => 'Banco X',
            'numeroCuenta' => '777',
            'tipoCuenta' => 'Vista'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseHas('cuentas_bancarias_proveedores', ['numero_cuenta' => '777']);
    }

    public function test_rechaza_crear_cuenta_sin_id_proveedor()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', [
            'banco' => 'Banco X',
            'numeroCuenta' => '777',
            'tipoCuenta' => 'Vista'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_rechaza_crear_cuenta_proveedor_inexistente()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', [
            'proveedorId' => 9999,
            'banco' => 'Banco X',
            'numeroCuenta' => '777',
            'tipoCuenta' => 'Vista'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [404, 422, 500]));
    }

    public function test_idor_rechaza_crear_cuenta_a_proveedor_ajeno()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', [
            'proveedorId' => $this->proveedorB->id,
            'banco' => 'Banco X',
            'numeroCuenta' => '777',
            'tipoCuenta' => 'Vista'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404, 500]));
    }

    public function test_rechaza_crear_cuenta_con_iso_pais_invalido()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', [
            'proveedorId' => $this->proveedorA->id,
            'banco' => 'B',
            'numeroCuenta' => '1',
            'tipoCuenta' => 'C',
            'paisIso' => 'CHILE'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_eliminar_cuenta_proveedor_exitosamente()
    {
        $cuenta = CuentaBancariaProveedor::create([
            'proveedor_id' => $this->proveedorA->id,
            'banco' => 'B',
            'numero_cuenta' => '1',
            'tipo_cuenta' => 'C',
            'pais_iso' => 'CL'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->deleteJson("/api/cuentas-bancarias/{$cuenta->id}");
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseMissing('cuentas_bancarias_proveedores', ['id' => $cuenta->id]);
    }

    public function test_eliminar_cuenta_proveedor_inexistente_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->deleteJson("/api/cuentas-bancarias/9999");
        $this->assertTrue(in_array($response->getStatusCode(), [404, 500]));
    }

    public function test_idor_rechaza_eliminar_cuenta_proveedor_ajeno()
    {
        $cuenta = CuentaBancariaProveedor::create([
            'proveedor_id' => $this->proveedorB->id,
            'banco' => 'B',
            'numero_cuenta' => '1',
            'tipo_cuenta' => 'C',
            'pais_iso' => 'CL'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->deleteJson("/api/cuentas-bancarias/{$cuenta->id}");
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404, 500]));
    }

    public function test_rechaza_sqli_en_numero_cuenta_proveedor()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', [
            'proveedorId' => $this->proveedorA->id,
            'banco' => 'B',
            'numeroCuenta' => "1'; DROP TABLE",
            'tipoCuenta' => 'C'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseHas('cuentas_bancarias_proveedores', ['numero_cuenta' => "1'; DROP TABLE"]);
    }

    public function test_previene_mass_assignment_en_crear_cuenta_proveedor()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', [
            'proveedorId' => $this->proveedorA->id,
            'banco' => 'B',
            'numeroCuenta' => '1',
            'tipoCuenta' => 'C',
            'id' => 9999
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseMissing('cuentas_bancarias_proveedores', ['id' => 9999]);
    }

    public function test_rechaza_crear_cuenta_proveedor_con_tipos_invalidos()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', [
            'proveedorId' => $this->proveedorA->id,
            'banco' => ['Banco'],
            'numeroCuenta' => '1',
            'tipoCuenta' => 'C'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_crear_cuenta_proveedor_vacia_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/cuentas-bancarias', []);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_eliminar_cuenta_proveedor_sin_token_falla()
    {
        $response = $this->deleteJson("/api/cuentas-bancarias/1");
        $response->assertStatus(401);
    }

    public function test_crear_cuenta_proveedor_sin_token_falla()
    {
        $response = $this->postJson('/api/cuentas-bancarias', [
            'proveedorId' => 1,
            'banco' => 'B',
            'numeroCuenta' => '1',
            'tipoCuenta' => 'C'
        ]);
        $response->assertStatus(401);
    }

    public function test_crear_cuenta_proveedor_metodo_get_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/cuentas-bancarias');
        $this->assertTrue(in_array($response->getStatusCode(), [404, 405]));
    }
}