<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Activos\Models\ActivoFijo;

class ActivoFijoFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Domains\Core\Models\EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = \App\Domains\Core\Models\Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        $this->empresa = Empresa::create(['rut' => '1.1.1-1', 'razon_social' => 'Filtros SPA']);
        $this->usuario = User::create(['nombre' => 'Filtro', 'email' => 'f@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_busqueda_parcial_por_nombre()
    {
        $this->markTestSkipped('Filtro ?search= pendiente de programar en el Controlador.');
    }

    public function test_busqueda_exacta_por_codigo()
    {
        $this->markTestSkipped('Filtro ?search= pendiente de programar en el Controlador.');
    }

    public function test_paginacion_incluye_metadata_correcta()
    {
        $this->markTestSkipped('Paginación ?per_page= pendiente de programar en el Controlador.');
    }
}