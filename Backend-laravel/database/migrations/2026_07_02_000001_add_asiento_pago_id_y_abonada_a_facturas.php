<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // El código ya seteaba 'ABONADA' en pagos parciales (procesarPagoFacturas,
        // compensarPartidas) pero el enum original nunca lo incluyó: en MySQL modo
        // strict esa escritura falla o trunca. SQLite (tests) no valida el enum, por
        // eso el gap no se detectó en la suite.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE facturas MODIFY estado ENUM('BORRADOR', 'REGISTRADA', 'PAGADA', 'ABONADA', 'ANULADA') DEFAULT 'REGISTRADA'");
        }

        Schema::table('facturas', function (Blueprint $table) {
            // Referencia floja (sin FK) al asiento de pago, siguiendo el mismo patrón
            // que origen_modulo/origen_id en asientos_contables. Permite que la
            // anulación del asiento de pago encuentre y revierta la factura afectada.
            $table->unsignedBigInteger('asiento_pago_id')->nullable()->after('comprobante_contable');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('asiento_pago_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE facturas MODIFY estado ENUM('BORRADOR', 'REGISTRADA', 'PAGADA', 'ANULADA') DEFAULT 'REGISTRADA'");
        }
    }
};
