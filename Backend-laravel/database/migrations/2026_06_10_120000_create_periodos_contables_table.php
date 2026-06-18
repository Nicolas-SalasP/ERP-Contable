<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueo de periodo contable (inmutabilidad). Cierra los hallazgos F-1/F-2 de la
 * auditoria: solo se persisten los periodos CERRADOS; la ausencia de fila = ABIERTO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->smallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('estado')->default('CERRADO');
            $table->unsignedBigInteger('cerrado_por')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->unsignedBigInteger('reabierto_por')->nullable();
            $table->timestamp('reabierto_at')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'anio', 'mes'], 'periodos_empresa_anio_mes_unique');
            $table->index(['empresa_id', 'estado']);

            $table->foreign('cerrado_por')->references('id')->on('usuarios')->nullOnDelete();
            $table->foreign('reabierto_por')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_contables');
    }
};
