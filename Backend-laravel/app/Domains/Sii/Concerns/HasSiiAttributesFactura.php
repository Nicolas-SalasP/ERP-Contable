<?php

namespace App\Domains\Sii\Concerns;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            // F6.1: vinculo opcional al snapshot SiiDteEmitido emitido.
            'sii_dte_emitido_id',
        ]);

        $this->casts = array_merge($this->casts ?? [], [
            'cliente_id'                  => 'integer',
            'tipo_dte'                    => 'integer',
            'forma_pago_codigo'           => 'integer',
            'monto_exento'                => 'decimal:2',
            'descuento_global_monto'      => 'decimal:2',
            'descuento_global_porcentaje' => 'decimal:2',
            'emitir_dte_automatico'       => 'boolean',
            'sii_dte_emitido_id'          => 'integer',
        ]);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class);
    }

    /**
     * F6.1 — Relacion al Cliente via cliente_id.
     * El modelo Factura del Comercial no declara esta relacion (auditoria F6.0
     * R2); la agregamos aqui para que mapper y UI puedan acceder a
     * $factura->cliente sin tocar app/Domains/Comercial/.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * F6.1 — Vinculo 1:1 opcional con el snapshot SiiDteEmitido (nullable hasta
     * que se completa el mapeo). Cuando esta seteado indica que la factura ya
     * tiene DTE emitido y NO puede re-emitirse (idempotencia enforce en mapper).
     */
    public function dteEmitido(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'sii_dte_emitido_id');
    }

    /**
     * F6.1 — Pre-check ligero para UI/endpoints: ¿esta factura puede emitirse?
     *
     * NO valida cuadratura de montos (delegada al mapper que invoca
     * CuadraturaMontosValidator). Util para decidir si mostrar el boton
     * "Emitir DTE" en la tabla de facturas.
     */
    public function puedeEmitirDte(): bool
    {
        return $this->tipo_dte !== null
            && $this->cliente_id !== null
            && $this->estado !== 'ANULADA'
            && $this->sii_dte_emitido_id === null
            && $this->detalles()->exists();
    }
}
