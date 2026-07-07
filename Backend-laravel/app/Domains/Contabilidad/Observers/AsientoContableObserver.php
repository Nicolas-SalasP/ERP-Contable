<?php

namespace App\Domains\Contabilidad\Observers;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Services\PeriodoContableService;

/** Garantia central de inmutabilidad: bloquea crear/modificar/eliminar asientos en un periodo cerrado aunque el llamador olvide invocar el guard explicito (cierra F-1/F-2 de la auditoria). */
class AsientoContableObserver
{
    public function __construct(private readonly PeriodoContableService $periodos)
    {
    }

    public function creating(AsientoContable $asiento): void
    {
        $this->periodos->assertAbierto((int) $asiento->empresa_id, $asiento->fecha);
    }

    public function updating(AsientoContable $asiento): void
    {
        $empresaId = (int) $asiento->empresa_id;

        // No se puede modificar un asiento que vive en un periodo cerrado...
        $fechaOriginal = $asiento->getOriginal('fecha');
        if ($fechaOriginal !== null) {
            $this->periodos->assertAbierto($empresaId, $fechaOriginal);
        }

        // ...ni moverlo hacia un periodo cerrado.
        $this->periodos->assertAbierto($empresaId, $asiento->fecha);
    }

    public function deleting(AsientoContable $asiento): void
    {
        $this->periodos->assertAbierto((int) $asiento->empresa_id, $asiento->fecha);
    }
}
