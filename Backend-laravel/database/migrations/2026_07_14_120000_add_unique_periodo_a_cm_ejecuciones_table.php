<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El check "ya existe una ejecucion 'ejecutada' para este periodo" vivia solo en
 * PHP (exists() antes de insertar), sin respaldo en BD -- dos requests concurrentes
 * podian pasar el check y crear dos ejecuciones para el mismo periodo. Esta columna
 * generada colapsa a NULL para 'simulada'/'anulada' (no bloquean, como antes) y a
 * un valor unico por empresa/periodo cuando estado='ejecutada'.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        $expresion = $driver === 'sqlite'
            ? "CASE WHEN estado = 'ejecutada' THEN empresa_id || '-' || periodo_mes || '-' || periodo_anio ELSE NULL END"
            : "CASE WHEN estado = 'ejecutada' THEN CONCAT(empresa_id, '-', periodo_mes, '-', periodo_anio) ELSE NULL END";

        Schema::table('cm_ejecuciones', function (Blueprint $table) use ($expresion) {
            $table->string('ejecutada_periodo_key', 40)->nullable()->virtualAs($expresion)->after('estado');
        });

        Schema::table('cm_ejecuciones', function (Blueprint $table) {
            $table->unique('ejecutada_periodo_key', 'cm_ejecuciones_periodo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cm_ejecuciones', function (Blueprint $table) {
            $table->dropUnique('cm_ejecuciones_periodo_unique');
            $table->dropColumn('ejecutada_periodo_key');
        });
    }
};
