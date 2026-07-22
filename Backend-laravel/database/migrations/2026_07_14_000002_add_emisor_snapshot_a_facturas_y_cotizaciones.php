<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los PDF de factura/cotizacion mostraban razon_social/rut/logo EN VIVO de la empresa.
     * Un cambio de imagen o razon social alteraba retroactivamente documentos ya emitidos,
     * lo cual rompe la inmutabilidad de un comprobante contable. Estas columnas guardan
     * una foto del emisor al momento de emision; los documentos viejos (sin foto) siguen
     * mostrando los datos actuales de la empresa como fallback.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('emisor_razon_social', 255)->nullable()->after('tipo');
            $table->string('emisor_rut', 20)->nullable()->after('emisor_razon_social');
            $table->string('emisor_logo_path', 255)->nullable()->after('emisor_rut');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('emisor_razon_social', 255)->nullable()->after('empresa_id');
            $table->string('emisor_rut', 20)->nullable()->after('emisor_razon_social');
            $table->string('emisor_logo_path', 255)->nullable()->after('emisor_rut');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['emisor_razon_social', 'emisor_rut', 'emisor_logo_path']);
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['emisor_razon_social', 'emisor_rut', 'emisor_logo_path']);
        });
    }
};
