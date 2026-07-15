<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boleta Electronica (39/41) usa un token de sesion DISTINTO al de Factura/NC/ND -- el propio
 * SII lo advierte en su spec: "el Token de autenticacion obtenido para el envio de factura
 * electronica [...] no aplica para boleta, se debe obtener uno especifico". Sin esta columna,
 * una sesion activa de factura se reutilizaria para boleta (o viceversa) y el SII la rechazaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sii_token_sesion', function (Blueprint $table) {
            $table->string('ambito', 20)->default('factura')->after('ambiente');
        });

        Schema::table('sii_token_sesion', function (Blueprint $table) {
            $table->dropIndex('sii_token_sesion_activa_idx');
            $table->index(
                ['empresa_id', 'ambiente', 'ambito', 'fecha_expiracion'],
                'sii_token_sesion_activa_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sii_token_sesion', function (Blueprint $table) {
            $table->dropIndex('sii_token_sesion_activa_idx');
            $table->index(
                ['empresa_id', 'ambiente', 'fecha_expiracion'],
                'sii_token_sesion_activa_idx'
            );
            $table->dropColumn('ambito');
        });
    }
};
