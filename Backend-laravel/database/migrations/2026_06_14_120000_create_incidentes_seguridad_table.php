<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 — Ley 21.663 / 21.719: Registro de incidentes de seguridad y brechas de datos.
 *
 * Plazos legales:
 *  - Alerta temprana CSIRT: 3 horas desde detección (Ley 21.663 Art. 8).
 *  - Reporte CSIRT:         72 horas desde detección (Ley 21.663 Art. 8).
 *  - Notificación a la Agencia de Protección de Datos Personales (Ley 21.719).
 *  - Notificación a titulares afectados (Ley 21.719).
 *
 * NOTA DE PRIVACIDAD: la columna categorias_datos_afectados almacena SOLO
 * categorías (p. ej. "RUT, datos de salud") — NUNCA datos personales reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidentes_seguridad', function (Blueprint $table) {
            $table->id();

            // Tenant: nullable para incidentes transversales de infraestructura.
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('set null');

            $table->string('titulo');
            $table->text('descripcion');

            // BAJA | MEDIA | ALTA | CRITICA
            $table->string('severidad');

            $table->string('origen')->nullable();

            // Categorías de datos afectados (solo tipología, nunca PII real).
            $table->text('categorias_datos_afectados')->nullable();

            $table->unsignedInteger('n_afectados_estimado')->nullable();

            // Línea de tiempo del incidente.
            $table->timestamp('detectado_at');
            $table->timestamp('alerta_temprana_at')->nullable();   // 3h CSIRT
            $table->timestamp('reporte_csirt_at')->nullable();     // 72h CSIRT
            $table->timestamp('notificacion_agencia_at')->nullable();
            $table->timestamp('notificacion_afectados_at')->nullable();

            // ABIERTO | CONTENIDO | CERRADO
            $table->string('estado')->default('ABIERTO');

            $table->string('registrado_por');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidentes_seguridad');
    }
};
