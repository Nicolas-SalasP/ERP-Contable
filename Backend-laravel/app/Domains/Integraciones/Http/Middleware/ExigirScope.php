<?php

namespace App\Domains\Integraciones\Http\Middleware;

use App\Domains\Integraciones\Models\IntegracionApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Debe correr DESPUES de AutenticarApiKey (usa 'integracion_key' ya resuelta en el request). Uso: middleware('integracion.scope:inventario:leer'). */
class ExigirScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var IntegracionApiKey|null $key */
        $key = $request->attributes->get('integracion_key');

        if (! $key || ! $key->tieneScope($scope)) {
            return response()->json([
                'success' => false,
                'message' => "La API-key no tiene el scope requerido: {$scope}.",
            ], 403);
        }

        return $next($request);
    }
}
