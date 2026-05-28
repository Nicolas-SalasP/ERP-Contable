<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Models\SiiCaf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCafControllerSaldosTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_saldos_devuelve_estructura_indexada_por_tipo_dte(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id, 'folio_desde' => 1, 'folio_hasta' => 50, 'folio_actual' => 1]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/caf/saldos')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '33' => ['tipo_dte', 'nombre', 'total_autorizado', 'disponibles', 'usados', 'huerfanos', 'cafs_activos', 'cafs_agotados'],
                ],
            ])
            ->assertJsonPath('data.33.total_autorizado', 50)
            ->assertJsonPath('data.33.disponibles', 50);
    }

    public function test_saldos_incluye_nombre_humano_del_tipo(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);
        SiiCaf::factory()->tipo39()->create(['empresa_id' => $empresa->id]);
        SiiCaf::factory()->tipo52()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $r = $this->getJson('/api/sii/caf/saldos')->assertStatus(200);

        $this->assertSame('Factura Electronica', $r->json('data.33.nombre'));
        $this->assertSame('Boleta Electronica', $r->json('data.39.nombre'));
        $this->assertSame('Guia de Despacho', $r->json('data.52.nombre'));
    }

    public function test_saldos_agrega_correctamente_multiples_cafs_del_mismo_tipo(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id, 'folio_desde' => 1,   'folio_hasta' => 50,  'folio_actual' => 1]);
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id, 'folio_desde' => 100, 'folio_hasta' => 200, 'folio_actual' => 100]);

        Sanctum::actingAs($usuario);

        $r = $this->getJson('/api/sii/caf/saldos')->assertStatus(200);

        $this->assertSame(50 + 101, $r->json('data.33.total_autorizado'));
        $this->assertSame(50 + 101, $r->json('data.33.disponibles'));
        $this->assertSame(2, $r->json('data.33.cafs_activos'));
    }

    public function test_saldos_no_incluye_tipos_sin_cafs(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $r = $this->getJson('/api/sii/caf/saldos')->assertStatus(200);

        $this->assertArrayHasKey('33', $r->json('data'));
        $this->assertArrayNotHasKey('39', $r->json('data'));
        $this->assertArrayNotHasKey('52', $r->json('data'));
    }

    public function test_saldos_aislamiento_multitenant(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB]            = $this->crearEmpresaConAdmin();

        SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresaA->id]);
        SiiCaf::factory()->tipo39()->create(['empresa_id' => $empresaB->id]); // tipo distinto, otra empresa

        Sanctum::actingAs($usuarioA);

        $r = $this->getJson('/api/sii/caf/saldos')->assertStatus(200);

        $this->assertArrayHasKey('33', $r->json('data'));
        $this->assertArrayNotHasKey('39', $r->json('data'));
    }

    public function test_saldos_requiere_autenticacion_401(): void
    {
        $this->getJson('/api/sii/caf/saldos')->assertStatus(401);
    }
}
