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
        }

        return response()->json(['error' => 'No autorizado por la web de Tenri'], 401);
    }
}
