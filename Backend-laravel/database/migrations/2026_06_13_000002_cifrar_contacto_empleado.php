<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2a — CipherSweet: ampliar columnas de contacto del empleado a text.
 *
 * Las columnas email, telefono y direccion se cifran con CipherSweet
 * (spatie/laravel-ciphersweet). El ciphertext generado supera el tamaño
 * de los varchar originales, por lo que se convierten a text nullable.
 *
 * No se realiza backfill en esta migración porque en el entorno de tests
 * la base de datos arranca vacía. En producción ejecutar:
 *   php artisan ciphersweet:encrypt "App\Domains\Rrhh\Models\Empleado" <NUEVA_CLAVE>
 * (ver docs/auditoria/CUMPLIMIENTO-LEY-21719-PLAN.md § Fase 2 — Operación de despliegue)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->text('email')->nullable()->change();
            $table->text('telefono')->nullable()->change();
            $table->text('direccion')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->change();
            $table->string('telefono', 20)->nullable()->change();
            $table->string('direccion', 255)->nullable()->change();
        });
    }
};
