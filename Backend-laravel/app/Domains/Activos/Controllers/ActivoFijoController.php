<?php

namespace App\Domains\Activos\Controllers;

use App\Domains\Activos\Services\ActivoFijoService;
use App\Domains\Contabilidad\Models\CentroCosto;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Illuminate\Http\Request;
use Exception;

class ActivoFijoController
{
    protected $service;

    public function __construct(ActivoFijoService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            $activos = $this->service->listarActivos($request->user()->empresa_id);
            return response()->json(['success' => true, 'data' => $activos]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function pendientes(Request $request)
    {
        try {
            $pendientes = $this->service->listarPendientes($request->user()->empresa_id);
            return response()->json(['success' => true, 'data' => $pendientes]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->validate([
                'nombre' => 'required|string',
                'codigo' => 'nullable|string',
                'descripcion' => 'nullable|string',
                'cuenta_activo_codigo' => 'nullable|string',
                'cuenta_depreciacion_codigo' => 'nullable|string',
                'cuenta_gasto_codigo' => 'nullable|string',
                'centro_costo_id' => 'nullable|integer',
                'valor_adquisicion' => 'required|numeric|min:1',
                'fecha_adquisicion' => 'required|date',
                'vida_util_meses' => 'required|integer|min:1',
                'valor_residual' => 'nullable|numeric|min:1',
                'estado' => 'nullable|string'
            ]);

            $datos['empresa_id'] = $request->user()->empresa_id;

            $activo = $this->service->registrarActivo($datos);

            return response()->json(['success' => true, 'message' => 'Activo registrado exitosamente', 'data' => $activo], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function parametros(Request $request)
    {
        try {
            $planCuentas = PlanCuenta::where('empresa_id', $request->user()->empresa_id)
                ->where('imputable', true)
                ->where('activo', true)
                ->get();

            $cuentasActivo = $planCuentas->filter(function ($c) {
                return $c->tipo === 'ACTIVO' && !str_contains(strtolower($c->nombre), 'depreciacion');
            })->values();

            $cuentasDepreciacion = $planCuentas->filter(function ($c) {
                return $c->tipo === 'ACTIVO' && str_contains(strtolower($c->nombre), 'depreciacion');
            })->values();

            $cuentasGasto = $planCuentas->filter(function ($c) {
                return $c->tipo === 'GASTO';
            })->values();

            $centros = CentroCosto::where('empresa_id', $request->user()->empresa_id)
                ->where('activo', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'cuentas_activo' => $cuentasActivo,
                    'cuentas_depreciacion' => $cuentasDepreciacion,
                    'cuentas_gasto' => $cuentasGasto,
                    'centros_costo' => $centros
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function proyectos(Request $request)
    {
        try {
            $proyectos = $this->service->listarProyectos($request->user()->empresa_id);
            return response()->json(['success' => true, 'data' => $proyectos]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function storeProyecto(Request $request)
    {
        try {
            $datos = $request->validate([
                'nombre' => 'required|string',
                'tipo_activo_id' => 'required|integer',
                'anio_fabricacion' => 'required|integer',
                'vida_util_meses' => 'required|integer',
                'centro_costo_id' => 'nullable|integer',
                'empleado_id' => 'nullable|integer'
            ]);

            $datos['empresa_id'] = $request->user()->empresa_id;

            $proyecto = $this->service->registrarProyecto($datos);

            return response()->json(['success' => true, 'message' => 'Proyecto creado exitosamente', 'data' => $proyecto], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function depreciarMes(Request $request)
    {
        try {
            $datos = $request->validate([
                'mes_anio' => 'required|date_format:Y-m'
            ]);

            $resultado = $this->service->depreciarMes(
                $request->user()->empresa_id, 
                $request->user()->id, 
                $datos['mes_anio']
            );

            return response()->json([
                'success' => true, 
                'message' => $resultado['mensaje'],
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}