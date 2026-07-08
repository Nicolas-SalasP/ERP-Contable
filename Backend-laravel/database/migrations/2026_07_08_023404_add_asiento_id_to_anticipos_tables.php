<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un anticipo autogenerado en Mesa de Conciliación (sobrepago) no guardaba el
 * asiento_id que lo originó -- al reversar/anular ese asiento, el anticipo
 * quedaba PAGADO para siempre (saldo fantasma) porque AnulacionService no
 * tenía forma de encontrarlo. Mismo patrón que factura.asiento_pago_id y
 * movimientos_bancarios.asiento_id (ya usados para lo mismo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('asiento_id')->nullable()->after('movimiento_id');
        });
        Schema::table('anticipos_clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('asiento_id')->nullable()->after('movimiento_id');
        });
    }

    public function down(): void
    {
        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            $table->dropColumn('asiento_id');
        });
        Schema::table('anticipos_clientes', function (Blueprint $table) {
            $table->dropColumn('asiento_id');
        });
    }
};
