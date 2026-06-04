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
            // Preferente: firma HMAC (timestamp + nonce + hash del body).
            if (HmacFirma::verifica($expected, $request)) {
                return $next($request);
            }

            // Fase 1 (compat): llave estática legacy, comparación en tiempo constante.
            // Se retira en Fase 2 cuando ambos lados firmen con HMAC.
            $legacy = (string) $request->header('X-WEB-API-KEY', '');
            if ($legacy !== '' && hash_equals($expected, $legacy)) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'No autorizado por la web de Tenri'], 401);
    }
}
