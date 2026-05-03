<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\ReporteContableService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Exception;

class ReporteController
{
    protected $service;

    public function __construct(ReporteContableService $service)
    {
        $this->service = $service;
    }

    public function libroDiario(Request $request)
    {
        try {
            $request->validate([
                'desde' => 'required|date',
                'hasta' => 'required|date|after_or_equal:desde'
            ]);

            $cuenta = $request->query('cuenta');
            $desde = $request->query('desde') ?? now()->startOfMonth()->format('Y-m-d');
            $hasta = $request->query('hasta') ?? now()->format('Y-m-d');
            $filtro = (int) $request->query('filtro', 1);

            if (!empty($cuenta)) {
                $reporte = $this->service->generarLibroMayor($request->user()->empresa_id, $cuenta, $desde, $hasta, $filtro);
            } else {
                $reporte = $this->service->generarLibroDiario($request->user()->empresa_id, $desde, $hasta, $filtro);
            }

            return response()->json([
                'success' => true,
                'data' => $reporte
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros obligatorios',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400); 
        }
    }

    public function libroMayor(Request $request)
    {
        try {
            $request->validate([
                'cuenta_contable' => 'required|string',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            ]);

            $reporte = $this->service->generarLibroMayor(
                $request->user()->empresa_id,
                $request->cuenta_contable,
                $request->fecha_inicio,
                $request->fecha_fin
            );

            return response()->json(['success' => true, 'data' => $reporte]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}