<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2c — CipherSweet: cifrar sueldo_base del contrato.
 *
 * La columna pasa de decimal(14,2) NOT NULL (default 0) a text NOT NULL para
 * acomodar el ciphertext de CipherSweet. El cast 'decimal:2' en el modelo sigue
 * activo: CipherSweet desencripta el string almacenado y Eloquent lo re-tipifica.
 *
 * La columna original tiene DEFAULT 0, lo cual queda obsoleto al pasar a text.
 * El modelo siempre envía un valor explícito para sueldo_base, por lo que
 * no se necesita DEFAULT en la capa de BD.
 *
 * En producción ejecutar:
 *   php artisan ciphersweet:encrypt "App\Domains\Rrhh\Models\Contrato" <NUEVA_CLAVE>
 * (ver docs/auditoria/CUMPLIMIENTO-LEY-21719-PLAN.md § Fase 2 — Operación de despliegue)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Mantenemos NOT NULL (la columna original es NOT NULL con default 0).
            $table->text('sueldo_base')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->decimal('sueldo_base', 14, 2)->default(0)->nullable(false)->change();
        });
    }
};
