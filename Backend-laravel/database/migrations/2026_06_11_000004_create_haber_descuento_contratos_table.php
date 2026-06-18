<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Haberes y descuentos fijos pactados en el contrato (colación, movilización, anticipos, etc.)
        Schema::create('haber_descuento_contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('contrato_id')->constrained('contratos')->onDelete('cascade');

            $table->string('nombre', 100);
            // Tipo enlazado a ConceptoRemuneracion; también puede ser ad-hoc sin concepto
            $table->foreignId('concepto_remuneracion_id')->nullable()->constrained('concepto_remuneraciones')->nullOnDelete();

            $table->enum('tipo', [
                'HABER_IMPONIBLE',
                'HABER_NO_IMPONIBLE',
                'DESCUENTO_LEGAL',
                'DESCUENTO_VOLUNTARIO',
            ]);

            // Valor: monto fijo o porcentaje del sueldo base
            $table->enum('modalidad_valor', ['MONTO_FIJO', 'PORCENTAJE_SUELDO_BASE'])->default('MONTO_FIJO');
            $table->decimal('monto', 14, 2)->default(0);
            $table->decimal('porcentaje', 6, 4)->nullable();

            $table->boolean('activo')->default(true);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'contrato_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haber_descuento_contratos');
    }
};
