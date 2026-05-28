<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de ejecuciones de Corrección Monetaria.
 *
 * Sirve para 3 propósitos:
 * 1. Anti-doble-ejecución: antes de ejecutar CM, el servicio verifica que no
 *    exista un registro 'ejecutada' para ese período (mismo patrón que depreciarMes).
 * 2. Auditoría: quién ejecutó qué, cuándo, con qué factor IPC.
 * 3. Historial: el usuario puede ver todos los CMs ejecutados y navegar al asiento.
 *
 * Diseño de estados:
 * - 'simulada': corrida de previsualización. Se guarda para historial pero
 *   NO bloquea una ejecución real posterior. No genera asiento.
 * - 'ejecutada': contabilizada. BLOQUEA re-ejecución en el mismo período.
 *   Tiene asiento_id real.
 * - 'anulada': fue reversada. Requiere generar asiento de reversa manualmente
 *   (mismo flujo que anular cualquier asiento en el ERP).
 *   Desbloquea el período para una nueva ejecución.
 *
 * Campos de montos:
 * Guardamos subtotales por categoría para análisis y para que el usuario
 * entienda la composición de la CM sin tener que leer los detalles del asiento.
 * - total_ajuste_activos: monto total revalorizado en activos no monetarios
 * - total_ajuste_depreciacion: monto total ajustado en depreciación acumulada
 * - total_ajuste_patrimonio: monto total ajustado en patrimonio
 * - total_ajuste_existencias: monto total ajustado en inventarios
 * - total_ajuste_pasivos: monto total ajustado en pasivos
 * - total_cm_neto: debe == haber (validación de cuadre)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cm_ejecuciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedTinyInteger('periodo_mes');     // 1-12
            $table->unsignedSmallInteger('periodo_anio');
            $table->enum('tipo', ['mensual', 'anual']);
            $table->enum('estado', ['simulada', 'ejecutada', 'anulada'])->default('ejecutada');

            // Factor IPC aplicado (snapshot al momento de la ejecución)
            $table->decimal('factor_ipc_utilizado', 10, 6);
            $table->decimal('variacion_porcentual', 8, 4);  // Para display: "0.42%"

            // Montos por categoría (para análisis sin leer detalles del asiento)
            $table->decimal('total_ajuste_activos', 15, 2)->default(0);
            $table->decimal('total_ajuste_depreciacion', 15, 2)->default(0);
            $table->decimal('total_ajuste_patrimonio', 15, 2)->default(0);
            $table->decimal('total_ajuste_existencias', 15, 2)->default(0);
            $table->decimal('total_ajuste_pasivos', 15, 2)->default(0);
            $table->decimal('total_cm_neto', 15, 2)->default(0);  // Suma total (debe = haber)

            // Asiento contable generado (null para simulaciones)
            $table->foreignId('asiento_id')->nullable()->constrained('asientos_contables')->nullOnDelete();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            // Solo puede haber 1 ejecución 'ejecutada' por empresa/mes/año
            // Las simuladas y anuladas no bloquean
            $table->index(['empresa_id', 'periodo_anio', 'periodo_mes', 'estado']);
            $table->index(['empresa_id', 'estado']);

            $table->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_ejecuciones');
    }
};
