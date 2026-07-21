<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5.3 — Audit log INMUTABLE de transiciones del envio al SII (espejo de
 * sii_dte_emitido_evento de HARDENING-1 R4 pero scoped al envio).
 *
 * Cada cambio del estado_envio + cada polling exitoso genera un INSERT.
 * Sin updated_at: event-sourcing, no se sobreescribe nunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_envio_dte_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_dte_id')
                ->constrained('sii_envio_dte')
                ->cascadeOnDelete();

            $table->string('estado_anterior', 40)->nullable();
            $table->string('estado_nuevo', 40);
            $table->text('glosa')->nullable();
            $table->json('payload')->nullable();

            // Codigo SII raw del polling: EPR, EOK, RPR, LOC, etc.
            $table->string('codigo_sii_raw', 10)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['envio_dte_id', 'created_at'], 'sii_envio_dte_evento_envio_created_idx');
            $table->index('estado_nuevo', 'sii_envio_dte_evento_estado_nuevo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_envio_dte_evento');
    }
};
