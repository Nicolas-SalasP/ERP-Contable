<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2b — CipherSweet: cifrar RUT de cargas familiares.
 *
 * El campo rut de cargas_familiares es nullable y no está indexado para búsqueda,
 * por lo que no necesita blind index. Se amplía a text para acomodar el ciphertext.
 *
 * En producción ejecutar ANTES de desplegar:
 *   php artisan ciphersweet:encrypt "App\Domains\Rrhh\Models\CargaFamiliar" <NUEVA_CLAVE>
 * (ver docs/auditoria/CUMPLIMIENTO-LEY-21719-PLAN.md § Fase 2 — Operación de despliegue)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cargas_familiares', function (Blueprint $table) {
            $table->text('rut')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cargas_familiares', function (Blueprint $table) {
            $table->string('rut', 12)->nullable()->change();
        });
    }
};
