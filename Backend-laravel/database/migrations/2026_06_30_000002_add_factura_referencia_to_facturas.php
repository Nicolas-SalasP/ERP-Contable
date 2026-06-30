<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedBigInteger('factura_referencia_id')->nullable()->after('sii_dte_emitido_id');
            $table->foreign('factura_referencia_id')->references('id')->on('facturas')->nullOnDelete();
            $table->string('razon_nota_credito', 255)->nullable()->after('factura_referencia_id');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropForeign(['factura_referencia_id']);
            $table->dropColumn(['factura_referencia_id', 'razon_nota_credito']);
        });
    }
};
