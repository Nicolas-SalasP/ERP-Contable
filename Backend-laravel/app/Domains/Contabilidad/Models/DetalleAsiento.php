<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'fecha' => 'date',
        'debe' => 'decimal:2',
        'haber' => 'decimal:2',
    ];

    public function asiento()
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function cuenta()
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_contable', 'codigo');
    }
}