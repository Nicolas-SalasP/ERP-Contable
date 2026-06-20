<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\Pais;

class PaisSeeder extends Seeder
{
    public function run(): void
    {
        $paises = [
            ['iso' => 'CL', 'nombre' => 'Chile',          'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT'],
            ['iso' => 'US', 'nombre' => 'Estados Unidos',  'moneda_defecto' => 'USD', 'etiqueta_id' => 'EIN'],
            ['iso' => 'AR', 'nombre' => 'Argentina',       'moneda_defecto' => 'ARS', 'etiqueta_id' => 'CUIT'],
            ['iso' => 'PE', 'nombre' => 'Peru',            'moneda_defecto' => 'PEN', 'etiqueta_id' => 'RUC'],
            ['iso' => 'CO', 'nombre' => 'Colombia',        'moneda_defecto' => 'COP', 'etiqueta_id' => 'NIT'],
            ['iso' => 'MX', 'nombre' => 'Mexico',          'moneda_defecto' => 'MXN', 'etiqueta_id' => 'RFC'],
            ['iso' => 'BR', 'nombre' => 'Brasil',          'moneda_defecto' => 'BRL', 'etiqueta_id' => 'CNPJ'],
            ['iso' => 'UY', 'nombre' => 'Uruguay',         'moneda_defecto' => 'UYU', 'etiqueta_id' => 'RUT'],
            ['iso' => 'PY', 'nombre' => 'Paraguay',        'moneda_defecto' => 'PYG', 'etiqueta_id' => 'RUC'],
            ['iso' => 'BO', 'nombre' => 'Bolivia',         'moneda_defecto' => 'BOB', 'etiqueta_id' => 'NIT'],
            ['iso' => 'EC', 'nombre' => 'Ecuador',         'moneda_defecto' => 'USD', 'etiqueta_id' => 'RUC'],
            ['iso' => 'VE', 'nombre' => 'Venezuela',       'moneda_defecto' => 'VES', 'etiqueta_id' => 'RIF'],
            ['iso' => 'ES', 'nombre' => 'España',          'moneda_defecto' => 'EUR', 'etiqueta_id' => 'NIF'],
            ['iso' => 'DE', 'nombre' => 'Alemania',        'moneda_defecto' => 'EUR', 'etiqueta_id' => 'USt-IdNr'],
            ['iso' => 'FR', 'nombre' => 'Francia',         'moneda_defecto' => 'EUR', 'etiqueta_id' => 'SIREN'],
            ['iso' => 'GB', 'nombre' => 'Reino Unido',     'moneda_defecto' => 'GBP', 'etiqueta_id' => 'VAT'],
            ['iso' => 'IT', 'nombre' => 'Italia',          'moneda_defecto' => 'EUR', 'etiqueta_id' => 'P.IVA'],
            ['iso' => 'PT', 'nombre' => 'Portugal',        'moneda_defecto' => 'EUR', 'etiqueta_id' => 'NIF'],
            ['iso' => 'NL', 'nombre' => 'Paises Bajos',    'moneda_defecto' => 'EUR', 'etiqueta_id' => 'BTW'],
            ['iso' => 'IE', 'nombre' => 'Irlanda',         'moneda_defecto' => 'EUR', 'etiqueta_id' => 'VAT'],
            ['iso' => 'CA', 'nombre' => 'Canada',          'moneda_defecto' => 'CAD', 'etiqueta_id' => 'BN'],
            ['iso' => 'CN', 'nombre' => 'China',           'moneda_defecto' => 'CNY', 'etiqueta_id' => 'USCI'],
            ['iso' => 'JP', 'nombre' => 'Japon',           'moneda_defecto' => 'JPY', 'etiqueta_id' => 'Tax ID'],
            ['iso' => 'KR', 'nombre' => 'Corea del Sur',   'moneda_defecto' => 'KRW', 'etiqueta_id' => 'BRN'],
            ['iso' => 'IN', 'nombre' => 'India',           'moneda_defecto' => 'INR', 'etiqueta_id' => 'GSTIN'],
            ['iso' => 'AU', 'nombre' => 'Australia',       'moneda_defecto' => 'AUD', 'etiqueta_id' => 'ABN'],
            ['iso' => 'CH', 'nombre' => 'Suiza',           'moneda_defecto' => 'CHF', 'etiqueta_id' => 'UID'],
            ['iso' => 'SE', 'nombre' => 'Suecia',          'moneda_defecto' => 'SEK', 'etiqueta_id' => 'VAT'],
            ['iso' => 'SG', 'nombre' => 'Singapur',        'moneda_defecto' => 'SGD', 'etiqueta_id' => 'UEN'],
            ['iso' => 'IL', 'nombre' => 'Israel',          'moneda_defecto' => 'ILS', 'etiqueta_id' => 'Tax ID'],
        ];

        foreach ($paises as $p) {
            Pais::firstOrCreate(
                ['iso' => $p['iso']],
                [
                    'nombre'         => $p['nombre'],
                    'moneda_defecto' => $p['moneda_defecto'],
                    'etiqueta_id'    => $p['etiqueta_id'],
                    'activo'         => true,
                ]
            );
        }
    }
}
