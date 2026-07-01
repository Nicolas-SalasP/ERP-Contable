<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Produccion fue creada desde SQL legacy con INT — forzar BIGINT UNSIGNED
        // Sin ->unique() en el change(): el indice ya existe desde la migration original
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_unico')->change();
        });

        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_unico')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedInteger('codigo_unico')->change();
        });

        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->unsignedInteger('codigo_unico')->nullable()->change();
        });
    }
};
