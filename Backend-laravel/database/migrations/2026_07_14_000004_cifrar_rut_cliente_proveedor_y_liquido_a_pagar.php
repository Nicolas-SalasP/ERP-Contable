<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ley 21.719 — extiende el cifrado CipherSweet (ya probado con Empleado/Contrato) a
 * Cliente.rut, Proveedor.rut y Liquidacion.liquido_a_pagar.
 *
 * En producción, ejecutar ANTES de desplegar (mismo procedimiento que Empleado/Contrato,
 * ver docs/BACKUPS.md para el patrón de respaldo previo):
 *   php artisan ciphersweet:encrypt "App\Domains\Comercial\Models\Cliente"
 *   php artisan ciphersweet:encrypt "App\Domains\Comercial\Models\Proveedor"
 *   php artisan ciphersweet:encrypt "App\Domains\Rrhh\Models\Liquidacion"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // La unicidad de (empresa_id, rut) ya no aplica sobre ciphertext; se
            // delega al blind index 'cliente_rut_index' (mismo patrón que Empleado).
            $table->dropUnique(['rut', 'empresa_id']);
            $table->text('rut')->change();
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->text('rut')->nullable()->change();
        });

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->text('liquido_a_pagar')->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('rut', 20)->change();
            $table->unique(['rut', 'empresa_id']);
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('rut', 20)->nullable()->change();
        });

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->decimal('liquido_a_pagar', 14, 2)->default(0)->change();
        });
    }
};
