<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El guard de depreciarMes() (exists() sobre asientos_contables por glosa) no tenia
 * respaldo en BD -- dos requests concurrentes para el mismo periodo podian pasar el
 * check y duplicar la cuota de depreciacion de cada activo. Esta unique hace que el
 * segundo INSERT de la carrera falle (mismo activo/periodo no puede repetirse), y
 * al estar dentro de la misma transaccion de depreciarMes(), revierte tambien el
 * UPDATE de depreciacion_acumulada ya aplicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depreciacion_ejecucion_activos', function (Blueprint $table) {
            $table->unique(
                ['empresa_id', 'activo_id', 'periodo_anio', 'periodo_mes'],
                'dep_ejec_activos_periodo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('depreciacion_ejecucion_activos', function (Blueprint $table) {
            $table->dropUnique('dep_ejec_activos_periodo_unique');
        });
    }
};
