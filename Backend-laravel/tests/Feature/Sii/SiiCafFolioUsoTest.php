<?php

namespace Tests\Feature\Sii;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCafFolioUsoTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_crear_folio_uso_persiste(): void
    {
        $caf = SiiCaf::factory()->create();
        $uso = SiiCafFolioUso::factory()->create(['caf_id' => $caf->id, 'folio' => 1]);

        $this->assertNotNull($uso->id);
        $this->assertSame($caf->id, $uso->caf_id);
        $this->assertSame(1, $uso->folio);
    }

    public function test_cascade_on_delete_desde_caf(): void
    {
        $caf = SiiCaf::factory()->create();
        SiiCafFolioUso::factory()->create(['caf_id' => $caf->id, 'folio' => 1]);

        $caf->delete();
        $this->assertSame(0, SiiCafFolioUso::count());
    }

    public function test_belongs_to_dte_emitido_nullable(): void
    {
        $caf = SiiCaf::factory()->create();
        $uso = SiiCafFolioUso::factory()->create(['caf_id' => $caf->id, 'folio' => 1, 'dte_emitido_id' => null]);

        $this->assertNull($uso->dteEmitido);

        $dte = SiiDteEmitido::factory()->create();
        $uso->update(['dte_emitido_id' => $dte->id]);

        $this->assertInstanceOf(SiiDteEmitido::class, $uso->fresh()->dteEmitido);
    }

    public function test_scopes_reservados_usados_huerfanos(): void
    {
        $caf = SiiCaf::factory()->create();

        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 1]);
        SiiCafFolioUso::factory()->usado()->create(['caf_id' => $caf->id, 'folio' => 2]);
        SiiCafFolioUso::factory()->huerfano()->create(['caf_id' => $caf->id, 'folio' => 3]);

        $this->assertSame(1, SiiCafFolioUso::reservados()->count());
        $this->assertSame(1, SiiCafFolioUso::usados()->count());
        $this->assertSame(1, SiiCafFolioUso::huerfanos()->count());
    }

    public function test_unique_compuesto_caf_folio_bloquea_duplicado(): void
    {
        $caf = SiiCaf::factory()->create();
        SiiCafFolioUso::factory()->create(['caf_id' => $caf->id, 'folio' => 1]);

        $this->expectException(QueryException::class);

        SiiCafFolioUso::factory()->create(['caf_id' => $caf->id, 'folio' => 1]);
    }
}
