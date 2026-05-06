<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Exception;

class AsientoContableController
{
    protected $service;

    public function __construct(AsientoContableService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return $this->service->obtenerAsientosPaginados($request->user()->empresa_id);
    }

    public function store(Request $request)
    {
        try {
            $lockKey = 'lock_asiento_' . $request->user()->id . '_' . md5($request->getContent());

            if (!Cache::add($lockKey, true, 3)) {
                throw ValidationException::withMessages(['general' => 'Petición en proceso. Por favor, espere.']);
            }

            $datosValidados = $request->validate([
                'fecha' => 'required|date',
                'glosa' => 'required|string|max:255',
                'tipo_asiento' => 'nullable|string',
                'origen_modulo' => 'nullable|string|in:manual',
                'origen_id' => 'nullable|integer',
                'detalles' => 'required|array|min:2',
                'detalles.*.centro_costo_id' => 'nullable|integer',
                'detalles.*.empleado_nombre' => 'nullable|string',
                'detalles.*.cuenta_contable' => 'required|string',
                'detalles.*.debe' => 'required|numeric|min:0|max:99999999999999',
                'detalles.*.haber' => 'required|numeric|min:0|max:99999999999999',
                'detalles.*.tipo_operacion' => 'nullable|string|in:DEBE,HABER',
                'detalles.*.glosa_detalle' => 'nullable|string|max:255'
            ]);

            foreach ($datosValidados['detalles'] as $detalle) {
                if ((float) $detalle['debe'] == 0 && (float) $detalle['haber'] == 0) {
                    throw ValidationException::withMessages(['detalles' => 'El debe y el haber no pueden ser 0 al mismo tiempo en una línea.']);
                }
            }

            $cabecera = [
                'empresa_id' => $request->user()->empresa_id,
                'usuario_id' => $request->user()->id,
                'fecha' => $datosValidados['fecha'],
                'glosa' => $datosValidados['glosa'],
                'tipo_asiento' => $datosValidados['tipo_asiento'] ?? 'traspaso',
                'origen_modulo' => $datosValidados['origen_modulo'] ?? 'manual',
                'origen_id' => $datosValidados['origen_id'] ?? null,
                'estado' => 'MAYORIZADO',
            ];

            $asiento = $this->service->registrarAsiento($cabecera, $datosValidados['detalles']);

            return response()->json([
                'success' => true,
                'message' => 'Asiento contable registrado con éxito',
                'data' => $asiento->load('detalles')
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function storeAvanzado(Request $request)
    {
        try {
            $lockKey = 'lock_asiento_avanzado_' . $request->user()->id . '_' . md5($request->getContent());

            if (!Cache::add($lockKey, true, 3)) {
                throw ValidationException::withMessages(['general' => 'Petición en proceso. Por favor, espere.']);
            }

            $datos = $request->validate([
                'fecha' => 'required|date',
                'glosa' => 'required|string|min:3|max:255',
                'detalles' => 'required|array|min:2',
                'detalles.*.cuenta_contable' => 'required|string',
                'detalles.*.glosa_detalle' => 'nullable|string|max:255',
                'detalles.*.centro_costo_id' => 'nullable|integer',
                'detalles.*.empleado_nombre' => 'nullable|string',
                'detalles.*.debe' => 'required|numeric|min:0|max:99999999999999',
                'detalles.*.haber' => 'required|numeric|min:0|max:99999999999999',
                'detalles.*.tipo_operacion' => 'nullable|string|in:DEBE,HABER'
            ]);

            foreach ($datos['detalles'] as $detalle) {
                if ((float) $detalle['debe'] == 0 && (float) $detalle['haber'] == 0) {
                    throw ValidationException::withMessages(['detalles' => 'El debe y el haber no pueden ser 0 al mismo tiempo en una línea.']);
                }
            }

            $cabecera = [
                'empresa_id' => $request->user()->empresa_id,
                'usuario_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'glosa' => $datos['glosa'],
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => 'contabilidad',
                'estado' => 'MAYORIZADO',
            ];

            $asiento = $this->service->registrarAsiento($cabecera, $datos['detalles']);

            return response()->json([
                'success' => true,
                'message' => 'Asiento contable registrado exitosamente.',
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
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $asiento = $this->service->obtenerAsientoPorId($request->user()->empresa_id, $id);
            return response()->json([
                'success' => true,
                'data' => [
                    'cabecera' => $asiento,
                    'detalles' => $asiento->detalles->map(function ($d) {
                        return [
                            'cuenta_contable' => $d->cuenta_contable,
                            'cuenta_nombre' => $d->cuenta->nombre ?? 'Sin nombre',
                            'debe' => $d->debe,
                            'haber' => $d->haber,
                        ];
                    })
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'El asiento contable no existe o no pertenece a tu empresa.'
            ], 404);
        }
    }

    public function reversar(Request $request, $id)
    {
        try {
            $request->validate([
                'fecha_reversa' => 'required|date',
                'motivo' => 'required|string|min:3'
            ]);

            $asiento = \App\Domains\Contabilidad\Models\AsientoContable::where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
            if ($request->fecha_reversa < $asiento->fecha->format('Y-m-d')) {
                throw ValidationException::withMessages(['fecha_reversa' => 'No puedes reversar con una fecha anterior.']); // TODO 8 resuelto
            }

            return response()->json(['success' => true, 'message' => 'Asiento reversado.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Errores de validación', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}