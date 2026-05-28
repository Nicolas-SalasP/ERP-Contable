<?php

namespace App\Domains\CorreccionMonetaria\Controllers;

use App\Domains\CorreccionMonetaria\Services\CorreccionMonetariaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class CorreccionMonetariaController
{
    public function __construct(private readonly CorreccionMonetariaService $service) {}

    public function indices(Request $request, int $anio)
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => $this->service->obtenerIndicesAnio($anio),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function guardarIndice(Request $request)
    {
        try {
            $data = $request->validate([
                'anio'        => 'required|integer|min:2000|max:2100',
                'mes'         => 'required|integer|min:1|max:12',
                'variacion'   => 'required|numeric|min:-20|max:50',
                'observacion' => 'nullable|string|max:500',
            ]);

            $indice = $this->service->guardarIndice(
                $request->user()->id,
                $data['anio'],
                $data['mes'],
                (float) $data['variacion'],
                $data['observacion'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => "IPC de {$indice->nombre_mes} {$indice->anio} guardado correctamente.",
                'data'    => $indice,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function configuracion(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => $this->service->obtenerConfiguracion($request->user()->empresa_id),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function actualizarConfiguracion(Request $request)
    {
        try {
            $data = $request->validate([
                'aplica_cm'                  => 'sometimes|boolean',
                'modalidad'                  => 'sometimes|in:mensual,anual',
                'mes_cierre'                 => 'sometimes|integer|min:1|max:12',
                'cuenta_activos_codigo'      => 'sometimes|string|max:20',
                'cuenta_depreciacion_codigo' => 'sometimes|string|max:20',
                'cuenta_patrimonio_codigo'   => 'sometimes|string|max:20',
                'cuenta_existencias_codigo'  => 'sometimes|string|max:20',
                'cuenta_pasivos_codigo'      => 'sometimes|string|max:20',
                'activo'                     => 'sometimes|boolean',
            ]);

            $config = $this->service->actualizarConfiguracion($request->user()->empresa_id, $data);

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada.',
                'data'    => $config,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function cuentasConfiguracion(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => $this->service->obtenerCuentasConfiguracion($request->user()->empresa_id),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function actualizarCuentasConfiguracion(Request $request)
    {
        try {
            $data = $request->validate([
                'cuentas'                    => 'required|array|min:1',
                'cuentas.*.cuenta_codigo'    => 'required|string|max:20',
                'cuentas.*.aplica'           => 'required|boolean',
                'cuentas.*.factor_override'  => 'nullable|numeric|min:-50|max:100',
            ]);

            $this->service->actualizarCuentasConfiguracion($request->user()->empresa_id, $data['cuentas']);

            return response()->json(['success' => true, 'message' => 'Cuentas actualizadas.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function agregarCuenta(Request $request)
    {
        try {
            $data = $request->validate([
                'cuenta_codigo' => 'required|string|max:20',
                'rol_cm'        => 'required|string',
            ]);

            $cuenta = $this->service->agregarCuentaConfiguracion(
                $request->user()->empresa_id,
                $data['cuenta_codigo'],
                $data['rol_cm'],
            );

            return response()->json([
                'success' => true,
                'message' => "Cuenta {$data['cuenta_codigo']} agregada a la configuración de CM.",
                'data'    => $cuenta,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function estadoPeriodo(Request $request, int $mes, int $anio)
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => $this->service->estadoPeriodo($request->user()->empresa_id, $mes, $anio),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function simular(Request $request, int $mes, int $anio)
    {
        try {
            $resultado = $this->service->simular($request->user()->empresa_id, $mes, $anio);

            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function ejecutar(Request $request)
    {
        try {
            $data = $request->validate([
                'mes'  => 'required|integer|min:1|max:12',
                'anio' => 'required|integer|min:2000|max:2100',
            ]);

            $resultado = $this->service->ejecutar(
                $request->user()->empresa_id,
                $request->user()->id,
                $data['mes'],
                $data['anio'],
            );

            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function historial(Request $request)
    {
        try {
            $anio = $request->query('anio') ? (int) $request->query('anio') : null;

            return response()->json([
                'success' => true,
                'data'    => $this->service->obtenerHistorial($request->user()->empresa_id, $anio),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
