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
        'empresa_activa_id',
        'email',
        'password',
        'nombre',
        'rol_id',
        'estado_suscripcion_id',
        'ultimo_acceso',
        'tenri_user_id',
        'plan_slug',
        'module_keys',
        'tenri_synced_at',
        'subscription_status',
        'subscription_ends_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'module_keys' => 'array',
            'tenri_synced_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->empresa_activa_id === null && $user->empresa_id !== null) {
                $user->empresa_activa_id = $user->empresa_id;
            }
        });
    }

    public function empresas(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_user')
            ->withPivot('rol_id', 'created_at');
    }

    public function empresaActiva(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_activa_id');
    }

    public function empresa()
    {
        // Apunta a empresa_activa_id para que $user->empresa devuelva siempre
        // la empresa activa actual (igual a empresa_id en usuarios de una sola empresa).
        return $this->belongsTo(Empresa::class, 'empresa_activa_id');
    }

    public function rol(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function estadoSuscripcion()
    {
        return $this->belongsTo(EstadoSuscripcion::class);
    }
}