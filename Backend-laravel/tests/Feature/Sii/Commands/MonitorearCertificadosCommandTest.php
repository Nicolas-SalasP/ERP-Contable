<?php

namespace Tests\Feature\Sii\Commands;

use App\Domains\Sii\Jobs\MonitorearVencimientoCertificadosJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MonitorearCertificadosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_existe_en_artisan_list(): void
    {
        $todos = array_keys(Artisan::all());
        $this->assertContains('sii:monitorear-certificados', $todos);
    }

    public function test_invocacion_manual_dispatcha_job_y_retorna_success(): void
    {
        Bus::fake([MonitorearVencimientoCertificadosJob::class]);

        $exitCode = Artisan::call('sii:monitorear-certificados');

        $this->assertSame(0, $exitCode);
        Bus::assertDispatchedSync(MonitorearVencimientoCertificadosJob::class);
    }
}
