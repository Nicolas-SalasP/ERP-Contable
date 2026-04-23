<?php
namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoSuscripcion extends Model
{
    protected $table = 'estados_suscripcion';
    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }
}