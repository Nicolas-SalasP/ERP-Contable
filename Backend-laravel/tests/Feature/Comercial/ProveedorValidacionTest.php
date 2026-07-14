<?php

namespace Tests\Feature\Comercial;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ProveedorValidacionTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected $empresa;

    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Validacion SpA']);
        $this->usuario = User::create([
            'nombre' => 'Admin',
            'email' => 'admin@validacion.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    public function test_store_sin_razon_social_devuelve_422_y_no_500()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/proveedores', [
            'rut' => '99.999.999-9',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['razonSocial']);
    }

    public function test_store_con_email_contacto_invalido_devuelve_422()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/proveedores', [
            'razonSocial' => 'Proveedor Email Malo',
            'emailContacto' => 'no-es-un-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['emailContacto']);
    }

    public function test_store_ignora_empresa_id_inyectado_en_el_body_y_usa_la_del_usuario_autenticado()
    {
        $otraEmpresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Otra SpA']);

        $response = $this->actingAs($this->usuario)->postJson('/api/proveedores', [
            'razonSocial' => 'Proveedor Multitenant',
            'empresa_id' => $otraEmpresa->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('proveedores', [
            'razon_social' => 'Proveedor Multitenant',
            'empresa_id' => $this->empresa->id,
        ]);

        $this->assertDatabaseMissing('proveedores', [
            'razon_social' => 'Proveedor Multitenant',
            'empresa_id' => $otraEmpresa->id,
        ]);
    }

    public function test_store_con_rut_chileno_invalido_devuelve_422_via_regla_de_negocio()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/proveedores', [
            'razonSocial' => 'Proveedor Rut Malo',
            'rut' => '11.111.111-5',
        ]);

        $response->assertStatus(422);
        $response->assertSee('no es un RUT chileno valido');

        $this->assertDatabaseMissing('proveedores', [
            'razon_social' => 'Proveedor Rut Malo',
        ]);
    }
}
