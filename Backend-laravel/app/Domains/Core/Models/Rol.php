<?php
namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'nombre',
        'permisos',
        'jerarquia',
    ];

    protected $casts = [
        'permisos' => 'array'
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Indica si es un rol de sistema (compartido por todas las empresas).
     */
    public function esDeSistema(): bool
    {
        return $this->empresa_id === null;
    }

    /**
     * Roles visibles para una empresa: los de sistema (empresa_id null) mas
     * los personalizados de la propia empresa.
     */
    public function scopeVisiblesPara($query, int $empresaId)
    {
        return $query->where(function ($q) use ($empresaId) {
            $q->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
        });
    }
}