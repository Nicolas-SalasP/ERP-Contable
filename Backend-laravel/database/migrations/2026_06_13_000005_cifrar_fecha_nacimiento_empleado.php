<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2c — CipherSweet: cifrar fecha_nacimiento del empleado.
 *
 * La columna pasa de date nullable a text nullable para acomodar el ciphertext
 * de CipherSweet. El cast 'date' en el modelo sigue activo: CipherSweet desencripta
 * el string almacenado y Eloquent lo re-tipifica como Carbon en el acceso.
 *
 * En producción ejecutar:
 *   php artisan ciphersweet:encrypt "App\Domains\Rrhh\Models\Empleado" <NUEVA_CLAVE>
 * (ver docs/auditoria/CUMPLIMIENTO-LEY-21719-PLAN.md § Fase 2 — Operación de despliegue)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->text('fecha_nacimiento')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->nullable()->change();
        });
    }
};
