<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CafServiceLiberarHuerfanoTest extends TestCase
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

    public function test_liberar_huerfano_cambia_estado_y_setea_liberado_at(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);
        $uso = $this->service->reservarSiguienteFolio($empresa->id, 33);

        $this->service->liberarFolioHuerfano($uso->id, 'Falla de firma en pre-envio');

        $uso->refresh();
        $this->assertSame(SiiCafFolioUso::ESTADO_HUERFANO, $uso->estado);
        $this->assertNotNull($uso->liberado_at);
        $this->assertStringContainsString('Falla de firma', $uso->razon_liberacion);
    }

    public function test_liberar_huerfano_incrementa_folios_huerfanos_del_caf(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $caf       = SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);

        $uso = $this->service->reservarSiguienteFolio($empresa->id, 33);
        $this->service->liberarFolioHuerfano($uso->id, 'Test');

        $this->assertSame(1, $caf->fresh()->folios_huerfanos);
    }

    public function test_folio_liberado_huerfano_no_se_reasigna(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id, 'folio_desde' => 1, 'folio_hasta' => 3, 'folio_actual' => 1]);

        // Reservo folio 1, lo libero como huerfano.
        $uso1 = $this->service->reservarSiguienteFolio($empresa->id, 33);
        $this->assertSame(1, $uso1->folio);
        $this->service->liberarFolioHuerfano($uso1->id, 'Aborto');

        // Reserva siguiente: debe ser 2, NUNCA 1 (regla SII).
        $uso2 = $this->service->reservarSiguienteFolio($empresa->id, 33);
        $this->assertSame(2, $uso2->folio);
    }
}
