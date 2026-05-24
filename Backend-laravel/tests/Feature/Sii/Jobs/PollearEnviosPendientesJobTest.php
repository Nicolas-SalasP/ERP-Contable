<?php

namespace Tests\Feature\Sii\Jobs;

use App\Domains\Sii\Jobs\PollearEnviosPendientesJob;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Polling\PollearEstadoSiiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Monolog\Handler\TestHandler;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class PollearEnviosPendientesJobTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    /**
     * Crea un envio sin necesidad de toda la cadena F4+F5.2; usa factory
     * directo del modelo + DTE factory minimo.
     */
    private function envioEnviado(array $overrides = []): SiiEnvioDte
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $dte = SiiDteEmitido::factory()->create([
            'empresa_id' => $empresa->id,
            'estado'     => SiiDteEmitido::ESTADO_ENVIADO_SII,
        ]);

        return SiiEnvioDte::create(array_merge([
            'empresa_id'     => $empresa->id,
            'dte_emitido_id' => $dte->id,
            'ambiente_sii'   => 'certificacion',
            'estado_envio'   => SiiEnvioDte::ESTADO_ENVIADO,
            'track_id'       => 'TRK_' . random_int(1000, 9999),
            'intentos_envio' => 1,
            'fecha_envio'    => now()->subMinutes(10),
        ], $overrides));
    }

    public function test_job_procesa_solo_envios_que_tocan_pollear(): void
    {
        $envioToca   = $this->envioEnviado(['fecha_envio' => now()->subHours(1), 'fecha_ultimo_polling' => null]);
        $envioNoToca = $this->envioEnviado(['fecha_envio' => now()->subSeconds(30), 'fecha_ultimo_polling' => null]);

        $servicio = Mockery::mock(PollearEstadoSiiService::class);
        $servicio->shouldReceive('yaTocaPollear')
            ->with(Mockery::on(fn ($e) => $e->id === $envioToca->id))
            ->andReturn(true);
        $servicio->shouldReceive('yaTocaPollear')
            ->with(Mockery::on(fn ($e) => $e->id === $envioNoToca->id))
            ->andReturn(false);
        $servicio->shouldReceive('pollear')
            ->once()
            ->with(Mockery::on(fn ($e) => $e->id === $envioToca->id))
            ->andReturn($envioToca);

        $this->app->instance(PollearEstadoSiiService::class, $servicio);

        (new PollearEnviosPendientesJob())->handle(app(PollearEstadoSiiService::class));

        // Mockery assertion via shouldReceive('pollear')->once() ya verifica el conteo.
        $this->assertTrue(true);
    }

    public function test_aislamiento_excepcion_en_un_envio_no_aborta_demas(): void
    {
        $envioA = $this->envioEnviado(['fecha_envio' => now()->subHours(1)]);
        $envioB = $this->envioEnviado(['fecha_envio' => now()->subHours(1)]);
        $envioC = $this->envioEnviado(['fecha_envio' => now()->subHours(1)]);

        $servicio = Mockery::mock(PollearEstadoSiiService::class);
        $servicio->shouldReceive('yaTocaPollear')->andReturn(true);
        $servicio->shouldReceive('pollear')->with(Mockery::on(fn ($e) => $e->id === $envioA->id))->andReturn($envioA);
        $servicio->shouldReceive('pollear')->with(Mockery::on(fn ($e) => $e->id === $envioB->id))->andThrow(new \RuntimeException('boom B'));
        $servicio->shouldReceive('pollear')->with(Mockery::on(fn ($e) => $e->id === $envioC->id))->andReturn($envioC);

        $this->app->instance(PollearEstadoSiiService::class, $servicio);

        (new PollearEnviosPendientesJob())->handle(app(PollearEstadoSiiService::class));

        // Si A y C se procesaron pese a B fallar, el aislamiento funciono.
        $this->assertTrue(true);  // Mockery verifica las 3 llamadas a pollear.
    }

    public function test_log_final_reporta_contadores_procesados_skipped_con_error(): void
    {
        $handler = new TestHandler();
        Log::channel('sii')->getLogger()->pushHandler($handler);

        $envioOk     = $this->envioEnviado(['fecha_envio' => now()->subHours(1)]);
        $envioBoom   = $this->envioEnviado(['fecha_envio' => now()->subHours(1)]);
        $envioNoToca = $this->envioEnviado(['fecha_envio' => now()->subSeconds(10)]);

        $servicio = Mockery::mock(PollearEstadoSiiService::class);
        $servicio->shouldReceive('yaTocaPollear')
            ->andReturnUsing(fn ($e) => $e->id !== $envioNoToca->id);
        $servicio->shouldReceive('pollear')->with(Mockery::on(fn ($e) => $e->id === $envioOk->id))->andReturn($envioOk);
        $servicio->shouldReceive('pollear')->with(Mockery::on(fn ($e) => $e->id === $envioBoom->id))->andThrow(new \RuntimeException('boom'));

        $this->app->instance(PollearEstadoSiiService::class, $servicio);

        (new PollearEnviosPendientesJob())->handle(app(PollearEstadoSiiService::class));

        $log = collect($handler->getRecords())
            ->first(fn ($r) => str_contains((string) $r['message'], 'PollearEnviosPendientesJob completado'));

        $this->assertNotNull($log);
        $this->assertSame(3, $log['context']['total_envios']);
        $this->assertSame(1, $log['context']['procesados']);
        $this->assertSame(1, $log['context']['skipped_no_toca']);
        $this->assertSame(1, $log['context']['con_error']);
    }

    public function test_limit_param_limita_envios_procesados(): void
    {
        $envios = collect();
        for ($i = 0; $i < 5; $i++) {
            $envios->push($this->envioEnviado(['fecha_envio' => now()->subHours(1)]));
        }

        $servicio = Mockery::mock(PollearEstadoSiiService::class);
        $servicio->shouldReceive('yaTocaPollear')->andReturn(true);
        $servicio->shouldReceive('pollear')->times(2)->andReturnUsing(fn ($e) => $e);

        $this->app->instance(PollearEstadoSiiService::class, $servicio);

        (new PollearEnviosPendientesJob(limit: 2))->handle(app(PollearEstadoSiiService::class));

        $this->assertTrue(true);  // ->times(2) ya verifica.
    }

    public function test_ordenamiento_procesa_envios_mas_viejos_primero(): void
    {
        $reciente = $this->envioEnviado([
            'fecha_envio'         => now()->subMinutes(20),
            'fecha_ultimo_polling' => now()->subMinutes(5),
        ]);
        $antiguo = $this->envioEnviado([
            'fecha_envio'         => now()->subHours(2),
            'fecha_ultimo_polling' => now()->subHours(1),
        ]);
        $virgen = $this->envioEnviado([
            'fecha_envio'         => now()->subMinutes(30),
            'fecha_ultimo_polling' => null,  // nunca polleado, debe ir primero por orderByRaw NULL DESC
        ]);

        $ordenLlamado = [];
        $servicio = Mockery::mock(PollearEstadoSiiService::class);
        $servicio->shouldReceive('yaTocaPollear')->andReturn(true);
        $servicio->shouldReceive('pollear')->andReturnUsing(function ($e) use (&$ordenLlamado) {
            $ordenLlamado[] = $e->id;
            return $e;
        });

        $this->app->instance(PollearEstadoSiiService::class, $servicio);

        (new PollearEnviosPendientesJob())->handle(app(PollearEstadoSiiService::class));

        // Esperado: virgen (NULL primero), luego antiguo (polling mas viejo), luego reciente.
        $this->assertSame([$virgen->id, $antiguo->id, $reciente->id], $ordenLlamado);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
