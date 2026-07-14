<?php

namespace Tests\Feature\Alertas;

use App\Domains\Alertas\Jobs\MotorAlertasJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MonitorearAlertasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_existe_en_artisan_list(): void
    {
        $todos = array_keys(Artisan::all());
        $this->assertContains('alertas:monitorear', $todos);
    }

    public function test_invocacion_manual_dispatcha_job_y_retorna_success(): void
    {
        Bus::fake([MotorAlertasJob::class]);

        $exitCode = Artisan::call('alertas:monitorear');

        $this->assertSame(0, $exitCode);
        Bus::assertDispatchedSync(MotorAlertasJob::class);
    }
}
