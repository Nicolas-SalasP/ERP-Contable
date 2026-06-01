<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Sii\Jobs\MonitorearVencimientoCertificadosJob;
use Illuminate\Console\Command;

class MonitorearCertificadosCommand extends Command
{
    protected $signature = 'sii:monitorear-certificados';

    protected $description = 'Ejecuta el monitoreo de vencimiento de certificados SII y envia alertas.';

    public function handle(): int
    {
        $this->info('Iniciando monitoreo de vencimiento de certificados SII...');

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        $this->info('Monitoreo finalizado. Ver canal de logs "sii" para el resumen.');

        return self::SUCCESS;
    }
}
