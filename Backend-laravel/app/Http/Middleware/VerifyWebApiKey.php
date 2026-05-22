<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-WEB-API-KEY');
        $expected = env('WEB_INTEGRATION_KEY');

        if (!is_string($expected) || $expected === '' || $apiKey !== $expected) {
            return response()->json(['error' => 'No autorizado por la web de Tenri'], 401);
        }

        return $next($request);
    }
}
