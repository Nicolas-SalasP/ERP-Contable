<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Catálogo de conceptos de remuneración: haberes y descuentos tipificados.
        // Algunos son fijos del sistema (sueldo_base, afp, salud, afc, impuesto_unico);
        // otros son configurables por empresa.
        Schema::create('concepto_remuneraciones', function (Blueprint $table) {
            $table->id();
            // empresa_id null = concepto del sistema (compartido); no-null = personalizado por empresa
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();

            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();

            $table->enum('tipo', [
                'HABER_IMPONIBLE',      // ej: sueldo base, gratificación, horas extra
                'HABER_NO_IMPONIBLE',   // ej: colación, movilización, asignación familiar
                'DESCUENTO_LEGAL',      // ej: AFP, salud, AFC, impuesto único
                'DESCUENTO_VOLUNTARIO', // ej: anticipo, préstamo, APV, sindicato
            ]);

            // Cómo se calcula este concepto en el motor de liquidación
            $table->enum('regla_calculo', [
                'MONTO_FIJO',            // se toma del HaberDescuentoContrato
                'PORCENTAJE_IMPONIBLE',  // tasa × base imponible (AFP, salud, AFC)
                'TABLA_IMPUESTO',        // liquidación por tabla (impuesto único)
                'FORMULA_GRATIFICACION', // 25% con tope (Art. 50)
                'FORMULA_HORAS_EXTRA',   // horas × valor_hora × 1.5
                'MANUAL',                // ingresado manualmente en la liquidación
            ])->default('MONTO_FIJO');

            // Indica si es un concepto obligatorio del sistema (no se puede desactivar)
            $table->boolean('es_sistema')->default(false);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);

            $table->timestamps();

            $table->index(['empresa_id', 'tipo', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_remuneraciones');
    }
};
