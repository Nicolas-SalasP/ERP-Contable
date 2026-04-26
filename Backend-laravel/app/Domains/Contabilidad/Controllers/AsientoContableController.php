<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Contabilidad\Models\AsientoContable;
use Illuminate\Http\Request;

class AsientoContableController
{
    protected $service;

    public function __construct(AsientoContableService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return AsientoContable::where('empresa_id', $request->user()->empresa_id)
            ->with('detalles.cuenta')
            ->orderBy('fecha', 'desc')
            ->paginate(15);
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->all();
            $datos['empresa_id'] = $request->user()->empresa_id;

            $asiento = $this->service->registrarAsiento($datos, $request->detalles);

            return response()->json([
                'success' => true,
                'message' => 'Asiento contable registrado con éxito',
                'data' => $asiento->load('detalles')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}