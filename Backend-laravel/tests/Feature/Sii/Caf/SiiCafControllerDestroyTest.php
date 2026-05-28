<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCafControllerDestroyTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_destroy_revoca_caf_con_motivo_valido_retorna_204(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $this->deleteJson("/api/sii/caf/{$caf->id}", ['motivo' => 'CAF de prueba descontinuado'])
            ->assertStatus(204);
    }

    public function test_destroy_persiste_estado_revocado(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $this->deleteJson("/api/sii/caf/{$caf->id}", ['motivo' => 'CAF descontinuado'])
            ->assertStatus(204);

        $this->assertSame('revocado', $caf->fresh()->estado);
    }

    public function test_destroy_libera_folios_RESERVADOS_como_HUERFANO(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 1]);
        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 2]);

        Sanctum::actingAs($usuario);

        $this->deleteJson("/api/sii/caf/{$caf->id}", ['motivo' => 'Cambio de razon social'])
            ->assertStatus(204);

        $this->assertSame(0, SiiCafFolioUso::where('caf_id', $caf->id)->where('estado', 'RESERVADO')->count());
        $this->assertSame(2, SiiCafFolioUso::where('caf_id', $caf->id)->where('estado', 'HUERFANO')->count());

        $primer = SiiCafFolioUso::where('caf_id', $caf->id)->where('folio', 1)->first();
        $this->assertStringContainsString('Cambio de razon social', $primer->razon_liberacion);
    }

    public function test_destroy_no_toca_folios_USADOS(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        SiiCafFolioUso::factory()->usado()->create(['caf_id' => $caf->id, 'folio' => 1]);
        SiiCafFolioUso::factory()->reservado()->create(['caf_id' => $caf->id, 'folio' => 2]);

        Sanctum::actingAs($usuario);

        $this->deleteJson("/api/sii/caf/{$caf->id}", ['motivo' => 'cualquier motivo valido'])
            ->assertStatus(204);

        // USADO permanece intacto.
        $usadoSigueIgual = SiiCafFolioUso::where('caf_id', $caf->id)->where('folio', 1)->first();
        $this->assertSame('USADO', $usadoSigueIgual->estado);
        $this->assertNull($usadoSigueIgual->razon_liberacion);
    }

    public function test_destroy_falla_409_si_caf_ya_estaba_revocado(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->revocado()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $this->deleteJson("/api/sii/caf/{$caf->id}", ['motivo' => 'doble revocacion'])
            ->assertStatus(409);
    }

    public function test_destroy_rechaza_sin_motivo_con_422(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $this->deleteJson("/api/sii/caf/{$caf->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_destroy_rechaza_motivo_demasiado_corto_con_422(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $this->deleteJson("/api/sii/caf/{$caf->id}", ['motivo' => 'no'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_destroy_retorna_404_si_caf_es_de_otra_empresa_idor(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB]            = $this->crearEmpresaConAdmin();

        $cafDeB = SiiCaf::factory()->create(['empresa_id' => $empresaB->id]);

        Sanctum::actingAs($usuarioA);

        $this->deleteJson("/api/sii/caf/{$cafDeB->id}", ['motivo' => 'intento de borrar ajeno'])
            ->assertStatus(404);

        $this->assertSame('activo', $cafDeB->fresh()->estado);
    }
}
