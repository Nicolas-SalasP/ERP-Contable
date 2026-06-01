<?php

namespace App\Http\Middleware;

use App\Domains\Core\Support\ModuloPermisos;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Valida que el usuario autenticado tenga al menos uno de los permisos
     * declarados en la ruta.
     *
     * Uso:
     *   ->middleware('permiso:sii.caf.ver')
     *   ->middleware('permiso:sii.alertas.ver,sii.auditoria.ver')
     */
    public function handle(Request $request, Closure $next, string ...$permisos): Response|JsonResponse
    {
        $usuario = $request->user();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], 401);
        }

        $permisos = ModuloPermisos::normalizarLista($permisos);

        if ($permisos === []) {
            return $next($request);
        }

        $permisosUsuario = ModuloPermisos::permisosUsuario($usuario);

        foreach ($permisos as $permiso) {
            if (in_array($permiso, $permisosUsuario, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'No tienes permisos para ejecutar esta operacion.',
            'errors' => [
                'permiso' => [
                    'Se requiere al menos uno de estos permisos: ' . implode(', ', $permisos),
                ],
            ],
        ], 403);
    }
}
