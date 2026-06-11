<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('liquidacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->onDelete('cascade');
            $table->foreignId('concepto_remuneracion_id')->nullable()->constrained('concepto_remuneraciones')->nullOnDelete();

            $table->string('codigo_concepto', 30);
            $table->string('nombre_concepto', 100);
            $table->enum('tipo', [
                'HABER_IMPONIBLE',
                'HABER_NO_IMPONIBLE',
                'DESCUENTO_LEGAL',
                'DESCUENTO_VOLUNTARIO',
            ]);

            // Datos del cálculo para trazabilidad
            $table->decimal('base_calculo', 14, 2)->nullable();  // base usada para el % o la fórmula
            $table->decimal('tasa_aplicada', 7, 4)->nullable();   // porcentaje usado
            $table->decimal('monto', 14, 2)->default(0);

            // Orden de presentación en la liquidación
            $table->integer('orden')->default(0);

            $table->timestamps();

            $table->index(['empresa_id', 'liquidacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_detalles');
    }
};
