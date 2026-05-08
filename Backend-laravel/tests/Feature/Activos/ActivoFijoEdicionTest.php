<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Activos\Models\ActivoFijo;

class ActivoFijoEdicionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Edicion SPA']);
        $this->usuario = User::create(['nombre' => 'Editor', 'email' => 'edit@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_puede_editar_nombre_y_descripcion_de_activo_operativo()
    {
        $this->markTestSkipped('Ruta PUT /api/activos/{id} pendiente de desarrollo.');
    }

    public function test_ignora_intentos_de_modificar_valores_contables_vaya_api_put()
    {
        $this->markTestSkipped('Ruta PUT /api/activos/{id} pendiente de desarrollo.');
    }

    public function test_no_puede_editar_activo_dado_de_baja()
    {
        $this->markTestSkipped('Ruta PUT /api/activos/{id} pendiente de desarrollo.');
    }

    public function test_edicion_de_activo_ajeno_retorna_404()
    {
        $this->markTestSkipped('Ruta PUT /api/activos/{id} pendiente de desarrollo.');
    }
}