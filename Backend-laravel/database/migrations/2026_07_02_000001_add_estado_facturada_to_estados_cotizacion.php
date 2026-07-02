<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('estado_cotizaciones')->where('nombre', 'Facturada')->exists()) {
            DB::table('estado_cotizaciones')->insert([
                'nombre'      => 'Facturada',
                'descripcion' => 'Convertida en factura de venta',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('estado_cotizaciones')->where('nombre', 'Facturada')->delete();
    }
};
