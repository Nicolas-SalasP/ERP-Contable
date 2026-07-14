<?php

namespace App\Domains\Core\Models;

use App\Domains\Contabilidad\Models\CentroCosto;
use App\Domains\Rrhh\Models\Mutualidad;
use App\Domains\Sii\Concerns\HasSiiAttributesEmpresa;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, CentroCosto> $centrosCosto
 * @property-read Collection<int, CuentaBancariaEmpresa> $cuentasBancarias
 */
class Empresa extends Model
{
    use HasSiiAttributesEmpresa;

    public const UPDATED_AT = null;

    protected $fillable = [
        'rut',
        'razon_social',
        'direccion',
        'email',
        'telefono',
        'logo_path',
        'color_primario',
        'regimen_tributario',
        'tasa_impuesto',
        'ppm_pct',
        'activa',
        'mutualidad_id',
        'mutual_tasa_adicional_pct',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsTo<Mutualidad, $this> */
    public function mutualidad(): BelongsTo
    {
        return $this->belongsTo(Mutualidad::class);
    }

    public function centrosCosto(): HasMany
    {
        return $this->hasMany(CentroCosto::class, 'empresa_id');
    }

    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(CuentaBancariaEmpresa::class, 'empresa_id');
    }
}
