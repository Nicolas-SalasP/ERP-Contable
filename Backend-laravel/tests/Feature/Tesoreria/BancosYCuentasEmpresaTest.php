<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Tesoreria\Models\CatalogoBanco;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use Laravel\Sanctum\Sanctum;

class BancosYCuentasEmpresaTest extends TestCase
{
    use RefreshDatabase;

    protected $empresaA;
    protected $empresaB;
    protected $adminA;
    protected $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $estadoActivo = EstadoSuscripcion::create(['nombre' => 'Activa']);
        $rolAdmin = Rol::create(['nombre' => 'Admin', 'jerarquia' => 100]);

        $this->empresaA = Empresa::create(['rut' => '11.111.111-1', 'razon_social' => 'Empresa A']);
        $this->empresaB = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Empresa B']);

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

        CatalogoBanco::create(['nombre' => 'Banco Estado']);
        CatalogoBanco::create(['nombre' => 'Banco Santander']);
    }

    public function test_catalogo_bancos_es_accesible_con_autenticacion()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/tesoreria/bancos-catalogo');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }

    public function test_catalogo_bancos_rechaza_acceso_sin_token()
    {
        $response = $this->getJson('/api/tesoreria/bancos-catalogo');
        $response->assertStatus(401);
    }

    public function test_listar_cuentas_empresa_vacias()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/tesoreria/cuentas-propias');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_listar_cuentas_empresa_aisla_tenant()
    {
        CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaB->id,
            'banco' => 'Banco',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/tesoreria/cuentas-propias');
        $this->assertCount(0, $response->json('data'));
    }

    public function test_listar_cuentas_empresa_devuelve_datos()
    {
        CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'Banco',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '999',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/tesoreria/cuentas-propias');
        $this->assertCount(1, $response->json('data'));
    }

    public function test_crear_cuenta_empresa_exitosamente()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/cuentas-propias', [
            'banco' => 'Banco de Chile',
            'tipo_cuenta' => 'Corriente',
            'numero_cuenta' => '123456',
            'titular' => 'Empresa A',
            'rut_titular' => '11.111.111-1'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseHas('cuentas_bancarias_empresa', ['numero_cuenta' => '123456']);
    }

    public function test_rechaza_crear_cuenta_con_payload_vacio()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/cuentas-propias', []);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_rechaza_crear_cuenta_con_rut_excesivo()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/cuentas-propias', [
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => str_repeat('1', 50)
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_rechaza_crear_cuenta_duplicada()
    {
        CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/cuentas-propias', [
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_permite_mismo_numero_cuenta_en_diferente_empresa()
    {
        CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaB->id,
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/cuentas-propias', [
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
    }

    public function test_previene_mass_assignment_en_creacion_de_cuenta()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/cuentas-propias', [
            'empresa_id' => $this->empresaB->id,
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        $this->assertDatabaseHas('cuentas_bancarias_empresa', [
            'numero_cuenta' => '1',
            'empresa_id' => $this->empresaA->id
        ]);
    }

    public function test_crear_cuenta_metodo_incorrecto_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson('/api/tesoreria/cuentas-propias/store');
        $response->assertStatus(404);
    }

    public function test_rechaza_crear_cuenta_con_array_en_vez_de_string()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/cuentas-propias', [
            'banco' => ['Banco'],
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_actualizar_cuenta_empresa_exitosamente()
    {
        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->putJson("/api/empresas/bancos/{$cuenta->id}", [
            'banco' => 'Nuevo Banco'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseHas('cuentas_bancarias_empresa', ['id' => $cuenta->id, 'banco' => 'Nuevo Banco']);
    }

    public function test_idor_rechaza_actualizar_cuenta_de_otra_empresa()
    {
        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaB->id,
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->putJson("/api/empresas/bancos/{$cuenta->id}", [
            'numero_cuenta' => '999'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404, 500]));
    }

    public function test_actualizar_cuenta_inexistente_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->putJson("/api/empresas/bancos/9999", ['banco' => 'X']);
        $this->assertTrue(in_array($response->getStatusCode(), [404, 500]));
    }

    public function test_eliminar_cuenta_empresa_exitosamente()
    {
        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->deleteJson("/api/empresas/bancos/{$cuenta->id}");
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseMissing('cuentas_bancarias_empresa', ['id' => $cuenta->id]);
    }

    public function test_idor_rechaza_eliminar_cuenta_de_otra_empresa()
    {
        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaB->id,
            'banco' => 'B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);
        Sanctum::actingAs($this->adminA);
        $response = $this->deleteJson("/api/empresas/bancos/{$cuenta->id}");
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404, 500]));
    }

    public function test_eliminar_cuenta_inexistente_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->deleteJson("/api/empresas/bancos/99999");
        $this->assertTrue(in_array($response->getStatusCode(), [404, 500]));
    }

    public function test_eliminar_cuenta_con_id_texto_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->deleteJson("/api/empresas/bancos/texto");
        $this->assertTrue(in_array($response->getStatusCode(), [400, 404, 422, 500]));
    }
}