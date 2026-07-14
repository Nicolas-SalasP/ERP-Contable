<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Core\Models\User;
use App\Support\MensajeErrorGenerico;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Log;

class AsientoContableController
{
    protected $service;

    public function __construct(AsientoContableService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return $this->service->obtenerAsientosPaginados($request->user()->empresa_activa_id);
    }

    public function store(Request $request)
    {
        try {
            $lockKey = 'lock_asiento_'.$request->user()->id.'_'.md5($request->getContent());

            if (! Cache::add($lockKey, true, 3)) {
                throw ValidationException::withMessages(['general' => 'Petición en proceso. Por favor, espere.']);
            }

            $datosValidados = $request->validate([
                'fecha' => 'required|date',
                'glosa' => 'required|string|max:255',
                // Debe calzar con el enum real de la columna (ingreso/egreso/traspaso/''); un valor
                // fuera de ese set pasaba la validacion como string libre y explotaba como
                // QueryException/500 en MySQL en vez de un 422 de validacion claro (SQLite, motor
                // de tests por defecto, no aplica esta restriccion — encontrado corriendo contra
                // MySQL real).
                'tipo_asiento' => 'nullable|string|in:ingreso,egreso,traspaso',
                'origen_modulo' => 'nullable|string|in:manual',
                'origen_id' => 'nullable|integer',
                'detalles' => 'required|array|min:2',
                'detalles.*.centro_costo_id' => 'nullable|integer',
                'detalles.*.empleado_nombre' => 'nullable|string',
                'detalles.*.cuenta_contable' => 'required|string',
                'detalles.*.debe' => 'required|numeric|min:0|max:99999999999999',
                'detalles.*.haber' => 'required|numeric|min:0|max:99999999999999',
                'detalles.*.tipo_operacion' => 'nullable|string|in:DEBE,HABER',
                'detalles.*.glosa_detalle' => 'nullable|string|max:255',
            ]);

            foreach ($datosValidados['detalles'] as $detalle) {
                if ((float) $detalle['debe'] == 0 && (float) $detalle['haber'] == 0) {
                    throw ValidationException::withMessages(['detalles' => 'El debe y el haber no pueden ser 0 al mismo tiempo en una línea.']);
                }
            }

            $cabecera = [
                'empresa_id' => $request->user()->empresa_activa_id,
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
                'data' => $asiento->load('detalles'),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 422);
        }
    }

    public function storeAvanzado(Request $request)
    {
        try {
            $lockKey = 'lock_asiento_avanzado_'.$request->user()->id.'_'.md5($request->getContent());

            if (! Cache::add($lockKey, true, 3)) {
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
                'detalles.*.tipo_operacion' => 'nullable|string|in:DEBE,HABER',
            ]);

            foreach ($datos['detalles'] as $detalle) {
                if ((float) $detalle['debe'] == 0 && (float) $detalle['haber'] == 0) {
                    throw ValidationException::withMessages(['detalles' => 'El debe y el haber no pueden ser 0 al mismo tiempo en una línea.']);
                }
            }

            $cabecera = [
                'empresa_id' => $request->user()->empresa_activa_id,
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
                'data' => $asiento,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (QueryException $e) {
            Log::error('Error de BD en storeAvanzado: '.$e->getMessage(), [
                'sql_state' => $e->errorInfo[0] ?? null,
                'code' => $e->getCode(),
            ]);

            $sqlState = $e->errorInfo[0] ?? null;
            if ($sqlState === '42S02') {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de configuracion del sistema: faltan migraciones por aplicar en la base de datos. Contacta al administrador para que ejecute "php artisan migrate".',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error tecnico al guardar el asiento. Si persiste, contacta soporte.',
            ], 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 422);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            /** @var User $usuario */
            $usuario = $request->user();

            $asiento = $this->service->obtenerAsientoPorId($usuario->empresa_activa_id, (int) $id);

            $detalles = $asiento->detalles->map(function ($d) {
                return [
                    'id' => $d->id,
                    'cuenta_contable' => $d->cuenta_contable,
                    'cuenta_nombre' => $d->cuenta->nombre ?? 'Sin nombre',
                    'descripcion' => $d->descripcion_extensa ?? '',
                    'debe' => $d->debe,
                    'haber' => $d->haber,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'cabecera' => $asiento,
                    'detalles' => $detalles,
                    'total_debe' => $detalles->sum(fn ($d) => (float) $d['debe']),
                    'total_haber' => $detalles->sum(fn ($d) => (float) $d['haber']),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'El asiento contable no existe o no pertenece a tu empresa.',
            ], 404);
        }
    }

    public function reversar(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'fecha_reversa' => 'required|date',
                'motivo' => 'required|string|min:3',
            ]);

            $nuevoAsiento = $this->service->reversarAsientoPorId(
                $request->user()->empresa_activa_id,
                $request->user()->id,
                (int) $id,
                $datos['fecha_reversa'],
                $datos['motivo']
            );

            return response()->json([
                'success' => true,
                'message' => 'Asiento reversado exitosamente.',
                'data' => $nuevoAsiento,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 422);
        }
    }
}
