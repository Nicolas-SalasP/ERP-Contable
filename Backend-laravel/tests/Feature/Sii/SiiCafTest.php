<?php

namespace Tests\Feature\Sii;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCafTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_crear_caf_con_factory_persiste(): void
    {
        $caf = SiiCaf::factory()->create();

        $this->assertNotNull($caf->id);
        $this->assertSame(33, $caf->tipo_dte);
        $this->assertSame('activo', $caf->estado);
    }

    public function test_relacion_belongs_to_empresa(): void
    {
        $caf = SiiCaf::factory()->create();
        $this->assertInstanceOf(Empresa::class, $caf->empresa);
    }

    public function test_relacion_has_many_folios(): void
    {
        $caf = SiiCaf::factory()->rangoChico()->create();

        SiiCafFolioUso::factory()->create(['caf_id' => $caf->id, 'folio' => 1]);
        SiiCafFolioUso::factory()->create(['caf_id' => $caf->id, 'folio' => 2]);

        $this->assertCount(2, $caf->fresh()->folios);
    }

    public function test_scope_activos_filtra_correctamente(): void
    {
        SiiCaf::factory()->create();
        SiiCaf::factory()->revocado()->create();

        $this->assertSame(1, SiiCaf::activos()->count());
    }

    public function test_estaAgotado_retorna_true_cuando_folio_actual_supera_hasta(): void
    {
        $caf = SiiCaf::factory()->agotado()->create();

        $this->assertTrue($caf->estaAgotado());
    }

    public function test_foliosDisponibles_calcula_correctamente(): void
    {
        $caf = SiiCaf::factory()->create([
            'folio_desde'  => 100,
            'folio_hasta'  => 109,
            'folio_actual' => 105,
        ]);

        $this->assertSame(5, $caf->foliosDisponibles()); // 109-105+1 = 5
    }

    public function test_foliosDisponibles_es_cero_si_agotado(): void
    {
        $caf = SiiCaf::factory()->create([
            'folio_desde'  => 1,
            'folio_hasta'  => 10,
            'folio_actual' => 11,
        ]);

        $this->assertSame(0, $caf->foliosDisponibles());
    }

    public function test_hidden_oculta_rsa_y_xml(): void
    {
        $caf  = SiiCaf::factory()->create();
        $json = json_decode($caf->toJson(), true);

        $this->assertArrayNotHasKey('rsa_sk_cifrada', $json);
        $this->assertArrayNotHasKey('xml_completo_cifrado', $json);
    }
}
