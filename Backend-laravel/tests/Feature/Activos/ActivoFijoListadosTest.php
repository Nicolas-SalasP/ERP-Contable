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
use App\Domains\Activos\Models\ActivoFijo;

class ActivoFijoListadosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Listados', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'Auditor', 'email' => 'audit@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_listar_activos_solo_trae_estados_operativos_y_bajas()
    {
        ActivoFijo::create(['empresa_id' => $this->empresa->id, 'codigo' => 'AF-1', 'nombre' => 'Activo', 'valor_adquisicion' => 100, 'vida_util_meses' => 10, 'fecha_adquisicion' => now(), 'estado' => 'ACTIVO']);
        ActivoFijo::create(['empresa_id' => $this->empresa->id, 'codigo' => 'AF-2', 'nombre' => 'Baja', 'valor_adquisicion' => 100, 'vida_util_meses' => 10, 'fecha_adquisicion' => now(), 'estado' => 'DADO_DE_BAJA']);
        ActivoFijo::create(['empresa_id' => $this->empresa->id, 'codigo' => 'AF-3', 'nombre' => 'Pendiente', 'valor_adquisicion' => 100, 'vida_util_meses' => 10, 'fecha_adquisicion' => now(), 'estado' => 'PENDIENTE']);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos');
        $response->assertStatus(200);
        $response->assertJsonMissing(['codigo' => 'AF-3', 'estado' => 'PENDIENTE']);
        $response->assertJsonFragment(['codigo' => 'AF-1']);
        $response->assertJsonFragment(['codigo' => 'AF-2']);
    }

    public function test_listar_pendientes_excluye_activos_operativos()
    {
        ActivoFijo::create(['empresa_id' => $this->empresa->id, 'codigo' => 'AF-1', 'nombre' => 'Activo', 'valor_adquisicion' => 100, 'vida_util_meses' => 10, 'fecha_adquisicion' => now(), 'estado' => 'ACTIVO']);
        ActivoFijo::create(['empresa_id' => $this->empresa->id, 'codigo' => 'AF-3', 'nombre' => 'Pendiente', 'valor_adquisicion' => 100, 'vida_util_meses' => 10, 'fecha_adquisicion' => now(), 'estado' => 'PENDIENTE']);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/pendientes');

        $response->assertStatus(200);
        $response->assertJsonMissing(['codigo' => 'AF-1', 'estado' => 'ACTIVO']);
        $response->assertJsonFragment(['codigo' => 'AF-3', 'estado' => 'PENDIENTE']);
    }

    public function test_listados_vacios_retornan_status_200_y_no_arrojan_error()
    {
        $res1 = $this->actingAs($this->usuario)->getJson('/api/activos');
        $res2 = $this->actingAs($this->usuario)->getJson('/api/activos/pendientes');
        $res3 = $this->actingAs($this->usuario)->getJson('/api/activos/proyectos');

        $res1->assertStatus(200);
        $res2->assertStatus(200);
        $res3->assertStatus(200);
    }
}