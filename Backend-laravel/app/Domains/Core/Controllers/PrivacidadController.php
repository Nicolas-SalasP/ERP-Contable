<?php

namespace App\Domains\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Core\Models\Consentimiento;
use App\Domains\Core\Models\PoliticaPrivacidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacidadController extends Controller
{
    /**
     * GET /api/privacidad/politica
     * Devuelve la política de privacidad activa o 404 si no existe ninguna.
     */
    public function politicaActiva(): JsonResponse
    {
        $politica = PoliticaPrivacidad::where('activa', true)->first();

        if (!$politica) {
            return response()->json([
                'success' => false,
                'message' => 'No hay una política de privacidad activa.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $politica,
        ]);
    }

    /**
     * GET /api/privacidad/mi-consentimiento
     * Indica si el usuario autenticado ha aceptado la versión activa de la política.
     */
    public function miConsentimiento(Request $request): JsonResponse
    {
        $user    = $request->user();
        $politica = PoliticaPrivacidad::where('activa', true)->first();

        if (!$politica) {
            return response()->json([
                'success' => true,
                'data'    => ['aceptada' => false, 'version' => null],
            ]);
        }

        $consentimiento = Consentimiento::where('titular_type', get_class($user))
            ->where('titular_id', $user->id)
            ->where('politica_version', $politica->version)
            ->where('otorgado', true)
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'aceptada' => $consentimiento !== null,
                'version'  => $consentimiento ? $consentimiento->politica_version : null,
            ],
        ]);
    }

    /**
     * POST /api/privacidad/consentimiento
     * Registra la aceptación de la política activa por parte del usuario autenticado.
     * Es idempotente: si ya existe el registro, lo devuelve sin duplicar.
     */
    public function aceptar(Request $request): JsonResponse
    {
        $user    = $request->user();
        $politica = PoliticaPrivacidad::where('activa', true)->first();

        if (!$politica) {
            return response()->json([
                'success' => false,
                'message' => 'No hay una política de privacidad activa.',
            ], 404);
        }

        $existente = Consentimiento::where('titular_type', get_class($user))
            ->where('titular_id', $user->id)
            ->where('politica_version', $politica->version)
            ->first();

        if ($existente && $existente->otorgado) {
            return response()->json([
                'success' => true,
                'data'    => $existente,
            ]);
        }

        if ($existente) {
            // Existía pero había sido revocado; reactiva
            $existente->update([
                'otorgado'    => true,
                'otorgado_at' => now(),
                'revocado_at' => null,
                'ip'          => $request->ip(),
                'user_agent'  => $request->header('User-Agent'),
            ]);
            return response()->json([
                'success' => true,
                'data'    => $existente->fresh(),
            ]);
        }

        $consentimiento = Consentimiento::create([
            'empresa_id'       => $user->empresa_id,
            'titular_type'     => get_class($user),
            'titular_id'       => $user->id,
            'politica_version' => $politica->version,
            'finalidad'        => 'uso_plataforma',
            'base_licitud'     => 'consentimiento',
            'otorgado'         => true,
            'otorgado_at'      => now(),
            'ip'               => $request->ip(),
            'user_agent'       => $request->header('User-Agent'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $consentimiento,
        ]);
    }

    /**
     * DELETE /api/privacidad/consentimiento
     * Revoca el consentimiento del usuario autenticado para la versión activa.
     */
    public function revocar(Request $request): JsonResponse
    {
        $user    = $request->user();
        $politica = PoliticaPrivacidad::where('activa', true)->first();

        if ($politica) {
            Consentimiento::where('titular_type', get_class($user))
                ->where('titular_id', $user->id)
                ->where('politica_version', $politica->version)
                ->where('otorgado', true)
                ->update([
                    'otorgado'    => false,
                    'revocado_at' => now(),
                ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/privacidad/politica  (requiere permiso:usuarios.gestionar)
     * Crea una nueva versión de la política y desactiva la anterior.
     */
    public function crearPolitica(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'version'   => 'required|string|unique:politicas_privacidad,version',
            'titulo'    => 'required|string',
            'contenido' => 'required|string',
        ]);

        // Desactivar la política actual
        PoliticaPrivacidad::where('activa', true)->update(['activa' => false]);

        $politica = PoliticaPrivacidad::create([
            'version'       => $datos['version'],
            'titulo'        => $datos['titulo'],
            'contenido'     => $datos['contenido'],
            'vigente_desde' => now()->toDateString(),
            'activa'        => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $politica,
        ], 201);
    }
}
