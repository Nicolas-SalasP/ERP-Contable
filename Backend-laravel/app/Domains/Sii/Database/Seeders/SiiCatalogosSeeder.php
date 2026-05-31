<?php

namespace App\Domains\Sii\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orquestador de catalogos SII.
 *
 * NO se registra automaticamente en database/seeders/DatabaseSeeder.php.
 * Se invoca manualmente:
 *   php artisan db:seed --class="App\\Domains\\Sii\\Database\\Seeders\\SiiCatalogosSeeder"
 */
class SiiCatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SiiCatFormaPagoSeeder::class,
            SiiCatImpuestosSeeder::class,
            SiiCatUnidadesSeeder::class,
            SiiCatComunasSeeder::class,
            SiiCatActecoSeeder::class,
        ]);
    }
}
