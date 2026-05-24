<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CafServiceRevocarTest extends TestCase
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

    public function test_revocar_caf_activo_cambia_estado_a_revocado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $caf       = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        $this->service->revocar($caf, 'No se va a usar mas');

        $this->assertSame(SiiCaf::ESTADO_REVOCADO, $caf->fresh()->estado);
    }

    public function test_revocar_libera_folios_RESERVADOS_como_HUERFANO_con_motivo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $caf       = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 1]);
        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 2]);

        $this->service->revocar($caf, 'Empresa cambio de razon social');

        $huerfanos = SiiCafFolioUso::where('caf_id', $caf->id)->where('estado', 'HUERFANO')->get();
        $this->assertCount(2, $huerfanos);

        foreach ($huerfanos as $folio) {
            $this->assertStringContainsString('CAF revocado:', $folio->razon_liberacion);
            $this->assertStringContainsString('Empresa cambio de razon social', $folio->razon_liberacion);
            $this->assertNotNull($folio->liberado_at);
        }
    }

    public function test_revocar_no_toca_folios_USADOS(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $caf       = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        SiiCafFolioUso::factory()->usado()->create(['caf_id' => $caf->id, 'folio' => 1]);
        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 2]);

        $this->service->revocar($caf, 'razon valida');

        $usado = SiiCafFolioUso::where('caf_id', $caf->id)->where('folio', 1)->first();
        $this->assertSame('USADO', $usado->estado);
        $this->assertNull($usado->razon_liberacion);
        $this->assertNull($usado->liberado_at);
    }

    public function test_revocar_lanza_LogicException_si_ya_revocado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $caf       = SiiCaf::factory()->revocado()->create(['empresa_id' => $empresa->id]);

        $this->expectException(LogicException::class);
        $this->service->revocar($caf, 'segundo intento');
    }

    public function test_revocar_actualiza_contador_folios_huerfanos_del_caf(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $caf       = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 1]);
        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 2]);
        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 3]);

        $this->service->revocar($caf, 'sin uso');

        $this->assertSame(3, $caf->fresh()->folios_huerfanos);
    }
}
