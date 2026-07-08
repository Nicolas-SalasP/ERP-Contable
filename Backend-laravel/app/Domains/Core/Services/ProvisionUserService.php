<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProvisionUserService
{
    public function provision(array $payload): User
    {
        return DB::transaction(function () use ($payload) {
            $rol = Rol::where('nombre', $payload['rol_erp'] ?? 'Administrador')->first()
                ?? Rol::where('nombre', 'Administrador')->firstOrFail();

            $estadoActiva = EstadoSuscripcion::where('nombre', 'Activa')->firstOrFail();

            // Match por tenri_user_id primero (identidad estable); si el email ya es de otra identidad Tenri, no se sobreescribe (evita secuestrar cuenta ajena).
            $tenriUserId = $payload['tenri_user_id'] ?? null;
            $user = $tenriUserId ? User::where('tenri_user_id', $tenriUserId)->first() : null;

            if (!$user) {
                $existentePorEmail = User::where('email', $payload['email'])->first();

                if ($existentePorEmail) {
                    if ($existentePorEmail->tenri_user_id !== null
                        && (int) $existentePorEmail->tenri_user_id !== (int) $tenriUserId) {
                        throw new RuntimeException(
                            "El email {$payload['email']} ya esta asociado a otra cuenta Tenri."
                        );
                    }
                    $user = $existentePorEmail;
                }
            }

            $attributes = [
                'tenri_user_id' => $payload['tenri_user_id'],
                'email' => $payload['email'],
                'nombre' => $payload['name'],
                'rol_id' => $rol->id,
                'estado_suscripcion_id' => $estadoActiva->id,
                'plan_slug' => $payload['plan_slug'],
                'module_keys' => $payload['module_keys'] ?? [],
                'subscription_status' => $payload['subscription_status'] ?? 'active',
                'subscription_ends_at' => $payload['subscription_ends_at'] ?? null,
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

            // Sin esto, un usuario Tenri ya asociado a una empresa (onboarding previo) nunca
            // aparece en empresa_user si esa fila no se creó en su momento -- lo deja invisible
            // para EmpresaCambioController::misEmpresas/cambiar.
            if ($user->empresa_id !== null) {
                DB::table('empresa_user')->insertOrIgnore([
                    'user_id'    => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'rol_id'     => $user->rol_id,
                    'created_at' => now(),
                ]);
            }

            return $user->fresh();
        });
    }
}
