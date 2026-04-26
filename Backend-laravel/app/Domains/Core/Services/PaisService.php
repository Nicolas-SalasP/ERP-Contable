<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\Pais;

class PaisService
{
    public function obtenerPaisesActivos()
    {
        return Pais::where('activo', true)->orderBy('nombre')->get();
    }
}