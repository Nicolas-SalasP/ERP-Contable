<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\ImpuestosService;
use Illuminate\Http\Request;
use Exception;

class ImpuestosController
{
    protected $service;

    public function __construct(ImpuestosService $service)
    {
        $this->service = $service;
    }

    public function simularF29(Request $request, $mes, $anio)
    {
        try {
            $resultado = $this->service->simularF29($request->user()->empresa_id, (int) $mes, (int) $anio);
            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function ejecutarF29(Request $request)
    {
        try {
            $datos = $request->validate([
                'mes' => 'required|integer',
                'anio' => 'required|integer'
            ]);

            $asiento = $this->service->ejecutarF29(
                $request->user()->empresa_id,
                $request->user()->id,
                $datos['mes'],
                $datos['anio']
            );

            return response()->json([
                'success' => true,
                'message' => 'Cierre F29 ejecutado correctamente.',
                'data' => $asiento
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }
}