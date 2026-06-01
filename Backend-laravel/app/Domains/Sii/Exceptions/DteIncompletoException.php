<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

class DteIncompletoException extends RuntimeException
{
    public const MOTIVO_CAMPO_FALTANTE        = 'campo_faltante';
    public const MOTIVO_CARACTER_NO_CONVERTIBLE = 'caracter_no_convertible';
    public const MOTIVO_TIPO_INCOMPATIBLE      = 'tipo_incompatible';
    public const MOTIVO_MONTOS_NO_CUADRAN      = 'montos_no_cuadran';

    public readonly string $motivo;

    /** @var array<string, array{esperado: float|int, real: float|int}> */
    public readonly array $discrepancias;

    /**
     * @param array<string, array{esperado: float|int, real: float|int}> $discrepancias
     */
    public function __construct(
        string $message,
        string $motivo = self::MOTIVO_CAMPO_FALTANTE,
        array $discrepancias = []
    ) {
        parent::__construct($message);
        $this->motivo        = $motivo;
        $this->discrepancias = $discrepancias;
    }

    public static function campoFaltante(string $campo): self
    {
        return new self(
            "Campo obligatorio para el DTE no presente: {$campo}",
            self::MOTIVO_CAMPO_FALTANTE
        );
    }

    public static function caracterNoConvertible(string $campo, string $valor): self
    {
        $muestra = function_exists('mb_substr') ? mb_substr($valor, 0, 60, 'UTF-8') : substr($valor, 0, 60);

        return new self(
            "El campo '{$campo}' contiene caracteres no representables en ISO-8859-1 (ej. emojis o alfabetos no latinos). Valor: \"{$muestra}\"",
            self::MOTIVO_CARACTER_NO_CONVERTIBLE
        );
    }

    public static function tipoIncompatible(int $tipoDte, string $razon): self
    {
        return new self(
            "DTE tipo {$tipoDte} no satisface precondiciones: {$razon}",
            self::MOTIVO_TIPO_INCOMPATIBLE
        );
    }

    /**
     * @param array<string, array{esperado: float|int, real: float|int}> $discrepancias
     *   Mapeado por nombre de columna del DTE -> ['esperado' => x, 'real' => y].
     */
    public static function montosNoCuadran(int $dteId, array $discrepancias): self
    {
        $detalle = implode('; ', array_map(
            fn (string $campo, array $d) => sprintf(
                '%s esperado=%s, real=%s',
                $campo,
                number_format((float) $d['esperado'], 2, '.', ''),
                number_format((float) $d['real'], 2, '.', '')
            ),
            array_keys($discrepancias),
            array_values($discrepancias)
        ));

        return new self(
            "DTE {$dteId} no cuadra: {$detalle}",
            self::MOTIVO_MONTOS_NO_CUADRAN,
            $discrepancias
        );
    }
}
