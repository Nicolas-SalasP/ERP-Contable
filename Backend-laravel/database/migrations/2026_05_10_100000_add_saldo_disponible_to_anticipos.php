<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas reales para tracking de saldos en anticipos a proveedores.
 *
 * Antes el saldo era virtual (todo o nada via accessor en el modelo).
 * Ahora se almacena explicitamente para soportar aplicaciones parciales:
 * un anticipo de $100k puede aplicarse en partes ($40k a una factura,
 * $60k a otra) y mantenemos el tracking del saldo restante.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            // monto_original: el monto inicial que nunca cambia
            // saldo_disponible: lo que queda por aplicar (decrece al aplicar)
            // factura_id: si esta totalmente aplicado a una sola factura, referencia
            $table->decimal('monto_original', 15, 2)->nullable()->after('monto');
            $table->decimal('saldo_disponible', 15, 2)->nullable()->after('monto_original');
            $table->date('fecha_real')->nullable()->after('saldo_disponible');
        });

        // Inicializar columnas para registros existentes
        DB::table('anticipos_proveedores')->update([
            'monto_original' => DB::raw('monto'),
            'saldo_disponible' => DB::raw("CASE WHEN estado = 'APLICADO' THEN 0 ELSE monto END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            $table->dropColumn(['monto_original', 'saldo_disponible', 'fecha_real']);
        });
    }
};
