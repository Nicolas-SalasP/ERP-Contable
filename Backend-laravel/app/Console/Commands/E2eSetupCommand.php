<?php

namespace App\Console\Commands;

use App\Domains\Core\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class E2eSetupCommand extends Command
{
    protected $signature   = 'tenri:e2e-setup';
    protected $description = 'Crea/actualiza usuario e2e con credenciales fijas para tests Playwright (solo entornos no-producción)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Este comando no puede ejecutarse en producción.');
            return self::FAILURE;
        }

        $empresaId = DB::table('empresas')->value('id');
        if (!$empresaId) {
            $this->error('No hay empresas en la BD. Ejecuta db:seed primero.');
            return self::FAILURE;
        }

        $email    = 'e2e_runner@tenri.cl';
        $password = 'E2ePassword_2026';

        User::updateOrCreate(
            ['email' => $email],
            [
                'nombre'               => 'E2E Test Runner',
                'password'             => Hash::make($password),
                'empresa_id'           => $empresaId,
                'rol_id'               => 1,
                'estado_suscripcion_id' => 1,
            ]
        );

        $this->info("Usuario e2e listo: {$email} / {$password} (empresa_id={$empresaId})");
        return self::SUCCESS;
    }
}
