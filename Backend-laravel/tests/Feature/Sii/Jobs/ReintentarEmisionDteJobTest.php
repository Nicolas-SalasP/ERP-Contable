<?php

namespace Tests\Feature\Sii\Jobs;

use App\Domains\Sii\Jobs\ReintentarEmisionDteJob;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ReintentarEmisionDteJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_reanudar_firma_invoca_EmitirDteService(): void
    {
        $emitirMock = Mockery::mock(EmitirDteService::class);
        $emitirMock->shouldReceive('emitir')->once()->with(42);

        $envioMock = Mockery::mock(EnvioSiiService::class);
        $envioMock->shouldNotReceive('enviar');

        $job = new ReintentarEmisionDteJob(
            dteEmitidoId: 42,
            accion: ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA,
            razon: 'test'
        );
        $job->handle($emitirMock, $envioMock);
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    public function test_job_reanudar_envio_invoca_EnvioSiiService(): void
    {
        $emitirMock = Mockery::mock(EmitirDteService::class);
        $emitirMock->shouldNotReceive('emitir');

        $envioMock = Mockery::mock(EnvioSiiService::class);
        $envioMock->shouldReceive('enviar')->once()->with(77);

        $job = new ReintentarEmisionDteJob(
            dteEmitidoId: 77,
            accion: ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO
        );
        $job->handle($emitirMock, $envioMock);
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    public function test_job_loguea_info_inicio_y_fin_en_canal_sii(): void
    {
        Log::shouldReceive('channel')->with('sii')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->twice();

        $emitirMock = Mockery::mock(EmitirDteService::class);
        $emitirMock->shouldReceive('emitir')->once();
        $envioMock = Mockery::mock(EnvioSiiService::class);

        $job = new ReintentarEmisionDteJob(
            dteEmitidoId: 1,
            accion: ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA,
            razon: 'r',
            usuarioId: 10
        );
        $job->handle($emitirMock, $envioMock);
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    public function test_job_falla_loguea_error_y_re_lanza(): void
    {
        Log::shouldReceive('channel')->with('sii')->andReturnSelf();
        Log::shouldReceive('info')->once();   // inicio
        Log::shouldReceive('error')->once();  // fallo

        $boom = new \RuntimeException('boom');
        $emitirMock = Mockery::mock(EmitirDteService::class);
        $emitirMock->shouldReceive('emitir')->andThrow($boom);
        $envioMock = Mockery::mock(EnvioSiiService::class);

        $job = new ReintentarEmisionDteJob(
            dteEmitidoId: 1,
            accion: ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA
        );

        $this->expectException(\RuntimeException::class);
        $job->handle($emitirMock, $envioMock);
    }

    public function test_job_failed_hook_loguea_critical(): void
    {
        Log::shouldReceive('channel')->with('sii')->andReturnSelf();
        Log::shouldReceive('critical')->once();

        $job = new ReintentarEmisionDteJob(
            dteEmitidoId: 5,
            accion: ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO,
            razon: 'razon-x',
            usuarioId: 7
        );
        $job->failed(new \RuntimeException('agotado'));
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    public function test_job_persiste_razon_y_usuario_en_propiedades(): void
    {
        $job = new ReintentarEmisionDteJob(
            dteEmitidoId: 99,
            accion: ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA,
            razon: 'red caida',
            usuarioId: 33
        );
        $this->assertSame(99, $job->dteEmitidoId);
        $this->assertSame('reanudar_firma', $job->accion);
        $this->assertSame('red caida', $job->razon);
        $this->assertSame(33, $job->usuarioId);
        $this->assertSame('sii', $job->queue);  // set via onQueue() in ctor
        $this->assertSame(2, $job->tries);
        $this->assertSame([60, 300], $job->backoff());
    }
}
