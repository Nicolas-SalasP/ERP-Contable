<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $asiento_id
 * @property string $cuenta_contable
 * @property string|null $fecha
 * @property string $tipo_operacion
 * @property float $debe
 * @property float $haber
 * @property string|null $descripcion_extensa
 * @property int|null $centro_costo_id
 * @property string|null $empleado_nombre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DetalleAsiento extends Model
{
    protected $table = 'detalles_asiento';
    public $timestamps = false;

    protected $fillable = [
        'asiento_id',
        'cuenta_contable',
        'fecha',
        'tipo_operacion',
        'debe',
        'haber',
        'descripcion_extensa',
        'centro_costo_id',
        'empleado_nombre',
    ];

    protected $casts = [
        'fecha' => 'date',
        'debe' => 'decimal:2',
        'haber' => 'decimal:2',
    ];

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_contable', 'codigo');
    }
}