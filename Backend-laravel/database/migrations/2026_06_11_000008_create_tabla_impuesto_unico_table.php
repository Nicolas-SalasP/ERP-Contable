<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tabla de tramos del Impuesto Único de 2ª Categoría (Art. 42-43 LIR).
        // Expresados en UTM — se multiplican por el valor de la UTM del período.
        // Fuente: SII (sii.cl), se actualiza anualmente.
        Schema::create('tabla_impuesto_unico', function (Blueprint $table) {
            $table->id();
            $table->integer('anio');
            // Un tramo por fila; el motor los recupera todos para el año y los aplica en orden
            $table->integer('orden');  // 1, 2, 3, ...
            // Límites en UTM (null = sin límite superior → último tramo)
            $table->decimal('desde_utm', 10, 4);
            $table->decimal('hasta_utm', 10, 4)->nullable();
            // Tasa marginal (decimal: 0.04 = 4%)
            $table->decimal('tasa', 5, 4);
            // Factor deducción en UTM (para evitar acumulación de tramos)
            $table->decimal('factor_deduccion_utm', 10, 4)->default(0);

            $table->text('fuente')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'orden']);
            $table->index('anio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabla_impuesto_unico');
    }
};
