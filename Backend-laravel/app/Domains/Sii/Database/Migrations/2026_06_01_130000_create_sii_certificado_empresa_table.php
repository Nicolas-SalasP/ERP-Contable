<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificado digital (.pfx / .p12) por empresa. Persiste el binario y la
 * passphrase CIFRADOS con APP_KEY (Crypt::encryptString). Soporta hasta
 * 1 cert activo + N en cuarentena (rollback 30d) + N revocados (auditoria).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_certificado_empresa', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            // Crypt::encryptString(.pfx). Cabe en BLOB normal MySQL (~10KB tras cifrado).
            $table->binary('pfx_cifrado');
            // Crypt::encryptString(passphrase). TEXT para permitir passphrases largas.
            $table->text('password_cifrada');

            // Metadatos extraidos del cert (sin filtrar secretos).
            $table->string('subject_rut', 10)->nullable();
            $table->string('subject_common_name', 200)->nullable();
            $table->string('issuer_common_name', 200)->nullable();
            $table->dateTime('valido_desde');
            $table->dateTime('valido_hasta');
            $table->string('fingerprint_sha256', 64)->nullable();

            $table->string('estado', 20)->default('activo'); // activo|cuarentena|revocado

            $table->timestamps();

            // Indices. No se aplica UNIQUE compuesto por (empresa_id, fingerprint)
            // condicionado a estado distinto a 'revocado' porque MySQL no soporta
            // partial indexes (la condicion se valida a nivel de aplicacion en
            // CertificadoService::cargar).
            $table->index(['empresa_id', 'estado'], 'sii_cert_empresa_estado_idx');
            $table->index('valido_hasta', 'sii_cert_valido_hasta_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_certificado_empresa');
    }
};
