<?php

namespace App\Domains\Sii\Concerns;

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
