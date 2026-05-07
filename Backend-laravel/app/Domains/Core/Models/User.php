<?php

namespace App\Domains\Core\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';
    public const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id',
        'email',
        'password',
        'nombre',
        'rol_id',
        'estado_suscripcion_id',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class);
    }

    public function estadoSuscripcion()
    {
        return $this->belongsTo(EstadoSuscripcion::class);
    }
}