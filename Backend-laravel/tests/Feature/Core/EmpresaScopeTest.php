<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Scopes\EmpresaScope;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;

class EmpresaScopeTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresaA;
    protected $usuarioA;
    protected $empresaB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresaA, $this->usuarioA] = $this->crearEmpresaConAdmin(['razon_social' => 'Empresa A'], ['email' => 'a@scope.cl']);
        [$this->empresaB] = $this->crearEmpresaConAdmin(['razon_social' => 'Empresa B'], ['email' => 'b@scope.cl']);
    }

    public function test_usuario_autenticado_solo_ve_registros_de_su_empresa()
    {
        $clienteB = Cliente::create(['empresa_id' => $this->empresaB->id, 'rut' => '9.9.9.9-9', 'razon_social' => 'Cliente B', 'estado' => 'ACTIVO']);
        Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '8.8.8.8-8', 'razon_social' => 'Cliente A', 'estado' => 'ACTIVO']);

        $this->actingAs($this->usuarioA);

        $this->assertSame(1, Cliente::count(), 'Con scope activo solo debe contar los clientes de la empresa A');
        $this->assertNull(Cliente::find($clienteB->id), 'El cliente de la empresa B no debe ser visible');
    }

    public function test_without_global_scope_permite_acceso_cross_empresa()
    {
        $clienteB = Cliente::create(['empresa_id' => $this->empresaB->id, 'rut' => '7.7.7.7-7', 'razon_social' => 'Cliente B', 'estado' => 'ACTIVO']);

        $this->actingAs($this->usuarioA);

        $this->assertNull(Cliente::find($clienteB->id));
        $this->assertNotNull(
            Cliente::withoutGlobalScope(EmpresaScope::class)->find($clienteB->id),
            'withoutGlobalScope debe permitir ver registros de otra empresa'
        );
    }

    public function test_sin_autenticacion_no_se_aplica_el_scope()
    {
        $proveedorB = Proveedor::create(['empresa_id' => $this->empresaB->id, 'rut' => '6.6.6.6-6', 'razon_social' => 'Prov B', 'codigo_interno' => 'PB-S', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $this->assertNotNull(Proveedor::find($proveedorB->id));
    }
}
