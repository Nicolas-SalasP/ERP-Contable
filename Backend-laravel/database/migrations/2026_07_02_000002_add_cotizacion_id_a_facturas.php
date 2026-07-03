<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Referencia floja (sin FK) a la cotización de venta convertida en esta
            // factura. Permite que anularFactura() revierta el estado de la cotización
            // a "Aceptada" en vez de dejarla "Facturada" para siempre.
            $table->unsignedBigInteger('cotizacion_id')->nullable()->after('asiento_pago_id');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('cotizacion_id');
        });
    }
};
