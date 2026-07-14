<?php

namespace App\Domains\Alertas\Controllers;

use App\Domains\Alertas\Models\Alerta;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bandeja de alertas de la empresa activa (EmpresaScope filtra automaticamente); requiere permiso alertas.ver / alertas.gestionar. */
class AlertaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => 'nullable|string',
            'nivel' => 'nullable|string|in:info,advertencia,critico',
            'estado' => 'nullable|string|in:pendiente,enviada,resuelta,descartada',
        ]);

        $query = Alerta::query()->orderByDesc('created_at');

        if (! empty($datos['tipo'])) {
            $query->where('tipo', $datos['tipo']);
        }

        if (! empty($datos['nivel'])) {
            $query->where('nivel', $datos['nivel']);
        }

        if (! empty($datos['estado'])) {
            $query->where('estado', $datos['estado']);
        } else {
            // Sin filtro explicito, la bandeja por defecto excluye lo ya resuelto/descartado.
            $query->pendientes();
        }

        $alertas = $query->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $alertas,
        ]);
    }

    public function resolver(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'estado' => 'required|string|in:resuelta,descartada',
        ]);

        // EmpresaScope aplica sobre findOrFail: una empresa nunca puede resolver una alerta ajena.
        $alerta = Alerta::query()->findOrFail($id);

        $alerta->update([
            'estado' => $datos['estado'],
            'resuelta_at' => now(),
            'resuelta_por' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $alerta,
        ]);
    }
}
