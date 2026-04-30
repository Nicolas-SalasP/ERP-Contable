<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\Empresa;
use App\Observers\EmpresaObserver;
use App\Domains\Contabilidad\Models\PlanCuenta;

class SincronizarCuentasSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = Empresa::all();
        $observer = new EmpresaObserver();

        foreach ($empresas as $empresa) {
            $tieneCuentas = PlanCuenta::where('empresa_id', $empresa->id)->exists();

            if (!$tieneCuentas) {
                $observer->created($empresa);
                $this->command->info("Plan de Cuentas clonado exitosamente para la empresa ID: {$empresa->id}");
            } else {
                $this->command->info("La empresa ID: {$empresa->id} ya tiene su Plan de Cuentas. Omitiendo...");
            }
        }
    }
}