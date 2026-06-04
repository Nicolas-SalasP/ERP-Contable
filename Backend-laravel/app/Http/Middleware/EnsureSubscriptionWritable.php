<?php

namespace App\Http\Middleware;

use App\Domains\Core\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * H9: bloquea escrituras cuando la suscripción es de solo lectura o está vencida.
 *
 * Complementa a CheckSubscription (que verifica actividad contra la web): este guard
 * usa el estado local `subscription_status` y solo afecta a métodos de escritura, de
 * modo que un usuario read_only/expired sigue pudiendo consultar (GET) pero no mutar.
 */
class EnsureSubscriptionWritable
{
    private const ESTADOS_SIN_ESCRITURA = ['read_only', 'expired'];

    private const METODOS_ESCRITURA = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && in_array($request->method(), self::METODOS_ESCRITURA, true)
            && in_array($user->subscription_status, self::ESTADOS_SIN_ESCRITURA, true)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tu suscripción es de solo lectura. Renueva tu plan en tenri.cl para volver a editar.',
                'code' => 'SUBSCRIPTION_READ_ONLY',
            ], 403);
        }

        return $next($request);
    }
}
