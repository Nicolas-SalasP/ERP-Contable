<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Produccion fue creada desde SQL legacy con INT — forzar BIGINT UNSIGNED
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_unico')->unique()->change();
        });

        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_unico')->nullable()->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedInteger('codigo_unico')->unique()->change();
        });

        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->unsignedInteger('codigo_unico')->nullable()->unique()->change();
        });
    }
};
