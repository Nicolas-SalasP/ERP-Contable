<?php

namespace Tests\Feature\Sii\Http;

use App\Domains\Sii\Jobs\ReintentarEmisionDteJob;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteRetryControllerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Cache::flush();
    }

    private function crearDte(string $estado = SiiDteEmitido::ESTADO_RECHAZADO): array
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $dte = SiiDteEmitido::factory()->create([
            'empresa_id' => $empresa->id,
            'estado'     => $estado,
            'tipo_dte'   => 33,
        ]);

        return compact('empresa', 'usuario', 'dte');
    }

    public function test_sin_autenticacion_retorna_401(): void
    {
        $this->postJson('/api/sii/dte/1/reintentar')->assertStatus(401);
    }

    public function test_reintentar_dte_rechazado_retorna_422_estado_no_reintentable(): void
    {
        // RECHAZADO ya consumio el folio SII -- no se puede reanudar la misma
        // fila via ACCION_REANUDAR_FIRMA (EmitirDteService::emitir exige BORRADOR).
        // Recuperar este caso requiere emitir un DTE nuevo con folio nuevo.
        Bus::fake([ReintentarEmisionDteJob::class]);
        $e = $this->crearDte(SiiDteEmitido::ESTADO_RECHAZADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/dte/{$e['dte']->id}/reintentar", [
            'razon' => 'reintento post rechazo SII',
        ]);

        $r->assertStatus(422);
        $r->assertJsonPath('error.razon', 'estado_no_reintentable');
        $r->assertJsonPath('error.estado_actual', SiiDteEmitido::ESTADO_RECHAZADO);
        Bus::assertNotDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_reintentar_dte_borrador_encola_firma(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);
        $e = $this->crearDte(SiiDteEmitido::ESTADO_BORRADOR);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/dte/{$e['dte']->id}/reintentar");

        $r->assertStatus(202);
        $r->assertJsonPath('data.accion_encolada', ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA);
        Bus::assertDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_reintentar_dte_firmado_encola_envio(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);
        $e = $this->crearDte(SiiDteEmitido::ESTADO_FIRMADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/dte/{$e['dte']->id}/reintentar");

        $r->assertStatus(202);
        $r->assertJsonPath('data.accion_encolada', ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO);
        Bus::assertDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_reintentar_dte_anulado_fallo_interno_retorna_422_estado_no_reintentable(): void
    {
        // Mismo criterio que RECHAZADO: el folio quedo liberado como HUERFANO
        // (auditado, no reusable) -- no es un BORRADOR valido para reanudar.
        Bus::fake([ReintentarEmisionDteJob::class]);
        $e = $this->crearDte(SiiDteEmitido::ESTADO_ANULADO_FALLO_INTERNO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/dte/{$e['dte']->id}/reintentar");

        $r->assertStatus(422);
        $r->assertJsonPath('error.razon', 'estado_no_reintentable');
        Bus::assertNotDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_reintentar_dte_aceptado_retorna_422_estado_no_reintentable(): void
    {
        $e = $this->crearDte(SiiDteEmitido::ESTADO_ACEPTADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/dte/{$e['dte']->id}/reintentar");

        $r->assertStatus(422);
        $r->assertJsonPath('error.razon', 'estado_no_reintentable');
        $r->assertJsonPath('error.estado_actual', SiiDteEmitido::ESTADO_ACEPTADO);
    }

    public function test_reintentar_dte_aceptado_con_reparos_retorna_422(): void
    {
        $e = $this->crearDte(SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS);
        Sanctum::actingAs($e['usuario']);

        $this->postJson("/api/sii/dte/{$e['dte']->id}/reintentar")->assertStatus(422);
    }

    public function test_reintentar_dte_otra_empresa_retorna_404(): void
    {
        $a = $this->crearDte();
        $b = $this->crearDte();
        Sanctum::actingAs($a['usuario']);

        $this->postJson("/api/sii/dte/{$b['dte']->id}/reintentar")->assertStatus(404);
    }

    public function test_razon_excede_200_chars_retorna_422_validacion(): void
    {
        $e = $this->crearDte(SiiDteEmitido::ESTADO_RECHAZADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/dte/{$e['dte']->id}/reintentar", [
            'razon' => str_repeat('A', 201),
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['razon']);
    }
}
