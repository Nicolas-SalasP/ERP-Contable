<?php

namespace App\Http\Middleware;

use App\Support\HmacFirma;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.tenri_web.web_integration_key');

        if (is_string($expected) && $expected !== '') {
            // Autenticación estándar: firma HMAC (timestamp + nonce + hash del body),
            // con ventana anti-replay. Es el único mecanismo activo por defecto.
            if (HmacFirma::verifica($expected, $request)) {
                return $next($request);
            }

            // Legacy (S-6): llave estática X-WEB-API-KEY. Deshabilitada salvo que se
            // active el flag durante una migración coordinada. Comparación en tiempo
            // constante para evitar timing attacks mientras siga disponible.
            if (config('services.tenri_web.allow_legacy_key')) {
                $legacy = (string) $request->header('X-WEB-API-KEY', '');
                if ($legacy !== '' && hash_equals($expected, $legacy)) {
                    return $next($request);
                }
            }
        }

        return response()->json(['error' => 'No autorizado por la web de Tenri'], 401);
    }
}
