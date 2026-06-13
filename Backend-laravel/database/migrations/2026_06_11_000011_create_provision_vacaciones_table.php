<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Provisión de vacaciones: saldo y monto provisionado por empleado.
        // Se actualiza mensualmente al cerrar la liquidación (devengo Art. 67 C. del Trabajo).
        Schema::create('provision_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');
            $table->foreignId('contrato_id')->constrained('contratos')->onDelete('cascade');

            $table->integer('anio');
            $table->integer('mes');

            // Días hábiles devengados en el mes (15 días/12 meses = 1.25 días hábiles/mes)
            $table->decimal('dias_devengados_mes', 8, 4)->default(0);
            // Saldo acumulado al cierre del mes
            $table->decimal('saldo_dias_habiles', 8, 4)->default(0);
            // Monto provisionado en el mes (sueldo_dia × dias_devengados)
            $table->decimal('monto_devengado_mes', 14, 2)->default(0);
            // Monto acumulado provisionado total
            $table->decimal('monto_provisionado_total', 14, 2)->default(0);

            // Remuneración diaria usada para el cálculo de este período
            $table->decimal('remuneracion_diaria', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['empresa_id', 'empleado_id', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provision_vacaciones');
    }
};
