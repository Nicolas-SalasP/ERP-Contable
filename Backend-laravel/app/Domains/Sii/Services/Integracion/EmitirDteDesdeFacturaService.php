<?php

namespace App\Domains\Sii\Services\Integracion;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Exceptions\FacturaNoEmisibleException;

/**
 * F6.2 — Puerto de entrada formal del modulo SII para emision desde una
 * Factura del Comercial.
 *
 * Unico metodo recomendado para disparar emision desde codigo externo
 * (controllers, comandos CLI, otros listeners). Encapsula:
 *   1. Pre-validacion ligera reusando $factura->puedeEmitirDte() del trait
 *      (F6.1). Si falla, lanza FacturaNoEmisibleException con razon especifica
 *      y NO dispara el evento.
 *   2. Dispatch del evento FacturaListaParaEmitirEvent, que el listener
 *      ProcesarFacturaParaSiiListener (queue=sii) procesa async.
 *
 * dispatchAfterCommit es automatico por la interface ShouldDispatchAfterCommit
 * del evento: si el llamador esta dentro de DB::transaction, el job se encola
 * solo si la tx commitea.
 *
 * Defensa en profundidad: tanto este servicio como el mapper (F6.1) validan
 * pre-emision. Aqui evitamos encolar jobs garantizadamente fallidos; el
 * mapper protege la atomicidad de persistencia en caso de race.
 */
class EmitirDteDesdeFacturaService
{
    /**
     * @param array<int, array<string, mixed>> $referencias  para tipo_dte ∈ {56,61}
     * @param string                           $origen       'manual'|'automatico'|'reintento'
     *
     * @throws FacturaNoEmisibleException si la factura no es candidata.
     */
    public function dispatch(
        Factura $factura,
        array $referencias = [],
        string $origen = 'manual',
        ?int $usuarioId = null
    ): void {
        $this->validarPreEmision($factura);

        FacturaListaParaEmitirEvent::dispatch($factura, $referencias, $origen, $usuarioId);
    }

    /**
     * @throws FacturaNoEmisibleException
     */
    private function validarPreEmision(Factura $factura): void
    {
        // Camino feliz: el trait F6.1 ya combino los 5 chequeos en una sola
        // llamada. Si retorna true, no hay nada mas que validar.
        if ($factura->puedeEmitirDte()) {
            return;
        }

        // Mapeamos la razon especifica para mejor mensaje al caller. El orden
        // refleja la prioridad de errores reportables al operador.
        $facturaId = (int) $factura->id;

        if ($factura->tipo_dte === null) {
            throw FacturaNoEmisibleException::tipoDteFaltante($facturaId);
        }
        if ($factura->cliente_id === null) {
            throw FacturaNoEmisibleException::clienteFaltante($facturaId);
        }
        if ($factura->estado === 'ANULADA') {
            throw FacturaNoEmisibleException::estadoAnulada($facturaId);
        }
        if ($factura->sii_dte_emitido_id !== null) {
            throw FacturaNoEmisibleException::yaEmitida(
                $facturaId,
                (int) $factura->sii_dte_emitido_id
            );
        }
        if ($factura->detalles()->doesntExist()) {
            throw FacturaNoEmisibleException::sinDetalles($facturaId);
        }

        // Defensa: si llegamos aqui hay un estado no contemplado.
        throw FacturaNoEmisibleException::noEmisible($facturaId);
    }
}
