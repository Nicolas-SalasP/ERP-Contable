<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas contables que participan en el cálculo de Corrección Monetaria.
 *
 * El Art. 41 LIR define categorías de "activos no monetarios" que se deben
 * revalorizar. Esta tabla mapea cada cuenta del plan de la empresa a su
 * rol en el cálculo.
 *
 * Roles posibles (enum rol_cm):
 *
 * ACTIVO_NO_MONETARIO:
 *   Bienes físicos que se revalorizan con inflación.
 *   Ej: Edificios, Maquinarias, Vehículos, Hardware, Muebles.
 *   Asiento: DEBE cuenta → HABER cuenta_resultado_activos (811001)
 *
 * DEPRECIACION_ACUMULADA:
 *   Contrapartida de activos fijos. La depreciación acumulada también
 *   se revaloriza para mantener consistencia con el activo bruto.
 *   Asiento: DEBE cuenta_resultado_depreciacion (821001) → HABER cuenta
 *
 * INVENTARIO:
 *   Existencias de mercaderías. Tratamiento especial: el factor IPC se
 *   aplica mes a mes según la data de ingreso al inventario.
 *   En v1 se usa el factor anual simplificado.
 *   Asiento: DEBE cuenta → HABER cuenta_resultado_existencias (811002)
 *
 * PATRIMONIO_CAPITAL:
 *   Capital pagado, reservas y resultados acumulados del ejercicio anterior.
 *   La revalorización del patrimonio genera GASTO (no ingreso).
 *   Asiento: DEBE cuenta_resultado_patrimonio (311406) → HABER cuenta
 *
 * PASIVO_NO_MONETARIO:
 *   Pasivos indexados (UF, UTM). En v1 se deja preparado pero no se calcula
 *   automáticamente (requiere marcar cada pasivo individualmente).
 *   Asiento: DEBE cuenta_resultado_pasivos (821002) → HABER cuenta
 *
 * Campos:
 * - empresa_id + cuenta_codigo: unique → una empresa no puede tener la misma
 *   cuenta con dos roles distintos.
 * - aplica: permite desactivar una cuenta sin eliminarla (el usuario puede
 *   excluir una cuenta específica sin cambiar la configuración global).
 * - factor_override: si se quiere usar un factor diferente al IPC global
 *   para esta cuenta específica. NULL = usa el IPC global de la empresa.
 *   Útil para activos en UF (tienen su propio factor de reajuste).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cm_configuracion_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('cuenta_codigo', 20);
            $table->enum('rol_cm', [
                'ACTIVO_NO_MONETARIO',
                'DEPRECIACION_ACUMULADA',
                'INVENTARIO',
                'PATRIMONIO_CAPITAL',
                'PASIVO_NO_MONETARIO',
            ]);
            $table->boolean('aplica')->default(true);

            // Override de factor IPC para esta cuenta específica (para activos en UF/UTM).
            // NULL = usa el IPC del período en cm_indices_ipc.
            $table->decimal('factor_override', 10, 6)->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'cuenta_codigo']);
            $table->index(['empresa_id', 'rol_cm']);
            $table->index(['empresa_id', 'aplica']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_configuracion_cuentas');
    }
};
