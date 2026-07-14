<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Jobs\GenerarReporteContableJob;
use App\Domains\Contabilidad\Models\ReporteContableSolicitado;
use App\Domains\Contabilidad\Services\ReporteContableService;
use App\Support\MensajeErrorGenerico;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;

class ReporteController
{
    /** Tope de rango para el export asincrono por correo (10 anios); el limite de 366 dias de arriba solo aplica a la vista en pantalla. */
    private const MAX_DIAS_EXPORTACION = 3653;

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
                $reporte = $this->service->generarLibroMayor($request->user()->empresa_activa_id, $cuenta, $desde, $hasta, $filtro);
            } else {
                $reporte = $this->service->generarLibroDiario($request->user()->empresa_activa_id, $desde, $hasta, $filtro, $search);
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
                'message' => MensajeErrorGenerico::desde($e)
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
                $request->user()->empresa_activa_id,
                $request->cuenta_contable,
                $request->fecha_inicio,
                $request->fecha_fin
            );

            return response()->json(['success' => true, 'data' => $reporte]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Errores de validación', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 422);
        }
    }

    public function balanceComprobacion(Request $request)
    {
        try {
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
                'filtro'       => 'nullable|integer',
            ]);

            $fechaInicio = $request->query('fecha_inicio');
            $fechaFin    = $request->query('fecha_fin');
            $filtro      = (int) $request->query('filtro', 1);
            $empresaId   = $request->user()->empresa_activa_id;

            $resultado = $this->service->generarBalanceComprobacion($empresaId, $fechaInicio, $fechaFin, $filtro);

            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros obligatorios o son inválidos',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 400);
        }
    }

    /** Encola la generacion de un Libro Diario/Mayor para un rango arbitrario (hasta 10 anios) y lo envia por correo, en vez de bloquear el request. */
    public function solicitarExportacion(Request $request)
    {
        try {
            $request->validate([
                'tipo_reporte' => 'required|in:libro_diario,libro_mayor',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'filtro' => 'nullable|integer|in:0,1,2',
                'cuenta_contable' => 'required_if:tipo_reporte,libro_mayor|nullable|string',
                'email' => 'nullable|email',
            ]);

            $desdeCarbon = Carbon::parse($request->fecha_inicio);
            $hastaCarbon = Carbon::parse($request->fecha_fin);

            if ($desdeCarbon->diffInDays($hastaCarbon) > self::MAX_DIAS_EXPORTACION) {
                throw ValidationException::withMessages([
                    'fecha_fin' => 'El rango de exportación no puede superar 10 años.',
                ]);
            }

            $solicitud = ReporteContableSolicitado::create([
                'empresa_id' => $request->user()->empresa_activa_id,
                'usuario_id' => $request->user()->id,
                'tipo_reporte' => $request->tipo_reporte,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'filtro' => (int) $request->input('filtro', 1),
                'cuenta_contable' => $request->cuenta_contable,
                'email_destino' => $request->input('email') ?: $request->user()->email,
                'estado' => ReporteContableSolicitado::ESTADO_PENDIENTE,
            ]);

            GenerarReporteContableJob::dispatch($solicitud->id);

            return response()->json([
                'success' => true,
                'message' => 'Tu reporte se está generando, te llegará por correo en unos minutos.',
                'data' => ['id' => $solicitud->id, 'estado' => $solicitud->estado],
            ], 202);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros obligatorios o son inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 400);
        }
    }

    public function estadoExportacion(Request $request, int $id)
    {
        $solicitud = ReporteContableSolicitado::where('usuario_id', $request->user()->id)->find($id);

        if ($solicitud === null) {
            return response()->json(['success' => false, 'message' => 'Solicitud no encontrada.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $solicitud->id,
                'estado' => $solicitud->estado,
                'error_mensaje' => $solicitud->error_mensaje,
                'enviado_at' => $solicitud->enviado_at,
            ],
        ]);
    }

    /** Lista las ultimas solicitudes de export de la empresa activa, para mostrar historial en la vista. */
    public function historialExportaciones(Request $request)
    {
        $solicitudes = ReporteContableSolicitado::where('empresa_id', $request->user()->empresa_activa_id)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $solicitudes]);
    }
}