<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de alertas generico (cumplimiento y gestion), multitipo. Cada evaluador registrado
 * en config('alertas.evaluadores') crea filas aca; el registro sirve a la vez de bandeja para
 * la UI y de historial de envio (evita duplicar notificaciones, igual que sii_certificado_notificaciones).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->string('nivel', 20)->default('info');
            $table->string('entidad_type', 100)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->text('mensaje');
            $table->json('datos')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('enviada_at')->nullable();
            $table->timestamp('resuelta_at')->nullable();
            $table->unsignedBigInteger('resuelta_por')->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestamps();

            // Dedupe: el motor consulta por esta combinacion antes de crear/enviar una nueva alerta.
            $table->index(['empresa_id', 'tipo', 'entidad_type', 'entidad_id', 'nivel'], 'alertas_dedupe_idx');
            $table->index(['empresa_id', 'estado'], 'alertas_empresa_estado_idx');

            $table->foreign('resuelta_por')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
