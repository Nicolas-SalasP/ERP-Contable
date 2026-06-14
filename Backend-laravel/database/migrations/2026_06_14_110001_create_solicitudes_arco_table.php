<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 — Ley 21.719: registro de solicitudes de derechos ARCO+
 * (Acceso, Rectificación, Cancelación/Supresión, Oposición/Bloqueo y Portabilidad).
 *
 * Cada solicitud queda trazada con su titular (polimórfico), tipo, estado y
 * resultado para evidencia ante la Agencia de Protección de Datos.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitudes_arco', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->morphs('titular');
            // ACCESO, PORTABILIDAD, SUPRESION, BLOQUEO
            $table->string('tipo');
            // COMPLETADA, PARCIAL_RETENCION, RECHAZADA
            $table->string('estado');
            $table->string('solicitado_por');
            $table->text('motivo')->nullable();
            $table->text('resultado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_arco');
    }
};
