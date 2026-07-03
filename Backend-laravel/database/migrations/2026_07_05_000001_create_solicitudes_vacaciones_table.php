<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitud/aprobacion de feriado legal (Art. 67-70 Codigo del Trabajo).
 *
 * El saldo disponible (VacacionesService::saldoActual) se calcula restando
 * la suma de dias_habiles de solicitudes APROBADA al devengo acumulado en
 * provision_vacaciones. No se toca esa tabla mensual de devengo: mantener
 * el consumo en una tabla separada evita colisionar con el unique
 * (empresa_id, empleado_id, anio, mes) que usa devengarMes().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');
            $table->foreignId('contrato_id')->constrained('contratos')->onDelete('cascade');

            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->decimal('dias_habiles', 8, 4);

            $table->enum('estado', ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA'])->default('PENDIENTE');

            $table->text('observacion')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->text('motivo_anulacion')->nullable();

            $table->unsignedBigInteger('solicitado_por');
            $table->unsignedBigInteger('resuelto_por')->nullable();
            $table->timestamp('resuelto_at')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'empleado_id', 'estado']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_vacaciones');
    }
};
