<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Guarda el ajuste exacto que actualizarActivosFijos() aplicó a cada activo en una
     * ejecución de CM -- sin esto, revertir la ejecución no tiene forma de saber cuánto
     * restarle a cada activo (recalcular de nuevo puede diverger si el activo cambió de
     * estado o se agregaron activos nuevos entre medio).
     */
    public function up(): void
    {
        Schema::create('cm_ejecucion_activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cm_ejecucion_id')->constrained('cm_ejecuciones')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos_fijos')->cascadeOnDelete();
            $table->decimal('ajuste_activo', 15, 2);
            $table->decimal('ajuste_depreciacion', 15, 2);
            $table->timestamps();

            $table->index(['cm_ejecucion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cm_ejecucion_activos');
    }
};
