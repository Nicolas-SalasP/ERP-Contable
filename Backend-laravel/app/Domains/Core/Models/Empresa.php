<?php
namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Contabilidad\Models\CentroCosto> $centrosCosto
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Tesoreria\Models\CuentaBancariaEmpresa> $cuentasBancarias
 */
class Empresa extends Model
{
    use \App\Domains\Sii\Concerns\HasSiiAttributesEmpresa;

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
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function centrosCosto(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Contabilidad\Models\CentroCosto::class, 'empresa_id');
    }

    public function cuentasBancarias(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Tesoreria\Models\CuentaBancariaEmpresa::class, 'empresa_id');
    }
}