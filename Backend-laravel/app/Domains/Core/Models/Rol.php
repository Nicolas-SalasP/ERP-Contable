<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * NO agregar HasEmpresaScope aqui: empresa_id es NULL a proposito para los roles de sistema
 * (Super Admin, Administrador, etc), compartidos por todas las empresas. El scope generico
 * filtra con "WHERE empresa_id = X" estricto (sin "OR empresa_id IS NULL"), asi que ocultaria
 * TODOS los roles de sistema en cualquier query con usuario autenticado -- rompe el login/RBAC
 * de la inmensa mayoria de usuarios, no solo un caso raro. Para roles propios de una empresa,
 * usar scopeVisiblesPara() (ya filtra a mano y SI incluye los de sistema).
 *
 * @property int $id
 * @property int|null $empresa_id
 * @property string $nombre
 * @property int $jerarquia
 * @property array $permisos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
        'permisos' => 'array',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    /** Indica si es un rol de sistema (compartido por todas las empresas). */
    public function esDeSistema(): bool
    {
        return $this->empresa_id === null;
    }

    /** Roles visibles para una empresa: los de sistema (empresa_id null) más los personalizados de la propia empresa. */
    public function scopeVisiblesPara($query, int $empresaId)
    {
        return $query->where(function ($q) use ($empresaId) {
            $q->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
        });
    }
}
