<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade el código de mutualidad al registro de parámetros previsionales.
 *
 * El ERP ya calcula y almacena el monto de la cotización (aporte_empleador_mutual
 * en liquidaciones), pero el archivo Previred (campo 59) requiere además el CÓDIGO
 * numérico de la institución: 01=ACHS, 02=ISL, 03=Mutual de Seguridad.
 * Valor por defecto '01' (ACHS, la mutualidad más común); se puede cambiar desde
 * la pantalla de parámetros RRHH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametros_previsionales', function (Blueprint $table) {
            $table->string('mutual_codigo', 2)->default('01')->after('mutual_cotizacion_basica_pct');
        });
    }

    public function down(): void
    {
        Schema::table('parametros_previsionales', function (Blueprint $table) {
            $table->dropColumn('mutual_codigo');
        });
    }
};
