<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('estados_cotizacion')->where('nombre', 'Facturada')->exists()) {
            DB::table('estados_cotizacion')->insert([
                'nombre'      => 'Facturada',
                'descripcion' => 'Convertida en factura de venta',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('estados_cotizacion')->where('nombre', 'Facturada')->delete();
    }
};
