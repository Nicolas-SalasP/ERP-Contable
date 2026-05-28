<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Contabilidad\Models\PlanCuenta;

class ActivoFijoFiltrosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '1.1.1-1', 'razon_social' => 'Filtros SPA']);
        $this->usuario = User::create([
            'nombre' => 'Filtro',
            'email' => 'f@erp.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '120101',
            'nombre' => 'Equipos', 'tipo' => 'ACTIVO',
            'imputable' => true, 'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '120151',
            'nombre' => 'Dep Ac', 'tipo' => 'ACTIVO',
            'imputable' => true, 'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '510101',
            'nombre' => 'Gasto', 'tipo' => 'GASTO',
            'imputable' => true, 'activo' => true,
        ]);
    }

    private function crearActivo(string $nombre, string $codigo): void
    {
        ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-01-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_busqueda_parcial_por_nombre()
    {
        $this->crearActivo('Notebook Lenovo', 'AF-001');
        $this->crearActivo('Notebook HP', 'AF-002');
        $this->crearActivo('Impresora Epson', 'AF-003');

        $response = $this->actingAs($this->usuario)->getJson('/api/activos?search=Notebook');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertCount(2, $items, 'Deberia traer los 2 notebooks');

        $nombres = collect($items)->pluck('nombre')->all();
        $this->assertContains('Notebook Lenovo', $nombres);
        $this->assertContains('Notebook HP', $nombres);
        $this->assertNotContains('Impresora Epson', $nombres);
    }

    public function test_busqueda_exacta_por_codigo()
    {
        $this->crearActivo('Notebook Lenovo', 'AF-LENOVO-001');
        $this->crearActivo('Notebook HP', 'AF-HP-001');

        $response = $this->actingAs($this->usuario)->getJson('/api/activos?search=LENOVO');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals('AF-LENOVO-001', $items[0]['codigo']);
    }

    public function test_paginacion_incluye_metadata_correcta()
    {
        // Crear 25 activos
        for ($i = 1; $i <= 25; $i++) {
            $this->crearActivo("Activo {$i}", sprintf('AF-%03d', $i));
        }

        $response = $this->actingAs($this->usuario)->getJson('/api/activos?per_page=10');

        $response->assertStatus(200);

        $meta = $response->json('meta');
        $this->assertNotNull($meta);
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(10, $meta['per_page']);
        $this->assertEquals(25, $meta['total']);
        $this->assertEquals(3, $meta['last_page']); // 25 / 10 = 3 paginas

        $items = $response->json('data');
        $this->assertCount(10, $items, 'Primera pagina debe tener 10 items');
    }
}
