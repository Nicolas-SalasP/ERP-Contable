<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PaisSeeder::class,
            RolSeeder::class,
            EstadoSuscripcionSeeder::class,
            EstadoCotizacionSeeder::class,
            CatalogoPlanMaestroSeeder::class,
            EmpresaSeeder::class,
            CentroCostoSeeder::class,
            UserSeeder::class,
            SincronizarCuentasSeeder::class,
            TestDataSeeder::class,
        ]);
    }
}