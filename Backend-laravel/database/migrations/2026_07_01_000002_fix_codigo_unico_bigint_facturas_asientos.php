<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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
