<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración de Corrección Monetaria por empresa.
 *
 * Cada empresa tiene su propia configuración. Esto es fundamental porque:
 * - Las empresas con régimen 14_D8 (Pro Pyme Transparente) no aplican CM.
 * - Algunas empresas prefieren CM mensual para reportes de gestión aunque
 *   tributariamente solo sea obligatorio el ajuste anual.
 * - Las cuentas contables de CM pueden diferir entre empresas si personalizaron
 *   su plan de cuentas.
 *
 * Campos:
 * - empresa_id: 1-a-1 con empresas.
 * - aplica_cm: false para empresas 14_D8. El sistema muestra advertencia
 *   pero no bloquea (puede haber motivos internos para usarla igual).
 * - modalidad: 'mensual' → permite ejecutar CM en cualquier mes.
 *             'anual'   → solo diciembre. Pero SIEMPRE se puede simular.
 * - mes_cierre: mes en que se ejecuta el cierre anual (default: 12 = diciembre).
 *   Existen empresas con año comercial diferente (año agrícola = abril).
 * - cuenta_resultado_activos_codigo: cuenta a usar como contrapartida de
 *   activos no monetarios. Default: 811001 (CM Activos).
 * - cuenta_resultado_depreciacion_codigo: contrapartida de dep. acumulada.
 *   Default: 821001 (CM Depreciación).
 * - cuenta_resultado_patrimonio_codigo: para revalorizar patrimonio.
 *   Default: 311406 (CM Patrimonio, ya existente en plan maestro).
 * - cuenta_resultado_existencias_codigo: para inventarios.
 *   Default: 811002 (CM Existencias).
 * - activo: si la empresa tiene CM activa o la deshabilitó.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cm_configuracion_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $table->boolean('aplica_cm')->default(true);
            $table->enum('modalidad', ['mensual', 'anual'])->default('anual');
            $table->unsignedTinyInteger('mes_cierre')->default(12);   // 1-12

            // Cuentas contables usadas en los asientos de CM.
            // Se guardan como string (codigo) para consistencia con el resto del ERP.
            $table->string('cuenta_activos_codigo', 20)->default('811001');
            $table->string('cuenta_depreciacion_codigo', 20)->default('821001');
            $table->string('cuenta_patrimonio_codigo', 20)->default('311406');
            $table->string('cuenta_existencias_codigo', 20)->default('811002');
            $table->string('cuenta_pasivos_codigo', 20)->default('821002');

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_configuracion_empresa');
    }
};
