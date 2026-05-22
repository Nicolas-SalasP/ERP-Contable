<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('estado', 50)->default('REGISTRADA')->change();
        });

        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            $table->string('estado', 50)->default('PENDIENTE')->change();
        });
    }

    public function down(): void
    {
    }
};