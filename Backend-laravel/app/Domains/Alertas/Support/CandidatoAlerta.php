<?php

namespace App\Domains\Alertas\Support;

/**
 * Resultado de un EvaluadorAlerta: un hecho candidato a convertirse en fila `alertas` +
 * notificacion. No decide envio ni dedupe -- eso lo resuelve MotorAlertasJob.
 */
final class CandidatoAlerta
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        public readonly int $empresaId,
        public readonly string $tipo,
        public readonly string $nivel,
        public readonly ?string $entidadType,
        public readonly ?int $entidadId,
        public readonly string $mensaje,
        public readonly array $datos = [],
        /** true = se puede repetir el aviso una vez por dia mientras el nivel no cambie; false = una unica vez por nivel hasta que se resuelva/escale. */
        public readonly bool $esDiaria = false,
    ) {}
}
