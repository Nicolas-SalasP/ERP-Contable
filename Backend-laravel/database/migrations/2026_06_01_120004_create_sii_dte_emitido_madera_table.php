<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de manejo de madera (sub-tabla 1:1 opcional con sii_dte_emitido_traslado).
 * Exigencia Res. Ex. SII N°154/2025.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_dte_emitido_madera', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dte_emitido_traslado_id')
                ->constrained('sii_dte_emitido_traslado')
                ->cascadeOnDelete();

            $table->string('rol_predio_origen', 30)->nullable();
            $table->string('rol_predio_destino', 30)->nullable();
            $table->string('aviso_ejecucion', 40)->nullable();
            $table->string('codigo_plan_conaf', 40)->nullable();
            $table->decimal('georef_origen_lat', 10, 7)->nullable();
            $table->decimal('georef_origen_lng', 10, 7)->nullable();
            $table->decimal('georef_destino_lat', 10, 7)->nullable();
            $table->decimal('georef_destino_lng', 10, 7)->nullable();

            $table->timestamps();

            $table->unique('dte_emitido_traslado_id', 'sii_dte_emitido_madera_traslado_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_dte_emitido_madera');
    }
};
