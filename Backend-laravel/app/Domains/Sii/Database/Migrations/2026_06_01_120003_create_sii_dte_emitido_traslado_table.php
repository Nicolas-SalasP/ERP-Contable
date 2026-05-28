<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos especificos de Guia de Despacho electronica (tipo_dte=52).
 * Relacion 1:1 con sii_dte_emitido (UNIQUE en dte_emitido_id).
 * Incluye campos exigidos por Res. Ex. SII N°154/2025.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_dte_emitido_traslado', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dte_emitido_id')
                ->constrained('sii_dte_emitido')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('indicador_traslado'); // IndTraslado 1-8
            $table->string('rut_chofer', 10)->nullable();
            $table->string('nombre_chofer', 80)->nullable();
            $table->string('patente', 8)->nullable();
            $table->string('rut_transportista', 10)->nullable();
            $table->string('direccion_destino', 70)->nullable();
            $table->string('comuna_destino', 20)->nullable();
            $table->string('ciudad_destino', 20)->nullable();

            $table->timestamps();

            $table->unique('dte_emitido_id', 'sii_dte_emitido_traslado_dte_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_dte_emitido_traslado');
    }
};
