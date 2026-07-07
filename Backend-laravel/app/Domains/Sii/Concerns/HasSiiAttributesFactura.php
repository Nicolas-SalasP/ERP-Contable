<?php

namespace App\Domains\Sii\Concerns;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $tipo_dte
 * @property int|null $cliente_id
 * @property int|null $forma_pago_codigo
 * @property string|null $condicion_pago
 * @property string|null $moneda
 * @property string|null $monto_exento
 * @property string|null $descuento_global_monto
 * @property string|null $descuento_global_porcentaje
 * @property bool $emitir_dte_automatico
 * @property int|null $sii_dte_emitido_id
 */
trait HasSiiAttributesFactura
{
    public function initializeHasSiiAttributesFactura(): void
    {
        $this->fillable = array_merge($this->fillable, [
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

        $this->casts = array_merge($this->casts, [
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

    /** F6.1 — relacion agregada aqui (no en el modelo Factura de Comercial) para que mapper/UI accedan a $factura->cliente sin tocar app/Domains/Comercial/. */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** F6.1 — vinculo 1:1 opcional con el snapshot SiiDteEmitido; si esta seteado la factura ya tiene DTE emitido y no puede re-emitirse (idempotencia enforce en mapper). */
    public function dteEmitido(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'sii_dte_emitido_id');
    }

    /** F6.1 — pre-check ligero para UI/endpoints; no valida cuadratura de montos (delegada al mapper via CuadraturaMontosValidator). */
    public function puedeEmitirDte(): bool
    {
        return $this->tipo_dte !== null
            && $this->cliente_id !== null
            && $this->estado !== 'ANULADA'
            && $this->sii_dte_emitido_id === null
            && $this->detalles()->exists();
    }
}
