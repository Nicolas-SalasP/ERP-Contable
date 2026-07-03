<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los ajustes críticos (mermas, deterioro, pérdida, vencimiento) no tenían
 * ningún camino de reversa: una cantidad mal digitada alteraba stock y capas
 * FIFO de forma permanente. Agrega trazabilidad de anulación + referencia al
 * movimiento de reversa (mismo patrón que Factura.asiento_pago_id: no se
 * borra ni edita el original, se registra un movimiento compensatorio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_ajustes_criticos', function (Blueprint $table) {
            $table->timestamp('anulado_at')->nullable()->after('registrado_por');
            $table->unsignedBigInteger('anulado_por')->nullable()->after('anulado_at');
            $table->string('motivo_anulacion', 500)->nullable()->after('anulado_por');

            $table->foreignId('movimiento_reversa_id')
                ->nullable()
                ->after('motivo_anulacion')
                ->constrained('inventario_movimientos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_ajustes_criticos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_reversa_id');
            $table->dropColumn(['anulado_at', 'anulado_por', 'motivo_anulacion']);
        });
    }
};
