<?php

namespace App\Domains\Sii\Concerns;

trait HasSiiAttributesProducto
{
    public function initializeHasSiiAttributesProducto(): void
    {
        $this->fillable = array_merge($this->fillable ?? [], [
            'codigo_sii_producto',
            'codigo_sii_tipo',
            'unidad_medida_sii',
        ]);
    }
}
