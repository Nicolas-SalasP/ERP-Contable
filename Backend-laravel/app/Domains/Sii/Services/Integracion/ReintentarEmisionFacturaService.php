<?php

namespace App\Domains\Sii\Services\Integracion;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Sii\Exceptions\ReintentoNoAplicableException;
use App\Domains\Sii\Jobs\ReintentarEmisionDteJob;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiEnvioDte;

/** Decide la accion de reintento segun el estado actual del DTE/envio y la encola asincronamente; no ejecuta nada sincronamente. */
class ReintentarEmisionFacturaService
{
    /** Estados del DTE donde el flujo ya no admite reintento. */
    private const ESTADOS_TERMINALES_DTE = [
        SiiDteEmitido::ESTADO_ACEPTADO,
        SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS,
        SiiDteEmitido::ESTADO_RECHAZADO,
        SiiDteEmitido::ESTADO_REEMITIDO,
        SiiDteEmitido::ESTADO_ANULADO_CON_NC,
        SiiDteEmitido::ESTADO_ANULADO_FALLO_INTERNO,
    ];

    /** Estados del ultimo envio que el operador puede reintentar. */
    private const ESTADOS_ENVIO_REINTENTABLES = [
        SiiEnvioDte::ESTADO_ERROR_TRANSPORTE,
        SiiEnvioDte::ESTADO_ERROR_TIMEOUT,
        SiiEnvioDte::ESTADO_ERROR_PERMANENTE,
    ];

    public function __construct(
        private readonly EmitirDteDesdeFacturaService $emitirDesdeFactura
    ) {
    }

    /**
     * @return string Una de: 'redispatch_evento' (factura sin DTE), 'reanudar_firma', 'reanudar_envio'.
     *
     * @throws ReintentoNoAplicableException
     * @throws \App\Domains\Sii\Exceptions\FacturaNoEmisibleException propagada desde EmitirDteDesdeFacturaService cuando es redispatch.
     */
    public function reintentar(
        Factura $factura,
        ?string $razon = null,
        ?int $usuarioId = null
    ): string {
        $factura = $factura->fresh(['dteEmitido.envios']);

        // Sin DTE: pipeline completo via evento (encola listener async).
        if ($factura->sii_dte_emitido_id === null) {
            $this->emitirDesdeFactura->dispatch($factura, [], 'reintento', $usuarioId);
            return 'redispatch_evento';
        }

        $dte       = $factura->dteEmitido;
        $estadoDte = $dte->estado;

        if (in_array($estadoDte, self::ESTADOS_TERMINALES_DTE, true)) {
            throw ReintentoNoAplicableException::estadoTerminal($factura->id, $estadoDte);
        }

        if ($estadoDte === SiiDteEmitido::ESTADO_BORRADOR) {
            ReintentarEmisionDteJob::dispatch(
                $dte->id,
                ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA,
                $razon,
                $usuarioId
            );
            return 'reanudar_firma';
        }

        // DTE ya firmado, nunca enviado: reanudar desde envio.
        if ($estadoDte === SiiDteEmitido::ESTADO_FIRMADO) {
            ReintentarEmisionDteJob::dispatch(
                $dte->id,
                ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO,
                $razon,
                $usuarioId
            );
            return 'reanudar_envio';
        }

        if ($estadoDte === SiiDteEmitido::ESTADO_ENVIADO_SII) {
            // envios() viene ordenado ASC por created_at; el ultimo es last().
            $ultimoEnvio = $dte->envios->last();

            if ($ultimoEnvio === null) {
                // Estado inconsistente: encolar y delegar al servicio interno la decision de aceptar o rechazar.
                ReintentarEmisionDteJob::dispatch(
                    $dte->id,
                    ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO,
                    $razon,
                    $usuarioId
                );
                return 'reanudar_envio';
            }

            if (in_array($ultimoEnvio->estado_envio, self::ESTADOS_ENVIO_REINTENTABLES, true)) {
                ReintentarEmisionDteJob::dispatch(
                    $dte->id,
                    ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO,
                    $razon,
                    $usuarioId
                );
                return 'reanudar_envio';
            }

            throw ReintentoNoAplicableException::yaEnProceso(
                $factura->id,
                $ultimoEnvio->estado_envio
            );
        }

        throw ReintentoNoAplicableException::dteNoReintentable($factura->id, $estadoDte);
    }
}
