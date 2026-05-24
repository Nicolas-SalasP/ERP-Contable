<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CafServiceSaldosTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private CafService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = new CafService(new CafXmlParser());
    }

    public function test_obtener_saldo_devuelve_estructura_correcta_con_un_caf(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create([
            'empresa_id'  => $empresa->id,
            'folio_desde' => 1,
            'folio_hasta' => 50,
            'folio_actual' => 1,
        ]);

        $saldo = $this->service->obtenerSaldoPorTipo($empresa->id, 33);

        $this->assertSame(50, $saldo['total_autorizado']);
        $this->assertSame(50, $saldo['disponibles']);
        $this->assertSame(0, $saldo['usados']);
        $this->assertSame(0, $saldo['huerfanos']);
        $this->assertSame(1, $saldo['cafs_activos']);
        $this->assertSame(0, $saldo['cafs_agotados']);
    }

    public function test_obtener_saldo_suma_correctamente_multiples_cafs_activos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id, 'folio_desde' => 1,   'folio_hasta' => 50,  'folio_actual' => 1]);
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id, 'folio_desde' => 100, 'folio_hasta' => 200, 'folio_actual' => 100]);

        $saldo = $this->service->obtenerSaldoPorTipo($empresa->id, 33);

        $this->assertSame(50 + 101, $saldo['total_autorizado']);
        $this->assertSame(50 + 101, $saldo['disponibles']);
        $this->assertSame(2, $saldo['cafs_activos']);
    }

    public function test_obtener_saldo_no_incluye_revocados(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id, 'folio_desde' => 1, 'folio_hasta' => 50, 'folio_actual' => 1]);
        SiiCaf::factory()->tipo33()->revocado()->create(['empresa_id' => $empresa->id, 'folio_desde' => 100, 'folio_hasta' => 200]);

        $saldo = $this->service->obtenerSaldoPorTipo($empresa->id, 33);

        $this->assertSame(50, $saldo['total_autorizado']);
        $this->assertSame(50, $saldo['disponibles']);
        $this->assertSame(1, $saldo['cafs_activos']);
        $this->assertSame(0, $saldo['cafs_agotados']);
    }

    public function test_obtener_saldo_distingue_disponibles_de_usados_y_huerfanos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create([
            'empresa_id'      => $empresa->id,
            'folio_desde'     => 1,
            'folio_hasta'     => 10,
            'folio_actual'    => 4,
            'folios_usados'   => 2,
            'folios_huerfanos' => 1,
        ]);

        $saldo = $this->service->obtenerSaldoPorTipo($empresa->id, 33);

        $this->assertSame(10, $saldo['total_autorizado']);
        $this->assertSame(7, $saldo['disponibles']);          // 10-4+1 = 7
        $this->assertSame(2, $saldo['usados']);
        $this->assertSame(1, $saldo['huerfanos']);
    }
}
