<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');
            $table->foreignId('contrato_id')->constrained('contratos')->onDelete('cascade');

            // Período de la liquidación
            $table->integer('anio');
            $table->integer('mes');  // 1-12

            // Parámetros aplicados en este cálculo (snapshot para inmutabilidad histórica)
            $table->foreignId('parametro_previsional_id')->nullable()->constrained('parametros_previsionales')->nullOnDelete();
            $table->foreignId('indicador_mensual_id')->nullable()->constrained('indicadores_mensuales')->nullOnDelete();

            // Totales del período
            $table->decimal('total_haberes_imponibles', 14, 2)->default(0);
            $table->decimal('total_haberes_no_imponibles', 14, 2)->default(0);
            $table->decimal('total_haberes', 14, 2)->default(0);         // imponible + no imponible
            $table->decimal('base_imponible', 14, 2)->default(0);        // tope aplicado para cotizaciones
            $table->decimal('base_tributable', 14, 2)->default(0);       // para cálculo impuesto único
            $table->decimal('total_descuentos_legales', 14, 2)->default(0);
            $table->decimal('total_descuentos_voluntarios', 14, 2)->default(0);
            $table->decimal('total_descuentos', 14, 2)->default(0);
            $table->decimal('liquido_a_pagar', 14, 2)->default(0);

            // Aportes del empleador (no van en el líquido pero se contabilizan)
            $table->decimal('aporte_empleador_afc', 14, 2)->default(0);
            $table->decimal('aporte_empleador_sis', 14, 2)->default(0);
            $table->decimal('aporte_empleador_mutual', 14, 2)->default(0);

            // Estado
            $table->enum('estado', ['BORRADOR', 'EMITIDA', 'PAGADA', 'ANULADA'])->default('BORRADOR');
            $table->text('observaciones')->nullable();

            // Comprobante contable (centralización R5)
            $table->string('comprobante_contable', 50)->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'empleado_id', 'anio', 'mes']);
            $table->index(['empresa_id', 'anio', 'mes', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');
    }
};
