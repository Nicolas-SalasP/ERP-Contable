<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_productos', function (Blueprint $table) {
            $table->string('codigo_sii_producto', 35)->nullable();
            $table->string('codigo_sii_tipo', 10)->nullable();
            $table->string('unidad_medida_sii', 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_productos', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_sii_producto',
                'codigo_sii_tipo',
                'unidad_medida_sii',
            ]);
        });
    }
};
