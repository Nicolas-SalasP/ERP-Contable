<?php

namespace App\Http\Middleware;

use App\Domains\Core\Models\User;
use App\Domains\Core\Services\SubscriptionVerifierService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function __construct(
        private readonly SubscriptionVerifierService $verifier,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], 401);
        }

        if (!$this->verifier->isActive($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Su suscripción al ERP no se encuentra activa. Renueva tu plan en tenri.cl para poder seguir usando.',
                'code' => 'SUBSCRIPTION_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
