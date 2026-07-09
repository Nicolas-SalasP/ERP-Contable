<?php

namespace App\Domains\Alertas\Console\Commands;

use App\Domains\Alertas\Jobs\MotorAlertasJob;
use Illuminate\Console\Command;

class MonitorearAlertasCommand extends Command
{
    protected $signature = 'alertas:monitorear';

    protected $description = 'Ejecuta todos los evaluadores de alertas registrados y envia las notificaciones pendientes.';

    public function handle(): int
    {
        $this->info('Iniciando motor de alertas...');

        dispatch_sync(new MotorAlertasJob);

        $this->info('Motor de alertas finalizado. Ver logs para el resumen.');

        return self::SUCCESS;
    }
}
