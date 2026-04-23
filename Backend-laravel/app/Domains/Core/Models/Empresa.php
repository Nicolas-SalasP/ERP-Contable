<?php
namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    // Por defecto Laravel asume timestamps (created_at, updated_at). Tu BD original solo tiene created_at.
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
}