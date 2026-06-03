<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende sii_dte_emitido con xml_completo_cifrado (backup del EnvioDTE cifrado
 * con APP_KEY para recuperacion ante perdida de disco) y fecha_firma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sii_dte_emitido', function (Blueprint $table) {
            $table->longText('xml_completo_cifrado')->nullable()->after('xml_hash_sha256');
            $table->timestamp('fecha_firma')->nullable()->after('xml_completo_cifrado');
        });
    }

    public function down(): void
    {
        Schema::table('sii_dte_emitido', function (Blueprint $table) {
            $table->dropColumn(['xml_completo_cifrado', 'fecha_firma']);
        });
    }
};
