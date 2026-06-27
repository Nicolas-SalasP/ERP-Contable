<?php

namespace App\Domains\Sii\Concerns;

/**
 * @property string|null $comuna
 * @property string|null $ciudad
 * @property string|null $giro
 * @property int|null $codigo_actividad
 */
trait HasSiiAttributesCliente
{
    public function initializeHasSiiAttributesCliente(): void
    {
        $this->fillable = array_merge($this->fillable ?? [], [
            'comuna',
            'ciudad',
            'giro',
            'codigo_actividad',
        ]);

        $this->casts = array_merge($this->casts ?? [], [
            'codigo_actividad' => 'integer',
        ]);
    }
}
