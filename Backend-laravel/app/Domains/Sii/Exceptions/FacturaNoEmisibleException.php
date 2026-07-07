<?php

namespace App\Domains\Sii\Exceptions;

use DomainException;

/** F6.2 — pre-validacion fallida ANTES de dispatchear el evento de emision ("esta factura ni siquiera deberia intentarse emitir"): a diferencia de FacturaIncompletaParaSii (F6.1, que se lanza en el mapper durante el listener), esta se lanza antes de cualquier accion sincrona, sin evento, job ni folio reservado. */
class FacturaNoEmisibleException extends DomainException
{
    public const RAZON_TIPO_DTE_FALTANTE = 'tipo_dte_faltante';
    public const RAZON_CLIENTE_FALTANTE  = 'cliente_faltante';
    public const RAZON_ESTADO_ANULADA    = 'estado_anulada';
    public const RAZON_YA_EMITIDA        = 'ya_emitida';
    public const RAZON_SIN_DETALLES      = 'sin_detalles';
    public const RAZON_NO_EMISIBLE       = 'no_emisible';

    public readonly string $razon;
    public readonly int $facturaId;

    /** @var array<string, mixed> */
    public readonly array $contexto;

    /** @param array<string, mixed> $contexto */
    public function __construct(string $message, string $razon, int $facturaId, array $contexto = [])
    {
        parent::__construct($message);
        $this->razon     = $razon;
        $this->facturaId = $facturaId;
        $this->contexto  = $contexto;
    }

    public static function tipoDteFaltante(int $facturaId): self
    {
        return new self(
            "Factura {$facturaId} no tiene tipo_dte asignado; no puede emitirse.",
            self::RAZON_TIPO_DTE_FALTANTE,
            $facturaId
        );
    }

    public static function clienteFaltante(int $facturaId): self
    {
        return new self(
            "Factura {$facturaId} no tiene cliente_id asignado; no puede emitirse.",
            self::RAZON_CLIENTE_FALTANTE,
            $facturaId
        );
    }

    public static function estadoAnulada(int $facturaId): self
    {
        return new self(
            "Factura {$facturaId} en estado ANULADA no puede emitirse.",
            self::RAZON_ESTADO_ANULADA,
            $facturaId
        );
    }

    public static function yaEmitida(int $facturaId, int $dteEmitidoId): self
    {
        return new self(
            "Factura {$facturaId} ya fue emitida (DTE id={$dteEmitidoId}); no puede re-emitirse.",
            self::RAZON_YA_EMITIDA,
            $facturaId,
            ['dte_emitido_id' => $dteEmitidoId]
        );
    }

    public static function sinDetalles(int $facturaId): self
    {
        return new self(
            "Factura {$facturaId} no tiene detalles; al menos 1 linea es requerida.",
            self::RAZON_SIN_DETALLES,
            $facturaId
        );
    }

    public static function noEmisible(int $facturaId): self
    {
        return new self(
            "Factura {$facturaId} no es emisible. Verifique tipo_dte, cliente_id, estado, detalles y que no este ya emitida.",
            self::RAZON_NO_EMISIBLE,
            $facturaId
        );
    }
}
