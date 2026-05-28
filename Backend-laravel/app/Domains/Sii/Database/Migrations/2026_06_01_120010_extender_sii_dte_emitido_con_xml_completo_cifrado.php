<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4.4 — Extiende sii_dte_emitido con:
 *
 *   - xml_completo_cifrado (LONGTEXT): backup del EnvioDTE final cifrado con
 *     APP_KEY (Crypt::encryptString). Permite recuperacion total si el disco
 *     se corrompe o el archivo se pierde. Cifrado para no exponer contenido
 *     sensible en backups de BD.
 *
 *   - fecha_firma (TIMESTAMP): momento en que EmitirDteService firma el DTE
 *     y lo deja en estado FIRMADO. Distinto de fecha_emision (campo del XSD,
 *     puede ser pasada) y de fecha_envio_sii (cuando se sube al WS, F5).
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
