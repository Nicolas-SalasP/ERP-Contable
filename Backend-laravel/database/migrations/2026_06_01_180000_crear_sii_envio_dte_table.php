<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5.2 — Auditoria de cada subida del XML EnvioDTE al WS DTEUpload del SII.
 *
 * En F5.2 con D3=A (1 envio = 1 DTE), xml_envio_path/hash quedan NULL — el
 * XML real esta en sii_dte_emitido.xml_completo_cifrado. Reservados para
 * evolucion futura si pasamos a D3=B (batch multi-DTE).
 *
 * SEGURIDAD: bodies cifrados con Crypt::encryptString; el modelo los marca
 * en $hidden para no exponerlos en JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_envio_dte', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('dte_emitido_id')->constrained('sii_dte_emitido')->cascadeOnDelete();
            $table->foreignId('token_sesion_id')->nullable()
                ->constrained('sii_token_sesion')
                ->nullOnDelete();

            $table->string('ambiente_sii', 20);

            $table->string('estado_envio', 40)->default('PENDIENTE');
            $table->string('track_id', 50)->nullable();
            $table->text('glosa_sii')->nullable();

            // Reservados para batch futuro (D3=B). En F5.2 quedan NULL.
            $table->string('xml_envio_path', 255)->nullable();
            $table->char('xml_envio_hash_sha256', 64)->nullable();

            // Forensia legal: bodies HTTP completos cifrados (sin el XML grande,
            // que vive en sii_dte_emitido.xml_completo_cifrado).
            $table->longText('request_body_completo_cifrado')->nullable();
            $table->longText('respuesta_body_completo_cifrado')->nullable();

            $table->unsignedSmallInteger('http_status_ultimo_envio')->nullable();
            $table->unsignedInteger('intentos_envio')->default(0);

            $table->timestamp('fecha_envio')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'estado_envio'], 'sii_envio_dte_empresa_estado_idx');
            $table->index('track_id', 'sii_envio_dte_track_id_idx');
            $table->index(['dte_emitido_id', 'estado_envio'], 'sii_envio_dte_dte_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_envio_dte');
    }
};
