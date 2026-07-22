<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * emisor_rut/receptor_rut eran varchar(10), pero el origen (empresas.rut/clientes.rut) es
 * varchar(20) y FacturaAComercialDteMapper saneaba a 12 caracteres antes de insertar --
 * un RUT formateado con puntos ("12.345.678-9") o extranjero podia truncar/fallar. Bajo
 * SQLite (usado en tests) el largo declarado no se aplica, por eso el bug no se veia ahi;
 * solo revienta en MySQL con STRICT_TRANS_TABLES ("Data too long for column").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sii_dte_emitido', function (Blueprint $table) {
            $table->string('emisor_rut', 20)->change();
            $table->string('receptor_rut', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sii_dte_emitido', function (Blueprint $table) {
            $table->string('emisor_rut', 10)->change();
            $table->string('receptor_rut', 10)->change();
        });
    }
};
