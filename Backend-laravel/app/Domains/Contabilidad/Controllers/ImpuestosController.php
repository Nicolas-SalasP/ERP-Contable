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

    public function preCalculoRenta(Request $request, $anio)
    {
        try {
            $resultado = $this->service->preCalculoRenta($request->user()->empresa_id, (int) $anio);
            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    public function obtenerMapeo(Request $request)
    {
        try {
            $data = $this->service->obtenerMapeo($request->user()->empresa_id);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function guardarMapeo(Request $request)
    {
        try {
            $request->validate([
                'codigo_cuenta' => 'required|string',
                'concepto_sii' => 'required|string'
            ]);

            $this->service->guardarMapeo(
                $request->user()->empresa_id,
                $request->codigo_cuenta,
                $request->concepto_sii
            );

            return response()->json(['success' => true, 'message' => 'Mapeo guardado exitosamente.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function eliminarMapeo(Request $request, $id)
    {
        try {
            $this->service->eliminarMapeo($request->user()->empresa_id, $id);
            return response()->json(['success' => true, 'message' => 'Mapeo eliminado.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}