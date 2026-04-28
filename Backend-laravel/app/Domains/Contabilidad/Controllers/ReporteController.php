<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\ReporteContableService;
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
            $cuenta = $request->query('cuenta');
            $desde = $request->query('desde') ?? now()->startOfMonth()->format('Y-m-d');
            $hasta = $request->query('hasta') ?? now()->format('Y-m-d');

            if (!empty($cuenta)) {
                $reporte = $this->service->generarLibroMayor($request->user()->empresa_id, $cuenta, $desde, $hasta);
            } else {
                $reporte = $this->service->generarLibroDiario($request->user()->empresa_id, $desde, $hasta);
            }

            return response()->json([
                'success' => true,
                'data' => $reporte
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
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