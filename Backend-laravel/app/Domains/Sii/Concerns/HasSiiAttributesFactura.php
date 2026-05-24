<?php

namespace App\Domains\Sii\Concerns;

use App\Domains\Comercial\Models\FacturaDetalle;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasSiiAttributesFactura
{
    public function initializeHasSiiAttributesFactura(): void
    {
        $this->fillable = array_merge($this->fillable ?? [], [
            'cliente_id',
            'tipo_dte',
            'forma_pago_codigo',
            'condicion_pago',
            'moneda',
            'monto_exento',
            'descuento_global_monto',
            'descuento_global_porcentaje',
            'emitir_dte_automatico',
        ]);

        $this->casts = array_merge($this->casts ?? [], [
            'cliente_id'                  => 'integer',
            'tipo_dte'                    => 'integer',
            'forma_pago_codigo'           => 'integer',
            'monto_exento'                => 'decimal:2',
            'descuento_global_monto'      => 'decimal:2',
            'descuento_global_porcentaje' => 'decimal:2',
            'emitir_dte_automatico'       => 'boolean',
        ]);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class);
    }
}
