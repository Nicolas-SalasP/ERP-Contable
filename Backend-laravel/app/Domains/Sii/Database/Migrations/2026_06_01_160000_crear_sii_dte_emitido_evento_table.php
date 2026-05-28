<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HARDENING-1 R4 — Audit log persistente de transiciones de estado del DTE.
 *
 * Cada cambio relevante de estado (BORRADOR→FIRMADO en F4.4; FIRMADO→ENVIADO
 * y subsiguientes en F5) crea un registro INMUTABLE aqui. Sin updated_at:
 * el historial es event-sourcing-like, no se sobreescribe nunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_dte_emitido_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dte_emitido_id')
                ->constrained('sii_dte_emitido')
                ->cascadeOnDelete();

            $table->string('estado_anterior', 40)->nullable();
            $table->string('estado_nuevo', 40);
            $table->string('glosa', 500)->nullable();

            // Payload JSON: contexto adicional (folio, hash, track_id, etc.).
            // En MySQL es JSON nativo; en SQLite (testing) es TEXT.
            $table->json('payload')->nullable();

            // Solo created_at: los eventos son INMUTABLES por diseno.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['dte_emitido_id', 'created_at'], 'sii_dte_emitido_evento_dte_created_idx');
            $table->index('estado_nuevo', 'sii_dte_emitido_evento_estado_nuevo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_dte_emitido_evento');
    }
};
