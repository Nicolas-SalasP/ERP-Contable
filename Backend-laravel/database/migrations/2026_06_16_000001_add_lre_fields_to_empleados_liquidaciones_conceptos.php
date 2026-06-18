<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('comuna_codigo_lre', 6)->nullable()->comment('Cód. comuna LRE (cod 1106)');
            $table->unsignedSmallInteger('organismo_mutual_codigo')->nullable()->comment('Org. Ley 16.744 (cod 1152): 1=ACHS, 2=IST, 3=MUTUAL, 4=CChC');
            $table->unsignedSmallInteger('ccaf_codigo')->nullable()->comment('CCAF (cod 1110)');
            $table->boolean('pensionado_vejez')->default(false)->comment('Pensionado por vejez (cod 1109)');
            $table->boolean('pensionado_invalidez')->default(false)->comment('Discapacidad/pensionado invalidez (cod 1108)');
        });

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias_trabajados')->nullable()->comment('Días trabajados en el mes (cod 1115)');
            $table->unsignedTinyInteger('dias_licencia_medica')->default(0)->comment('Días licencia médica (cod 1116)');
            $table->unsignedTinyInteger('dias_vacaciones')->default(0)->comment('Días vacaciones en el mes (cod 1117)');
        });

        Schema::table('concepto_remuneraciones', function (Blueprint $table) {
            $table->string('codigo_lre', 10)->nullable()->comment('Código LRE del campo (ej: 2101, 2106, 3141)');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn([
                'comuna_codigo_lre',
                'organismo_mutual_codigo',
                'ccaf_codigo',
                'pensionado_vejez',
                'pensionado_invalidez',
            ]);
        });

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropColumn([
                'dias_trabajados',
                'dias_licencia_medica',
                'dias_vacaciones',
            ]);
        });

        Schema::table('concepto_remuneraciones', function (Blueprint $table) {
            $table->dropColumn('codigo_lre');
        });
    }
};
