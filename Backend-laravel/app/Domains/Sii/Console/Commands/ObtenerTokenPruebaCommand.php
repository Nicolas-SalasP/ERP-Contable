<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Exceptions\SiiAutenticacionException;
use App\Domains\Sii\Exceptions\SiiConfiguracionIncompletaException;
use App\Domains\Sii\Services\Ws\SiiTokenService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/**
 * F5.1 — Comando de validacion manual del flujo de autenticacion SII.
 *
 * IMPORTANTE: en operacion local este comando pegaria al WS REAL del SII
 * (maullin.sii.cl o palena.sii.cl). NO ejecutar contra entornos productivos
 * sin autorizacion explicita. Para testing local usar Http::fake() o entornos
 * dummy.
 */
class ObtenerTokenPruebaCommand extends Command
{
    protected $signature = 'sii:obtener-token-prueba
                            {empresa_id : ID de la empresa}
                            {--force : Forzar nueva sesion ignorando cache de sesion activa}';

    protected $description = 'Obtiene (o reusa) una sesion autenticada con el WS SII para la empresa dada. Imprime token truncado + TTL.';

    public function handle(SiiTokenService $service): int
    {
        $empresaId = (int) $this->argument('empresa_id');

        try {
            /** @var Empresa $empresa */
            $empresa = Empresa::findOrFail($empresaId);
        } catch (ModelNotFoundException) {
            $this->error("Empresa con ID {$empresaId} no encontrada.");
            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        try {
            $sesion = $force
                ? $service->generarSesionNueva($empresa)
                : $service->obtenerSesionActiva($empresa);
        } catch (SiiConfiguracionIncompletaException $e) {
            $this->error('Configuracion SII incompleta: ' . $e->getMessage());
            $this->line('Motivo: ' . $e->motivo);
            return self::FAILURE;
        } catch (CertificadoInvalidoException $e) {
            $this->error('Certificado: ' . $e->getMessage());
            return self::FAILURE;
        } catch (SiiAutenticacionException $e) {
            $this->error('Fallo autenticacion WS SII: ' . $e->getMessage());
            $this->line('Motivo: ' . $e->motivo);
            if ($e->httpStatus !== null) {
                $this->line('HTTP status: ' . $e->httpStatus);
            }
            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Error inesperado: ' . $e::class . ' — ' . $e->getMessage());
            return self::FAILURE;
        }

        $fueReuso = ! $force && $sesion->intentos_uso > 1;
        $minutosRestantes = (int) max(0, now()->diffInMinutes($sesion->fecha_expiracion, false));

        $this->info('Sesion SII obtenida exitosamente.');
        $this->line('  Empresa           : ' . $empresa->razon_social . ' (' . $empresa->rut . ')');
        $this->line('  Ambiente          : ' . $sesion->ambiente);
        $this->line('  Token (truncado)  : ' . substr($sesion->token, 0, 16) . '...');
        $this->line('  Fecha obtencion   : ' . $sesion->fecha_obtencion->toIso8601String());
        $this->line('  Fecha expiracion  : ' . $sesion->fecha_expiracion->toIso8601String());
        $this->line('  Minutos restantes : ' . $minutosRestantes);
        $this->line('  Origen            : ' . ($fueReuso ? 'REUSO (cache activo)' : 'NUEVA SESION'));
        $this->line('  Intentos de uso   : ' . $sesion->intentos_uso);

        return self::SUCCESS;
    }
}
