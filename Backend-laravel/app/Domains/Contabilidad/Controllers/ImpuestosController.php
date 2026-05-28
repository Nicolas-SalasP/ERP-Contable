<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\ImpuestosService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            $mesInt = (int) $mes;
            $anioInt = (int) $anio;

            if ($mesInt < 1 || $mesInt > 12) {
                return response()->json([
                    'success' => false,
                    'message' => 'El mes debe estar entre 1 y 12.'
                ], 422);
            }

            if ($anioInt < 2000 || $anioInt > 2100) {
                return response()->json([
                    'success' => false,
                    'message' => 'El año debe estar entre 2000 y 2100.'
                ], 422);
            }

            $resultado = $this->service->simularF29($request->user()->empresa_id, $mesInt, $anioInt);
            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function ejecutarF29(Request $request)
    {
        try {
            $datos = $request->validate([
                'mes' => 'required|integer|between:1,12',
                'anio' => 'required|integer|min:2000|max:2100'
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
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => $e->getMessage()
            ], 422);
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
                'codigo_cuenta' => [
                    'required',
                    'string',
                    Rule::unique('mapeo_cuentas_sii')->where(function ($query) use ($request) {
                        return $query->where('empresa_id', $request->user()->empresa_id);
                    })
                ],
                'concepto_sii' => 'required|string|in:INGRESOS_GIRO,OTROS_INGRESOS,COMPRAS,DEPRECIACION,REMUNERACIONES,HONORARIOS,ARRIENDOS,GASTOS_GENERALES'
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