<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // UF, UTM y UTA por período — necesarios para convertir topes en pesos
        // y calcular la tabla del impuesto único.
        // Fuentes: CMF (UF), SII (UTM/UTA).
        Schema::create('indicadores_mensuales', function (Blueprint $table) {
            $table->id();
            $table->integer('anio');
            $table->integer('mes');  // 1-12

            // UF del primer día hábil del mes (usada para calcular topes)
            $table->decimal('uf_valor', 12, 4);
            // UTM del mes (base para tramos impuesto único)
            $table->decimal('utm_valor', 12, 2);
            // UTA = 12 × UTM del mes
            $table->decimal('uta_valor', 14, 2)->nullable();

            $table->text('fuente')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicadores_mensuales');
    }
};
