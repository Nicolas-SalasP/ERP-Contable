<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Actualiza usuarios.ultimo_acceso para reflejar actividad real del usuario,
 * no solo el momento del login.
 *
 * - Solo escribe si pasaron mas de 5 minutos desde el ultimo registro (throttle),
 *   para evitar un UPDATE en cada request.
 * - Todo el bloque va envuelto en try/catch: si el update falla, nunca rompe la
 *   request; solo deja un warning en el log y continua.
 * - La respuesta se resuelve primero ($next) y la actualizacion ocurre despues,
 *   de modo que el tracking jamas altere ni bloquee la respuesta al cliente.
 */
class TrackUltimoAcceso
{
    /**
     * Ventana de throttle en minutos.
     */
    private const THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        // Resolvemos la respuesta primero; el tracking nunca debe afectarla.
        $response = $next($request);

        try {
            $user = $request->user();

            if ($user !== null) {
                $ultimoAcceso = $user->ultimo_acceso;

                $debeActualizar = $ultimoAcceso === null
                    || Carbon::parse($ultimoAcceso)->lt(now()->subMinutes(self::THROTTLE_MINUTES));

                if ($debeActualizar) {
                    // updateQuietly + forceFill no son necesarios: no hay UPDATED_AT
                    // (User::UPDATED_AT = null) ni eventos relevantes que rompan nada.
                    $user->forceFill(['ultimo_acceso' => now()])->saveQuietly();
                }
            }
        } catch (\Throwable $e) {
            // Nunca propagamos: el tracking es best-effort.
            Log::warning('TrackUltimoAcceso: no se pudo actualizar ultimo_acceso', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
