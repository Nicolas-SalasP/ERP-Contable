<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Models\IncidenteSeguridad;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 6 — Registro de incidentes de seguridad y brechas de datos personales.
 *
 * Ley 21.663: alerta temprana 3h / reporte 72h al CSIRT.
 * Ley 21.719: notificación a la Agencia de Protección de Datos + titulares.
 *
 * Todos los endpoints requieren permiso:usuarios.gestionar (administrador >= 80).
 * El aislamiento multitenant se garantiza vía EmpresaScope sobre el modelo.
 */
class IncidenteSeguridadController extends Controller
{
    /**
     * GET /api/incidentes
     * Lista paginada de incidentes de la empresa del solicitante, más recientes primero.
     * Filtros opcionales: severidad, estado.
     */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'severidad' => 'nullable|string|in:' . implode(',', IncidenteSeguridad::SEVERIDADES),
            'estado'    => 'nullable|string|in:' . implode(',', IncidenteSeguridad::ESTADOS),
        ]);

        $query = IncidenteSeguridad::query()->orderByDesc('detectado_at');

        if (!empty($datos['severidad'])) {
            $query->where('severidad', $datos['severidad']);
        }

        if (!empty($datos['estado'])) {
            $query->where('estado', $datos['estado']);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(25),
        ]);
    }

    /**
     * POST /api/incidentes
     * Crea un nuevo incidente de seguridad para la empresa del solicitante.
     * registrado_por se fija desde el nombre del usuario autenticado.
     * empresa_id se fija desde la empresa del usuario autenticado.
     * estado inicial: ABIERTO.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo'                     => 'required|string|max:255',
            'descripcion'                => 'required|string',
            'severidad'                  => 'required|string|in:' . implode(',', IncidenteSeguridad::SEVERIDADES),
            'detectado_at'               => 'required|date',
            'origen'                     => 'nullable|string|max:255',
            'categorias_datos_afectados' => 'nullable|string',
            'n_afectados_estimado'       => 'nullable|integer|min:0',
        ]);

        $user = $request->user();

        $incidente = IncidenteSeguridad::create(array_merge($datos, [
            'empresa_id'    => $user->empresa_id,
            'registrado_por' => $user->nombre,
            'estado'        => IncidenteSeguridad::ESTADO_ABIERTO,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $incidente,
        ], 201);
    }

    /**
     * PUT /api/incidentes/{id}
     * Actualiza la línea de tiempo legal del incidente y/o el estado.
     * Solo se pueden actualizar incidentes de la empresa del solicitante (EmpresaScope lo garantiza).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'alerta_temprana_at'         => 'nullable|date',
            'reporte_csirt_at'           => 'nullable|date',
            'notificacion_agencia_at'    => 'nullable|date',
            'notificacion_afectados_at'  => 'nullable|date',
            'estado'                     => 'nullable|string|in:' . implode(',', IncidenteSeguridad::ESTADOS),
        ]);

        // EmpresaScope garantiza aislamiento multitenant: si el incidente
        // pertenece a otra empresa, findOrFail lanza 404.
        $incidente = IncidenteSeguridad::findOrFail($id);

        $incidente->update(array_filter($datos, fn ($v) => $v !== null));

        return response()->json([
            'success' => true,
            'data'    => $incidente->fresh(),
        ]);
    }
}
