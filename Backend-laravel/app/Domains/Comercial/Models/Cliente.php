<?php
namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

class Cliente extends Model
{
    protected $table = 'clientes';
    const UPDATED_AT = null;

    protected $fillable = [
        'rut',
        'razon_social',
        'contacto_nombre',
        'contacto_email',
        'contacto_telefono',
        'direccion',
        'telefono',
        'email',
        'estado',
        'empresa_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}