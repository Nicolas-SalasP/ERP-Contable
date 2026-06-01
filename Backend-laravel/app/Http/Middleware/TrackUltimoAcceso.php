<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackUltimoAcceso
{
    private const THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $user = $request->user();

            if ($user !== null) {
                $ultimoAcceso = $user->ultimo_acceso;

                $debeActualizar = $ultimoAcceso === null
                    || Carbon::parse($ultimoAcceso)->lt(now()->subMinutes(self::THROTTLE_MINUTES));

                if ($debeActualizar) {
                    $user->forceFill(['ultimo_acceso' => now()])->saveQuietly();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TrackUltimoAcceso: no se pudo actualizar ultimo_acceso', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
