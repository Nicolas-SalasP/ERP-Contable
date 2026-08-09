<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Margen fijo (no por categoria: el ERP no tiene categoria estructurada de producto)
            // que PricingService usa para sugerir precio de venta a partir del costo de reposicion.
            $table->decimal('margen_venta_pct', 5, 2)->default(30.00)->after('ppm_pct');
            // Piso dinamico: el precio sugerido nunca baja de costo * (1 + margen_minimo_pct).
            $table->decimal('margen_minimo_pct', 5, 2)->default(5.00)->after('margen_venta_pct');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['margen_venta_pct', 'margen_minimo_pct']);
        });
    }
};
