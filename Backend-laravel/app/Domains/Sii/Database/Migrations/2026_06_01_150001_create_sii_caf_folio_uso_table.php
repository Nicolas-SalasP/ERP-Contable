<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad de uso de cada folio del CAF.
 * Estados: RESERVADO|USADO|HUERFANO|ANULADO. Una vez USADO no se libera
 * (regla SII: folios firmados quedan inmutables aunque el DTE se anule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_caf_folio_uso', function (Blueprint $table) {
            $table->id();

            $table->foreignId('caf_id')->constrained('sii_caf')->cascadeOnDelete();
            $table->unsignedInteger('folio');

            $table->foreignId('dte_emitido_id')
                ->nullable()
                ->constrained('sii_dte_emitido')
                ->nullOnDelete();

            $table->string('estado', 20); // RESERVADO|USADO|HUERFANO|ANULADO

            $table->timestamp('reservado_at');
            $table->timestamp('usado_at')->nullable();
            $table->timestamp('liberado_at')->nullable();
            $table->string('razon_liberacion', 200)->nullable();

            $table->foreignId('usuario_reservo_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['caf_id', 'folio'], 'sii_caf_folio_uso_caf_folio_unique');
            $table->index(['caf_id', 'estado'], 'sii_caf_folio_uso_caf_estado_idx');
            // dte_emitido_id ya tiene index implicito por la FK.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_caf_folio_uso');
    }
};
