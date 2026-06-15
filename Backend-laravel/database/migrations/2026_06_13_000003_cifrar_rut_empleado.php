<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2b — CipherSweet: cifrar RUT del empleado con índice ciego para búsqueda exacta.
 *
 * El RUT deja de almacenarse en texto claro. Se convierte a text (ciphertext) y
 * se elimina la restricción UNIQUE directa de la columna (que ya no es útil sobre
 * ciphertext). La unicidad se garantiza vía el blind index 'empleado_rut_index'
 * almacenado en la tabla blind_indexes (spatie/laravel-ciphersweet).
 *
 * Nota SQLite: Schema::table() con ->change() y ->dropUnique() funcionan en el driver
 * sqlite del entorno de tests porque doctrine/dbal recrea la tabla internamente.
 *
 * En producción ejecutar ANTES de desplegar:
 *   php artisan ciphersweet:encrypt "App\Domains\Rrhh\Models\Empleado" <NUEVA_CLAVE>
 * (ver docs/auditoria/CUMPLIMIENTO-LEY-21719-PLAN.md § Fase 2 — Operación de despliegue)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            // Eliminar la restricción UNIQUE sobre (empresa_id, rut) — ya no aplica
            // sobre ciphertext; la unicidad se delega al blind index.
            $table->dropUnique(['empresa_id', 'rut']);

            // Ampliar la columna a text para acomodar el ciphertext de CipherSweet.
            $table->text('rut')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            // Volver a string(12) NOT NULL y restaurar el índice único.
            $table->string('rut', 12)->nullable(false)->change();
            $table->unique(['empresa_id', 'rut']);
        });
    }
};
