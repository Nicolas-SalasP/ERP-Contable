<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');

            // Tipo y vigencia (Código del Trabajo Art. 7-17)
            $table->enum('tipo', ['INDEFINIDO', 'PLAZO_FIJO', 'POR_OBRA'])->default('INDEFINIDO');
            $table->date('fecha_inicio');
            $table->date('fecha_termino')->nullable();  // null = indefinido o sin plazo definido

            // Cargo y estructura organizacional
            $table->string('cargo', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();

            // Jornada
            $table->integer('horas_semana')->default(45);
            $table->enum('tipo_jornada', ['COMPLETA', 'PARCIAL', 'TURNO'])->default('COMPLETA');

            // Remuneración base
            $table->decimal('sueldo_base', 14, 2)->default(0);

            // Causal de término (Art. 159, 160, 161)
            $table->enum('causal_termino', [
                'MUTUO_ACUERDO',     // 159 N°1
                'RENUNCIA',          // 159 N°2
                'MUERTE',            // 159 N°3
                'VENCIMIENTO_PLAZO', // 159 N°4
                'FIN_OBRA',          // 159 N°5
                'CASO_FORTUITO',     // 159 N°6
                'DESPIDO_CONDUCTA',  // 160
                'NECESIDADES_EMPRESA', // 161
                'DESAHUCIO',         // 161 inciso 2°
            ])->nullable();
            $table->date('fecha_termino_real')->nullable();
            $table->text('observaciones_termino')->nullable();

            // Estado del contrato
            $table->enum('estado', ['VIGENTE', 'TERMINADO', 'SUSPENDIDO'])->default('VIGENTE');

            // Solo un contrato puede estar VIGENTE por empleado a la vez
            // (soportamos histórico de múltiples contratos)
            $table->boolean('es_contrato_activo')->default(true);

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'empleado_id', 'estado']);
            $table->index(['empresa_id', 'es_contrato_activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
