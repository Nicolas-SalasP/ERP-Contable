<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finiquitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');
            $table->foreignId('contrato_id')->constrained('contratos')->onDelete('cascade');

            // Causal y fecha de término
            $table->enum('causal', [
                'MUTUO_ACUERDO',
                'RENUNCIA',
                'MUERTE',
                'VENCIMIENTO_PLAZO',
                'FIN_OBRA',
                'CASO_FORTUITO',
                'DESPIDO_CONDUCTA',
                'NECESIDADES_EMPRESA',
                'DESAHUCIO',
            ]);
            $table->date('fecha_termino');

            // Datos usados en el cálculo
            $table->date('fecha_inicio_contrato');
            $table->decimal('ultima_remuneracion_mensual', 14, 2)->default(0);
            // Promedio de remuneraciones variables de los últimos 3 meses
            $table->decimal('promedio_remuneraciones_variables', 14, 2)->default(0);
            $table->decimal('remuneracion_base_calculo', 14, 2)->default(0);  // tope 90 UF

            // Años y meses de servicio
            $table->integer('anios_servicio')->default(0);
            $table->integer('meses_fraccion')->default(0);  // fracción > 6 meses = 1 año
            $table->boolean('fraccion_cuenta_como_anio')->default(false);
            $table->integer('anios_calculo')->default(0);  // anios que se pagan (máx. 11)

            // Indemnización por años de servicio (Art. 163) — solo para Art. 161
            $table->decimal('monto_indemnizacion_anos', 14, 2)->default(0);
            // Aviso previo (30 días o sustitutivo) — solo para Art. 161
            $table->boolean('tiene_aviso_previo')->default(false);
            $table->decimal('monto_aviso_previo', 14, 2)->default(0);

            // Vacaciones proporcionales (Art. 73)
            $table->decimal('dias_vacaciones_proporcionales', 8, 4)->default(0);
            $table->decimal('monto_vacaciones_proporcionales', 14, 2)->default(0);

            // Otros conceptos en el finiquito
            $table->decimal('haberes_pendientes', 14, 2)->default(0);  // sueldos no pagados
            $table->decimal('descuentos_pendientes', 14, 2)->default(0);

            // Total neto a pagar
            $table->decimal('total_bruto', 14, 2)->default(0);
            $table->decimal('total_neto', 14, 2)->default(0);

            $table->enum('estado', ['BORRADOR', 'FIRMADO', 'PAGADO', 'ANULADO'])->default('BORRADOR');
            $table->text('observaciones')->nullable();
            $table->string('comprobante_contable', 50)->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'empleado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finiquitos');
    }
};
