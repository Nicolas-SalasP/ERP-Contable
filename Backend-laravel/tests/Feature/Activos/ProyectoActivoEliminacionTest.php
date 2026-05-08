<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;

class ProyectoActivoEliminacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '1.1.1-1', 'razon_social' => 'Del SPA']);
        $this->usuario = User::create(['nombre' => 'Del', 'email' => 'd@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_puede_eliminar_proyecto_en_construccion_vacio()
    {
        $this->markTestSkipped('Ruta DELETE /api/activos/proyectos/{id} pendiente de desarrollo.');
    }

    public function test_rechaza_eliminar_proyecto_con_facturas_vinculadas()
    {
        $this->markTestSkipped('Ruta DELETE /api/activos/proyectos/{id} pendiente de desarrollo.');
    }

    public function test_desvincular_factura_resta_costo_al_proyecto()
    {
        $this->markTestSkipped('Ruta DELETE para desvincular facturas pendiente de desarrollo.');
    }
}