<?php

namespace App\Http\Middleware;

use App\Domains\Core\Support\ModuloPermisos;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
     *
     * Los permisos efectivos se cachean 5 minutos por usuario+empresa para evitar
     * una DB query en cada request autenticado. Al cambiar el rol de un usuario
     * la clave se invalida inmediatamente (ver UsuarioController::actualizarRol).
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

        $cacheKey = self::cacheKeyPermisos((int) $usuario->id, (int) ($usuario->empresa_activa_id ?? 0));

        $permisosUsuario = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($usuario) {
            return ModuloPermisos::permisosUsuario($usuario);
        });

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

    /**
     * Genera la clave de cache de permisos para un usuario en una empresa.
     * Formato: permisos_u{userId}_e{empresaActivaId}
     * Expuesto como public static para que los controladores puedan invalidarla.
     */
    public static function cacheKeyPermisos(int $userId, int $empresaActivaId): string
    {
        return "permisos_u{$userId}_e{$empresaActivaId}";
    }
}
