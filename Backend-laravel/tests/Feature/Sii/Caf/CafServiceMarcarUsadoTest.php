<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Exceptions\FolioUsoEstadoInvalidoException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CafServiceMarcarUsadoTest extends TestCase
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

    public function test_marcar_usado_cambia_estado_y_setea_usado_at(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);

        $uso = $this->service->reservarSiguienteFolio($empresa->id, 33);
        $dte = SiiDteEmitido::factory()->create(['empresa_id' => $empresa->id]);

        $this->service->marcarFolioUsado($uso->id, $dte->id);

        $uso->refresh();
        $this->assertSame(SiiCafFolioUso::ESTADO_USADO, $uso->estado);
        $this->assertSame($dte->id, $uso->dte_emitido_id);
        $this->assertNotNull($uso->usado_at);
    }

    public function test_marcar_usado_incrementa_folios_usados_del_caf(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $caf       = SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);

        $uso1 = $this->service->reservarSiguienteFolio($empresa->id, 33);
        $uso2 = $this->service->reservarSiguienteFolio($empresa->id, 33);
        $dte  = SiiDteEmitido::factory()->create(['empresa_id' => $empresa->id]);

        $this->service->marcarFolioUsado($uso1->id, $dte->id);
        $this->assertSame(1, $caf->fresh()->folios_usados);

        $dte2 = SiiDteEmitido::factory()->create(['empresa_id' => $empresa->id]);
        $this->service->marcarFolioUsado($uso2->id, $dte2->id);
        $this->assertSame(2, $caf->fresh()->folios_usados);
    }

    /**
     * Regresion: si el CAF fue revocado (folio pasa a HUERFANO) mientras el DTE se estaba firmando,
     * marcarFolioUsado() NO debe sobreescribir silenciosamente el HUERFANO a USADO. Antes del fix,
     * esto emitia un DTE valido sobre un folio invalido ante el SII y ademas doble-contaba el folio
     * en folios_usados + folios_huerfanos simultaneamente.
     */
    public function test_marcar_usado_lanza_excepcion_si_el_folio_ya_no_esta_reservado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $caf       = SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);

        $uso = $this->service->reservarSiguienteFolio($empresa->id, 33);
        $dte = SiiDteEmitido::factory()->create(['empresa_id' => $empresa->id]);

        // Simula la revocacion concurrente del CAF: el folio reservado pasa a HUERFANO.
        $this->service->revocar($caf, 'Revocado mientras el DTE se firmaba');
        $this->assertSame(1, $caf->fresh()->folios_huerfanos);

        $this->expectException(FolioUsoEstadoInvalidoException::class);

        try {
            $this->service->marcarFolioUsado($uso->id, $dte->id);
        } finally {
            $uso->refresh();
            $this->assertSame(SiiCafFolioUso::ESTADO_HUERFANO, $uso->estado, 'El folio debe permanecer HUERFANO, no sobreescrito a USADO.');
            $this->assertNull($uso->dte_emitido_id);

            $cafFresh = $caf->fresh();
            $this->assertSame(0, $cafFresh->folios_usados, 'folios_usados no debe incrementarse sobre un folio ya revocado.');
            $this->assertSame(1, $cafFresh->folios_huerfanos, 'folios_huerfanos no debe duplicarse.');
        }
    }
}
