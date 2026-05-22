<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\ReporteContableService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Carbon\Carbon;
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

            $desdeCarbon = Carbon::parse($request->query('desde'));
            $hastaCarbon = Carbon::parse($request->query('hasta'));

            if ($desdeCarbon->diffInDays($hastaCarbon) > 366) {
                throw ValidationException::withMessages([
                    'hasta' => 'El rango de búsqueda no puede superar 1 año (366 días) por rendimiento.'
                ]);
            }

            $cuenta = $request->query('cuenta');
            $desde = $request->query('desde') ?? now()->startOfMonth()->format('Y-m-d');
            $hasta = $request->query('hasta') ?? now()->format('Y-m-d');
            $filtro = (int) $request->query('filtro', 1);
            $search = $request->query('search');

            if (!empty($cuenta)) {
                $reporte = $this->service->generarLibroMayor($request->user()->empresa_id, $cuenta, $desde, $hasta, $filtro);
            } else {
                $reporte = $this->service->generarLibroDiario($request->user()->empresa_id, $desde, $hasta, $filtro, $search);
            }

            return response()->json([
                'success' => true,
                'data' => $reporte
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros obligatorios o son inválidos',
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

            $desdeCarbon = Carbon::parse($request->fecha_inicio);
            $hastaCarbon = Carbon::parse($request->fecha_fin);

            if ($desdeCarbon->diffInDays($hastaCarbon) > 366) {
                throw ValidationException::withMessages([
                    'fecha_fin' => 'El rango de búsqueda no puede superar 1 año (366 días).'
                ]);
            }

            $reporte = $this->service->generarLibroMayor(
                $request->user()->empresa_id,
                $request->cuenta_contable,
                $request->fecha_inicio,
                $request->fecha_fin
            );

            return response()->json(['success' => true, 'data' => $reporte]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Errores de validación', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}