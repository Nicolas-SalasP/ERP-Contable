<?php
namespace App\Domains\Tesoreria\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoBanco extends Model
{
    protected $table = 'catalogo_bancos';
    public $timestamps = false;

    protected $fillable = ['nombre'];
}