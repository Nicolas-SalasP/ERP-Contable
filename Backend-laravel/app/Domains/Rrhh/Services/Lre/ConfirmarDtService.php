<?php

namespace App\Domains\Rrhh\Services\Lre;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\LreEnvio;

/**
 * Registra la confirmación manual del LRE en el portal Mi DT.
 * Transiciona el estado a CONFIRMADO_DT y persiste el número de confirmación.
 */
class ConfirmarDtService
{
    public function confirmar(LreEnvio $lre, ?string $numeroConfirmacion): LreEnvio
    {
        if ($lre->estado !== LreEnvio::ESTADO_VALIDADO) {
            throw RrhhException::regla(
                'Solo se puede confirmar un LRE en estado VALIDADO. Estado actual: ' . $lre->estado
            );
        }

        $lre->update([
            'estado'                 => LreEnvio::ESTADO_CONFIRMADO_DT,
            'numero_confirmacion_dt' => $numeroConfirmacion,
            'confirmado_at'          => now(),
        ]);

        return $lre->fresh();
    }
}
