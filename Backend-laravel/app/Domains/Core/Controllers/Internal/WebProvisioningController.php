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

            return response()->json([
                'success' => false,
                'message' => 'No se pudo provisionar el usuario.',
            ], 500);
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

    public function syncPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenri_user_id' => ['required', 'integer'],
            'password_hash' => ['required', 'string'],
        ]);

        $updated = DB::table('usuarios')
            ->where('tenri_user_id', $data['tenri_user_id'])
            ->exists();

        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        DB::table('usuarios')
            ->where('tenri_user_id', $data['tenri_user_id'])
            ->update([
                'password' => $data['password_hash'],
                'tenri_synced_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }
}
