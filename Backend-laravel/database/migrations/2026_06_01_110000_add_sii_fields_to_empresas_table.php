<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('giro_emisor', 80)->nullable();
            $table->unsignedInteger('codigo_actividad_sii')->nullable();
            $table->string('comuna', 20)->nullable();
            $table->string('ciudad', 20)->nullable();
            $table->integer('resolucion_sii_numero')->nullable();
            $table->date('resolucion_sii_fecha')->nullable();
            $table->string('ambiente_sii', 15)->default('certificacion');
            $table->string('email_intercambio_sii', 80)->nullable();
            $table->string('rut_representante_legal', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'giro_emisor',
                'codigo_actividad_sii',
                'comuna',
                'ciudad',
                'resolucion_sii_numero',
                'resolucion_sii_fecha',
                'ambiente_sii',
                'email_intercambio_sii',
                'rut_representante_legal',
            ]);
        });
    }
};
