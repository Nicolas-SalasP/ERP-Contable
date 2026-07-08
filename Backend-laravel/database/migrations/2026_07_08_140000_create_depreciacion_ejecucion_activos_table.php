<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda la cuota exacta que depreciarMes() aplicó a cada activo en una ejecución
     * mensual -- sin esto, revertir el asiento (reversa/anulación) no tiene forma de saber
     * cuánto restarle a depreciacion_acumulada de cada activo (recalcular de nuevo puede
     * diverger si el activo cambió de estado o vida útil entre medio). Mismo patrón que
     * cm_ejecucion_activos (Corrección Monetaria).
     */
    public function up(): void
    {
        Schema::create('depreciacion_ejecucion_activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('asiento_id')->constrained('asientos_contables')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos_fijos')->cascadeOnDelete();
            $table->unsignedTinyInteger('periodo_mes');
            $table->unsignedSmallInteger('periodo_anio');
            $table->decimal('monto_cuota', 15, 2);
            $table->timestamps();

            $table->index(['asiento_id']);
            $table->index(['empresa_id', 'activo_id', 'periodo_anio', 'periodo_mes'], 'dep_ejec_activos_periodo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciacion_ejecucion_activos');
    }
};
