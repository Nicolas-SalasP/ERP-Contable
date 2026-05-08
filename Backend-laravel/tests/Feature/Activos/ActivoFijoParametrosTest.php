<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\CentroCosto;

class ActivoFijoParametrosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Params', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'Parametrizador', 'email' => 'param@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_excluye_cuentas_no_imputables_o_inactivas()
    {
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '100', 'nombre' => 'Activo Inactivo', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => false]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '101', 'nombre' => 'Activo Titulo', 'tipo' => 'ACTIVO', 'imputable' => false, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '102', 'nombre' => 'Activo Valido', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/parametros');

        $data = $response->json('data.cuentas_activo');

        $this->assertCount(1, $data);
        $this->assertEquals('Activo Valido', $data[0]['nombre']);
    }

    public function test_clasifica_cuentas_de_depreciacion_ignorando_mayusculas_o_minusculas()
    {
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '200', 'nombre' => 'DEPRECIACION acumulada', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '201', 'nombre' => 'Otra deprecia', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '202', 'nombre' => 'Vehiculo', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/parametros');

        $depre = $response->json('data.cuentas_depreciacion');
        $activo = $response->json('data.cuentas_activo');

        $this->assertCount(2, $depre);
        $this->assertCount(1, $activo);
    }

    public function test_ignora_cuentas_de_pasivo_o_patrimonio()
    {
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '300', 'nombre' => 'Cuentas por Pagar', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '301', 'nombre' => 'Capital', 'tipo' => 'PATRIMONIO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/parametros');

        $this->assertEmpty($response->json('data.cuentas_activo'));
        $this->assertEmpty($response->json('data.cuentas_gasto'));
    }

    public function test_excluye_centros_de_costo_inactivos()
    {
        CentroCosto::create(['empresa_id' => $this->empresa->id, 'nombre' => 'TI', 'codigo' => 'CC-1', 'activo' => true]);
        CentroCosto::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Gerencia', 'codigo' => 'CC-2', 'activo' => false]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/parametros');

        $centros = $response->json('data.centros_costo');
        $this->assertCount(1, $centros);
        $this->assertEquals('TI', $centros[0]['nombre']);
    }
}