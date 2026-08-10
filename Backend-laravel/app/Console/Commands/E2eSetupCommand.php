<?php

namespace App\Console\Commands;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\User;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class E2eSetupCommand extends Command
{
    protected $signature = 'tenri:e2e-setup';

    protected $description = 'Crea/actualiza usuario e2e y datos base (cliente, cuenta bancaria) para tests Playwright (solo entornos no-producción)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Este comando no puede ejecutarse en producción.');

            return self::FAILURE;
        }

        $empresaId = DB::table('empresas')->value('id');
        if (! $empresaId) {
            $this->error('No hay empresas en la BD. Ejecuta db:seed primero.');

            return self::FAILURE;
        }

        // No asumir rol_id=1: en un entorno de test con RefreshDatabase transaccional sobre
        // MySQL, el auto_increment de "roles" no se resetea entre tests (InnoDB no revierte el
        // contador en rollback), asi que el id real de "Super Admin" varia segun el orden de
        // ejecucion. Buscar por nombre es correcto en cualquier entorno (test o real).
        $rolSuperAdminId = DB::table('roles')->where('nombre', 'Super Admin')->value('id');
        if (! $rolSuperAdminId) {
            $this->error('No existe el rol "Super Admin". Ejecuta db:seed primero.');

            return self::FAILURE;
        }

        // Mismo motivo que rol_id: no asumir estado_suscripcion_id=1.
        $estadoSuscripcionActivaId = DB::table('estados_suscripcion')->where('nombre', 'Activa')->value('id');
        if (! $estadoSuscripcionActivaId) {
            $this->error('No existe el estado de suscripcion "Activa". Ejecuta db:seed primero.');

            return self::FAILURE;
        }

        $email = 'e2e_runner@tenri.cl';
        $password = 'E2ePassword_2026';

        User::updateOrCreate(
            ['email' => $email],
            [
                'nombre' => 'E2E Test Runner',
                'password' => Hash::make($password),
                'empresa_id' => $empresaId,
                'rol_id' => $rolSuperAdminId,
                'estado_suscripcion_id' => $estadoSuscripcionActivaId,
            ]
        );

        // rut esta cifrado con CipherSweet -- buscar por razon_social (no cifrado) para evitar
        // comparar texto plano contra el valor cifrado almacenado.
        $cliente = Cliente::firstOrCreate(
            ['razon_social' => 'Cliente E2E de Prueba', 'empresa_id' => $empresaId],
            [
                'rut' => '11111111-1',
                'contacto_email' => 'cliente-e2e@tenri.cl',
                'estado' => 'ACTIVO',
            ]
        );

        $planCuentaBanco = PlanCuenta::firstOrCreate(
            ['empresa_id' => $empresaId, 'codigo' => '1101'],
            ['nombre' => 'Banco E2E', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true, 'activo' => true]
        );

        // numero_cuenta usa el cast "encrypted" de Laravel (no deterministico): comparar en el
        // WHERE de firstOrCreate contra el valor cifrado real nunca matchea, asi que se busca
        // por "banco" (no cifrado) para mantener el comando idempotente.
        $cuentaBancaria = CuentaBancariaEmpresa::firstOrCreate(
            ['empresa_id' => $empresaId, 'banco' => 'Banco E2E'],
            [
                'numero_cuenta' => '999-000-E2E',
                'tipo_cuenta' => 'CORRIENTE',
                'cuenta_contable' => $planCuentaBanco->codigo,
                'titular' => 'Empresa E2E',
                'rut_titular' => '76000000-0',
            ]
        );

        $this->info("Usuario e2e listo: {$email} / {$password} (empresa_id={$empresaId})");
        $this->info("Cliente e2e listo: {$cliente->razon_social} (id={$cliente->id})");
        $this->info("Cuenta bancaria e2e lista: {$cuentaBancaria->banco} (id={$cuentaBancaria->id})");

        return self::SUCCESS;
    }
}
