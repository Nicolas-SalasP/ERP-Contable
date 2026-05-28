<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use Illuminate\Support\Facades\DB;

class ProvisionUserService
{
    public function provision(array $payload): User
    {
        return DB::transaction(function () use ($payload) {
            $rol = Rol::where('nombre', $payload['rol_erp'] ?? 'Administrador')->first()
                ?? Rol::where('nombre', 'Administrador')->firstOrFail();

            $estadoActiva = EstadoSuscripcion::where('nombre', 'Activa')->firstOrFail();

            $user = User::where('email', $payload['email'])->first()
                ?? User::where('tenri_user_id', $payload['tenri_user_id'])->first();

            $attributes = [
                'tenri_user_id' => $payload['tenri_user_id'],
                'email' => $payload['email'],
                'nombre' => $payload['name'],
                'rol_id' => $rol->id,
                'estado_suscripcion_id' => $estadoActiva->id,
                'plan_slug' => $payload['plan_slug'],
                'module_keys' => $payload['module_keys'] ?? [],
                'tenri_synced_at' => now(),
            ];

            if ($user) {
                $user->forceFill($attributes)->save();
            } else {
                $user = new User();
                $user->forceFill($attributes);
                $user->empresa_id = null;
                $user->password = 'placeholder';
                $user->save();
            }

            DB::table('usuarios')
                ->where('id', $user->id)
                ->update(['password' => $payload['password_hash']]);

            return $user->fresh();
        });
    }
}
