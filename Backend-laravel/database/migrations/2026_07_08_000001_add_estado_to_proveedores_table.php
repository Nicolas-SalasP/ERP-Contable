<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Replica el campo 'estado' (ACTIVO/INACTIVO) que ya existe en clientes, para permitir bloquear proveedores.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('estado', 20)->default('ACTIVO')->after('razon_social');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
