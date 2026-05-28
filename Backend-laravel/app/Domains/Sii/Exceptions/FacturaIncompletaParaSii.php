<?php

namespace App\Domains\Sii\Exceptions;

use DomainException;

/**
 * F6.1 — Falla de validacion pre-mapeo de una Factura del Comercial al
 * snapshot SiiDteEmitido. Lanzada por FacturaAComercialDteMapper ANTES
 * de cualquier persistencia, de modo que no consuma folio CAF (D5).
 *
 * El operador corrige el dato faltante en la Factura y reintenta. Mientras
 * el mapper no haya completado exitosamente, no hay efectos secundarios:
 * cero folios reservados, cero filas SiiDteEmitido, cero requests al SII.
 */
class FacturaIncompletaParaSii extends DomainException
{
    public const TIPO_DTE_FALTANTE              = 'tipo_dte_faltante';
    public const TIPO_DTE_INVALIDO              = 'tipo_dte_invalido';
    public const CLIENTE_FALTANTE               = 'cliente_faltante';
    public const ESTADO_INVALIDO                = 'estado_invalido';
    public const YA_EMITIDA                     = 'ya_emitida';
    public const SIN_DETALLES                   = 'sin_detalles';
    public const TIPO_DOCUMENTO_INCONSISTENTE   = 'tipo_documento_inconsistente';
    public const MONTOS_NO_CUADRAN              = 'montos_no_cuadran';
    public const REFERENCIAS_FALTANTES          = 'referencias_faltantes';
    public const EMPRESA_SIN_AMBIENTE_SII       = 'empresa_sin_ambiente_sii';

    public readonly string $razon;
    public readonly int $facturaId;

    /** @var array<string, mixed> contexto opcional para logs. */
    public readonly array $contexto;

    /**
     * @param array<string, mixed> $contexto
     */
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
            "Factura {$facturaId} no tiene tipo_dte asignado; requerido para emitir al SII.",
            self::TIPO_DTE_FALTANTE,
            $facturaId
        );
    }

    /**
     * @param array<int, int> $validos
     */
    public static function tipoDteInvalido(int $facturaId, ?int $tipoDte, array $validos): self
    {
        return new self(
            sprintf(
                'Factura %d tiene tipo_dte=%s; permitidos: [%s].',
                $facturaId,
                $tipoDte === null ? 'null' : (string) $tipoDte,
                implode(', ', $validos)
            ),
            self::TIPO_DTE_INVALIDO,
            $facturaId,
            ['tipo_dte' => $tipoDte, 'validos' => $validos]
        );
    }

    public static function clienteFaltante(int $facturaId): self
    {
        return new self(
            "Factura {$facturaId} no tiene cliente_id asignado; requerido para emitir al SII.",
            self::CLIENTE_FALTANTE,
            $facturaId
        );
    }

    public static function estadoInvalido(int $facturaId, string $estado): self
    {
        return new self(
            "Factura {$facturaId} en estado '{$estado}' no es emisible al SII.",
            self::ESTADO_INVALIDO,
            $facturaId,
            ['estado_actual' => $estado]
        );
    }

    public static function yaEmitida(int $facturaId, int $dteEmitidoId): self
    {
        return new self(
            "Factura {$facturaId} ya tiene DTE emitido (sii_dte_emitido_id={$dteEmitidoId}); no se puede re-mapear.",
            self::YA_EMITIDA,
            $facturaId,
            ['dte_emitido_id' => $dteEmitidoId]
        );
    }

    public static function sinDetalles(int $facturaId): self
    {
        return new self(
            "Factura {$facturaId} no tiene detalles; al menos 1 linea es requerida.",
            self::SIN_DETALLES,
            $facturaId
        );
    }

    public static function tipoDocumentoInconsistente(int $facturaId, string $tipoDocumento, int $tipoDte): self
    {
        return new self(
            "Factura {$facturaId}: tipo_documento='{$tipoDocumento}' inconsistente con tipo_dte={$tipoDte}.",
            self::TIPO_DOCUMENTO_INCONSISTENTE,
            $facturaId,
            ['tipo_documento' => $tipoDocumento, 'tipo_dte' => $tipoDte]
        );
    }

    public static function montosNoCuadran(int $facturaId, string $detalle): self
    {
        return new self(
            "Factura {$facturaId}: montos no cuadran. {$detalle}",
            self::MONTOS_NO_CUADRAN,
            $facturaId,
            ['detalle' => $detalle]
        );
    }

    public static function referenciasFaltantes(int $facturaId, int $tipoDte): self
    {
        return new self(
            "Factura {$facturaId} tipo_dte={$tipoDte} (NC/ND) requiere al menos 1 referencia al documento original.",
            self::REFERENCIAS_FALTANTES,
            $facturaId,
            ['tipo_dte' => $tipoDte]
        );
    }

    public static function empresaSinAmbienteSii(int $facturaId, int $empresaId): self
    {
        return new self(
            "Factura {$facturaId}: empresa {$empresaId} no tiene ambiente_sii configurado.",
            self::EMPRESA_SIN_AMBIENTE_SII,
            $facturaId,
            ['empresa_id' => $empresaId]
        );
    }
}
