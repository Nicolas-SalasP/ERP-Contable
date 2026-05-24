<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\SinFoliosDisponiblesException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Nota: lockForUpdate aplica solo en MySQL/PostgreSQL. SQLite (motor de
 * tests) lo ignora silenciosamente, por lo que estos tests verifican la
 * logica funcional pero NO la atomicidad real bajo carga concurrente.
 */
class CafServiceReservarFolioTest extends TestCase
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

    private function empresa(): Empresa
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        return $empresa;
    }

    public function test_reservar_primer_folio_retorna_FolioUso_con_folio_desde(): void
    {
        $empresa = $this->empresa();
        $caf     = SiiCaf::factory()->tipo33()->rangoChico()->create(['empresa_id' => $empresa->id]);

        $uso = $this->service->reservarSiguienteFolio($empresa->id, 33);

        $this->assertSame($caf->folio_desde, $uso->folio);
        $this->assertSame($caf->id, $uso->caf_id);
        $this->assertSame(SiiCafFolioUso::ESTADO_RESERVADO, $uso->estado);
    }

    public function test_reservar_incrementa_folio_actual(): void
    {
        $empresa = $this->empresa();
        $caf     = SiiCaf::factory()->tipo33()->rangoChico()->create(['empresa_id' => $empresa->id]);

        $this->service->reservarSiguienteFolio($empresa->id, 33);
        $this->service->reservarSiguienteFolio($empresa->id, 33);
        $this->service->reservarSiguienteFolio($empresa->id, 33);

        $this->assertSame(4, $caf->fresh()->folio_actual);
        $this->assertSame(3, SiiCafFolioUso::count());
    }

    public function test_reservas_consecutivas_no_tienen_saltos(): void
    {
        $empresa = $this->empresa();
        SiiCaf::factory()->tipo33()->create([
            'empresa_id'  => $empresa->id,
            'folio_desde' => 100,
            'folio_hasta' => 105,
            'folio_actual' => 100,
        ]);

        $folios = [];
        for ($i = 0; $i < 5; $i++) {
            $folios[] = $this->service->reservarSiguienteFolio($empresa->id, 33)->folio;
        }

        $this->assertSame([100, 101, 102, 103, 104], $folios);
    }

    public function test_reservar_con_usuario_id_lo_persiste(): void
    {
        $empresa = $this->empresa();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);
        [, $usuario] = $this->crearEmpresaConAdmin();

        $uso = $this->service->reservarSiguienteFolio($empresa->id, 33, $usuario->id);

        $this->assertSame($usuario->id, $uso->usuario_reservo_id);
    }

    public function test_reservar_cuando_caf_se_agota_cambia_estado_a_agotado(): void
    {
        $empresa = $this->empresa();
        $caf     = SiiCaf::factory()->tipo33()->create([
            'empresa_id'  => $empresa->id,
            'folio_desde' => 1,
            'folio_hasta' => 2,
            'folio_actual' => 1,
        ]);

        $this->service->reservarSiguienteFolio($empresa->id, 33); // 1
        $this->service->reservarSiguienteFolio($empresa->id, 33); // 2

        $caf->refresh();
        $this->assertSame(3, $caf->folio_actual);
        $this->assertSame(SiiCaf::ESTADO_AGOTADO, $caf->estado);
    }

    public function test_reservar_cuando_caf_agotado_pasa_al_siguiente_caf_activo(): void
    {
        $empresa = $this->empresa();

        $cafA = SiiCaf::factory()->tipo33()->create([
            'empresa_id'  => $empresa->id,
            'folio_desde' => 1,
            'folio_hasta' => 1,
            'folio_actual' => 1,
        ]);
        $cafB = SiiCaf::factory()->tipo33()->create([
            'empresa_id'  => $empresa->id,
            'folio_desde' => 100,
            'folio_hasta' => 150,
            'folio_actual' => 100,
        ]);

        $uso1 = $this->service->reservarSiguienteFolio($empresa->id, 33);
        $uso2 = $this->service->reservarSiguienteFolio($empresa->id, 33);

        $this->assertSame($cafA->id, $uso1->caf_id);
        $this->assertSame(1, $uso1->folio);

        // El segundo va al cafB porque cafA quedo agotado.
        $this->assertSame($cafB->id, $uso2->caf_id);
        $this->assertSame(100, $uso2->folio);

        $this->assertSame(SiiCaf::ESTADO_AGOTADO, $cafA->fresh()->estado);
        $this->assertSame(SiiCaf::ESTADO_ACTIVO, $cafB->fresh()->estado);
    }

    public function test_reservar_sin_cafs_disponibles_lanza_excepcion(): void
    {
        $empresa = $this->empresa();
        // No hay CAFs cargados.

        $this->expectException(SinFoliosDisponiblesException::class);
        $this->service->reservarSiguienteFolio($empresa->id, 33);
    }

    public function test_tipos_diferentes_no_se_mezclan_en_reservas(): void
    {
        $empresa = $this->empresa();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id, 'folio_desde' => 1, 'folio_hasta' => 10, 'folio_actual' => 1]);

        // Solo hay CAFs tipo 33; pedir tipo 39 debe fallar.
        $this->expectException(SinFoliosDisponiblesException::class);
        $this->service->reservarSiguienteFolio($empresa->id, 39);
    }

    public function test_empresas_diferentes_no_se_mezclan_en_reservas(): void
    {
        $empresaA = $this->empresa();
        $empresaB = $this->empresa();

        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresaA->id, 'folio_desde' => 1, 'folio_hasta' => 10, 'folio_actual' => 1]);

        // Empresa B no tiene CAF; debe fallar pidiendo tipo 33.
        $this->expectException(SinFoliosDisponiblesException::class);
        $this->service->reservarSiguienteFolio($empresaB->id, 33);
    }

    public function test_cafs_revocados_no_se_consideran(): void
    {
        $empresa = $this->empresa();
        SiiCaf::factory()->tipo33()->revocado()->create(['empresa_id' => $empresa->id]);

        $this->expectException(SinFoliosDisponiblesException::class);
        $this->service->reservarSiguienteFolio($empresa->id, 33);
    }
}
