<?php
namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
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
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function centrosCosto()
    {
        return $this->hasMany(\App\Domains\Contabilidad\Models\CentroCosto::class, 'empresa_id');
    }

    public function cuentasBancarias()
    {
        return $this->hasMany(\App\Domains\Tesoreria\Models\CuentaBancariaEmpresa::class, 'empresa_id');
    }
}