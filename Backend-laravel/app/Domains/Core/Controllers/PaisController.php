<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Services\PaisService;

class PaisController
{
    protected $service;

    public function __construct(PaisService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->obtenerPaisesActivos()
        ]);
    }
}