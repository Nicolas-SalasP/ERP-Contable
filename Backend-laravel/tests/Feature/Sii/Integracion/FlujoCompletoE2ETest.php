<?php

namespace Tests\Feature\Sii\Integracion;

use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoEvento;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Models\SiiTokenSesion;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use App\Domains\Sii\Services\Polling\PollearEstadoSiiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\OrquestaFlujoCompletoEnTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * F5.4 — Test E2E integral del flujo SII completo: BORRADOR → FIRMADO →
 * ENVIADO_SII → estado terminal. Reusa Http::fake() para los 4 endpoints
 * SII en una sola pasada y verifica que la maquina de estados y los
 * eventos de audit terminan coherentes.
 */
class FlujoCompletoE2ETest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use OrquestaFlujoCompletoEnTests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries'         => 2,
            'sii.upload.retry_delay_ms'  => 1,
        ]);
        Storage::fake(config('sii.storage.disk', 'local'));
    }

    /**
     * Ejecuta el pipeline F4.4 + F5.2 + F5.3 sobre el DTE dado.
     */
    private function ejecutarFlujoCompleto(int $dteId): SiiEnvioDte
    {
        app(EmitirDteService::class)->emitir($dteId);
        $envio = app(EnvioSiiService::class)->enviar($dteId);
        return app(PollearEstadoSiiService::class)->pollear($envio->fresh());
    }

    public function test_escenario_aceptado_DTE_termina_en_ACEPTADO_con_3_eventos(): void
    {
        $ctx = $this->setupEmpresaConFlujoCompleto(['rut' => '76111111-1']);
        $this->fakeRespuestasSiiFlujoCompleto('aceptado');

        $envio = $this->ejecutarFlujoCompleto($ctx['dte']->id);

        $this->assertSame(SiiEnvioDte::ESTADO_ACEPTADO, $envio->estado_envio);
        $dteFinal = $ctx['dte']->fresh();
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO, $dteFinal->estado);

        $eventos = SiiDteEmitidoEvento::where('dte_emitido_id', $dteFinal->id)->orderBy('id')->get();
        $this->assertCount(3, $eventos);
        $this->assertSame(SiiDteEmitido::ESTADO_BORRADOR,    $eventos[0]->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO,     $eventos[0]->estado_nuevo);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO,     $eventos[1]->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $eventos[1]->estado_nuevo);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $eventos[2]->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO,    $eventos[2]->estado_nuevo);
    }

    public function test_escenario_con_reparos_DTE_termina_en_ACEPTADO_CON_REPAROS(): void
    {
        $ctx = $this->setupEmpresaConFlujoCompleto(['rut' => '76222222-2']);
        $this->fakeRespuestasSiiFlujoCompleto('con-reparos');

        $envio = $this->ejecutarFlujoCompleto($ctx['dte']->id);

        $this->assertSame(SiiEnvioDte::ESTADO_ACEPTADO_REPAROS, $envio->estado_envio);
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS, $ctx['dte']->fresh()->estado);
    }

    public function test_escenario_rechazado_DTE_termina_en_RECHAZADO_con_glosa_SII(): void
    {
        $ctx = $this->setupEmpresaConFlujoCompleto(['rut' => '76333333-3']);
        $this->fakeRespuestasSiiFlujoCompleto('rechazado');

        $envio = $this->ejecutarFlujoCompleto($ctx['dte']->id);

        $this->assertSame(SiiEnvioDte::ESTADO_RECHAZADO, $envio->estado_envio);
        $dteFinal = $ctx['dte']->fresh();
        $this->assertSame(SiiDteEmitido::ESTADO_RECHAZADO, $dteFinal->estado);
        $this->assertSame('Envio Rechazado', $dteFinal->glosa_sii);
        $this->assertNotNull($dteFinal->fecha_rechazo_sii);
    }

    public function test_escenario_procesando_DTE_permanece_en_ENVIADO_SII_para_polling_futuro(): void
    {
        $ctx = $this->setupEmpresaConFlujoCompleto(['rut' => '76444444-4']);
        $this->fakeRespuestasSiiFlujoCompleto('procesando');

        $envio = $this->ejecutarFlujoCompleto($ctx['dte']->id);

        // EPR no es estado terminal: envio sigue en ENVIADO, DTE en ENVIADO_SII.
        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $envio->estado_envio);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $ctx['dte']->fresh()->estado);
        $this->assertSame('EPR', $envio->estado_sii_ultimo);
        $this->assertNull($envio->fecha_resolucion);

        // El DTE solo tiene 2 eventos (no la transicion terminal).
        $this->assertCount(2, SiiDteEmitidoEvento::where('dte_emitido_id', $ctx['dte']->id)->get());
    }

    public function test_aislamiento_multitenant_empresa_A_y_B_paralelo_no_interfieren(): void
    {
        $a = $this->setupEmpresaConFlujoCompleto(['rut' => '76111111-1']);
        $b = $this->setupEmpresaConFlujoCompleto(['rut' => '77222222-2']);

        // Un solo fake (track_id default) sirve para ambas. Lo que validamos
        // aqui es aislamiento de DATOS (empresa_id, caf, sesion, eventos),
        // no diferenciacion de track_id (el SII real asigna unico por envio
        // pero en fake usamos uno fijo).
        $this->fakeRespuestasSiiFlujoCompleto('aceptado');

        $envioA = $this->ejecutarFlujoCompleto($a['dte']->id);
        $envioB = $this->ejecutarFlujoCompleto($b['dte']->id);

        // Estado final correcto en ambas
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO, $a['dte']->fresh()->estado);
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO, $b['dte']->fresh()->estado);
        $this->assertNotNull($envioA->fresh()->track_id);
        $this->assertNotNull($envioB->fresh()->track_id);

        // FOLIOS CAF segregados: cada DTE consumio un folio de SU CAF.
        $foliosUsadosA = SiiCafFolioUso::where('caf_id', $a['caf']->id)->where('estado', 'USADO')->get();
        $foliosUsadosB = SiiCafFolioUso::where('caf_id', $b['caf']->id)->where('estado', 'USADO')->get();
        $this->assertCount(1, $foliosUsadosA);
        $this->assertCount(1, $foliosUsadosB);
        $this->assertSame($a['dte']->id, $foliosUsadosA->first()->dte_emitido_id);
        $this->assertSame($b['dte']->id, $foliosUsadosB->first()->dte_emitido_id);
        $this->assertSame($a['caf']->id, $a['dte']->fresh()->caf_id);
        $this->assertSame($b['caf']->id, $b['dte']->fresh()->caf_id);

        // SESIONES segregadas por empresa
        $sesionesA = SiiTokenSesion::where('empresa_id', $a['empresa']->id)->get();
        $sesionesB = SiiTokenSesion::where('empresa_id', $b['empresa']->id)->get();
        $this->assertGreaterThanOrEqual(1, $sesionesA->count());
        $this->assertGreaterThanOrEqual(1, $sesionesB->count());
        $this->assertEmpty($sesionesA->pluck('id')->intersect($sesionesB->pluck('id')));

        // ENVIOS segregados
        $enviosA = SiiEnvioDte::where('empresa_id', $a['empresa']->id)->get();
        $enviosB = SiiEnvioDte::where('empresa_id', $b['empresa']->id)->get();
        $this->assertCount(1, $enviosA);
        $this->assertCount(1, $enviosB);
        $this->assertNotSame($enviosA->first()->id, $enviosB->first()->id);

        // EVENTOS del DTE segregados (cada DTE tiene SUS 3 eventos)
        $eventosA = SiiDteEmitidoEvento::where('dte_emitido_id', $a['dte']->id)->get();
        $eventosB = SiiDteEmitidoEvento::where('dte_emitido_id', $b['dte']->id)->get();
        $this->assertCount(3, $eventosA);
        $this->assertCount(3, $eventosB);
        $this->assertEmpty($eventosA->pluck('id')->intersect($eventosB->pluck('id')));
    }

    public function test_aislamiento_de_ambiente_cert_y_prod_paralelo_no_interfieren(): void
    {
        $c = $this->setupEmpresaConFlujoCompleto([
            'rut'          => '76555555-5',
            'ambiente_sii' => 'certificacion',
        ]);
        $p = $this->setupEmpresaConFlujoCompleto([
            'rut'                   => '77666666-6',
            'ambiente_sii'          => 'produccion',
            'resolucion_sii_numero' => 1234,
            'resolucion_sii_fecha'  => '2023-12-01',
        ]);

        $this->fakeRespuestasSiiFlujoCompleto('aceptado');
        $envioC = $this->ejecutarFlujoCompleto($c['dte']->id);
        $envioP = $this->ejecutarFlujoCompleto($p['dte']->id);

        $this->assertSame('certificacion', $envioC->fresh()->ambiente_sii);
        $this->assertSame('produccion',    $envioP->fresh()->ambiente_sii);

        // Sesiones segregadas por ambiente (cada empresa tiene su ambiente unico).
        $this->assertSame(
            'certificacion',
            SiiTokenSesion::where('empresa_id', $c['empresa']->id)->first()->ambiente
        );
        $this->assertSame(
            'produccion',
            SiiTokenSesion::where('empresa_id', $p['empresa']->id)->first()->ambiente
        );
    }
}
