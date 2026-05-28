<?php

namespace App\Domains\Core\Controllers\Internal;

use App\Domains\Core\Services\ProvisionUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebProvisioningController
{
    public function __construct(
        private readonly ProvisionUserService $provisionService,
    ) {
    }

    public function provisionUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenri_user_id' => ['required', 'integer'],
            'email' => ['required', 'email'],
            'name' => ['required', 'string'],
            'rut' => ['nullable', 'string'],
            'password_hash' => ['required', 'string'],
            'plan_slug' => ['required', 'string'],
            'module_keys' => ['required', 'array'],
            'rol_erp' => ['required', 'string'],
        ]);

        try {
            $user = $this->provisionService->provision($data);
        } catch (Throwable $e) {
            Log::error('WebProvisioning: error al provisionar usuario', [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'No se pudo provisionar el usuario.'], 500);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'tenri_user_id' => $user->tenri_user_id,
                'plan_slug' => $user->plan_slug,
                'tenri_synced_at' => optional($user->tenri_synced_at)->toIso8601String(),
            ],
        ], 201);
    }

    public function syncPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_slug' => ['required', 'string'],
            'module_keys' => ['required', 'array'],
        ]);

        try {
            $updated = DB::table('usuarios')
                ->where('plan_slug', $data['plan_slug'])
                ->update([
                    'module_keys' => json_encode($data['module_keys']),
                    'tenri_synced_at' => now(),
                ]);

            Log::info('WebProvisioning: plan sincronizado masivamente', [
                'plan_slug' => $data['plan_slug'],
                'usuarios_updated' => $updated,
                'module_count' => count($data['module_keys']),
            ]);

            return response()->json([
                'success' => true,
                'plan_slug' => $data['plan_slug'],
                'usuarios_updated' => $updated,
            ]);
        } catch (Throwable $e) {
            Log::error('WebProvisioning: error en sync-plan', [
                'error' => $e->getMessage(),
                'plan_slug' => $data['plan_slug'],
            ]);
            return response()->json(['success' => false, 'message' => 'Error al sincronizar plan.'], 500);
        }
    }

    public function onlineUsers(Request $request): JsonResponse
    {
        $threshold = now()->subMinutes(30);

        $allUsers = DB::table('usuarios as u')
            ->leftJoin('empresas as e', 'u.empresa_id', '=', 'e.id')
            ->where('u.ultimo_acceso', '>=', $threshold)
            ->select(
                'u.id',
                'u.nombre',
                'u.email',
                'u.plan_slug',
                'u.tenri_user_id',
                'u.ultimo_acceso',
                'e.razon_social as empresa'
            )
            ->orderByDesc('u.ultimo_acceso')
            ->get();

        $paid = $allUsers->whereNotNull('plan_slug')
            ->where('plan_slug', '!=', 'erp-starter')
            ->values();

        $all = $allUsers->values();

        return response()->json([
            'paid' => $paid,
            'all' => $all,
            'threshold' => 30,
            'at' => now()->toIso8601String(),
        ]);
    }
}
