<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Sii\Console\Commands\Concerns\BloqueaEnProduccion;
use App\Domains\Sii\Exceptions\EnvioSiiException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/** F5.2 — comando de validacion manual del flujo de envio del DTE al SII; IMPORTANTE: pega al WS REAL del SII, no ejecutar contra produccion sin autorizacion explicita (usar Http::fake() o entornos dummy para testing). */
class EnviarDtePruebaCommand extends Command
{
    use BloqueaEnProduccion;

    protected $signature = 'sii:enviar-dte-prueba
                            {dte_id : ID del SiiDteEmitido a enviar (debe estar FIRMADO)}';

    protected $description = 'Sube el XML del EnvioDTE firmado al WS DTEUpload del SII y registra el track_id.';

    public function handle(EnvioSiiService $service): int
    {
        if ($this->abortarSiProduccion()) {
            return self::FAILURE;
        }

        $dteId = (int) $this->argument('dte_id');

        try {
            SiiDteEmitido::findOrFail($dteId);
        } catch (ModelNotFoundException) {
            $this->error("DTE {$dteId} no encontrado.");
            return self::FAILURE;
        }

        try {
            $envio = $service->enviar($dteId);
        } catch (EnvioSiiException $e) {
            $this->error('Envio SII: ' . $e->getMessage());
            $this->line('Motivo: ' . $e->motivo);
            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Error inesperado: ' . $e::class . ' — ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Envio procesado.');
        $this->line('  envio_id        : ' . $envio->id);
        $this->line('  dte_emitido_id  : ' . $envio->dte_emitido_id);
        $this->line('  estado_envio    : ' . $envio->estado_envio);
        $this->line('  track_id        : ' . ($envio->track_id ?? '[N/A]'));
        $this->line('  http_status     : ' . ($envio->http_status_ultimo_envio ?? '[N/A]'));
        $this->line('  intentos_envio  : ' . $envio->intentos_envio);
        $this->line('  glosa_sii       : ' . ($envio->glosa_sii ?? '[N/A]'));
        $this->line('  fecha_envio     : ' . ($envio->fecha_envio?->toIso8601String() ?? '[N/A]'));

        // Exit code reflejando el resultado funcional.
        return $envio->track_id !== null ? self::SUCCESS : self::FAILURE;
    }
}
