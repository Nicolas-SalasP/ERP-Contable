<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Models\SiiCaf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCafControllerIndexTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_index_devuelve_lista_de_cafs_de_la_empresa(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->count(3)->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/caf')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'tipo_dte', 'folio_desde', 'folio_hasta', 'estado']]])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filtra_por_tipo_dte_cuando_se_provee(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->count(2)->create(['empresa_id' => $empresa->id]);
        SiiCaf::factory()->tipo39()->count(1)->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/sii/caf?tipo_dte=33')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $caf) {
            $this->assertSame(33, $caf['tipo_dte']);
        }
    }

    public function test_index_oculta_material_criptografico(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/sii/caf')->assertStatus(200);
        $caf      = $response->json('data.0');

        $this->assertArrayNotHasKey('rsa_sk_cifrada', $caf);
        $this->assertArrayNotHasKey('xml_completo_cifrado', $caf);
    }

    public function test_index_aislamiento_multitenant_no_lista_cafs_de_otra_empresa(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB]            = $this->crearEmpresaConAdmin();

        SiiCaf::factory()->create(['empresa_id' => $empresaA->id]);
        SiiCaf::factory()->create(['empresa_id' => $empresaB->id]);
        SiiCaf::factory()->create(['empresa_id' => $empresaB->id]);

        Sanctum::actingAs($usuarioA);

        $this->getJson('/api/sii/caf')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_sin_cafs_retorna_array_vacio_no_404(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/caf')
            ->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    public function test_index_requiere_autenticacion_401(): void
    {
        $this->getJson('/api/sii/caf')->assertStatus(401);
    }
}
