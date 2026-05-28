<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de revalorización por CM a la tabla activos_fijos.
 *
 * Problema que resuelve:
 * El activo fijo tiene `valor_adquisicion` (costo histórico) y
 * `depreciacion_acumulada` (acumulada histórica). Cuando aplicamos CM,
 * AMBOS valores se revalorizan, pero no queremos pisar los originales
 * porque son el registro contable oficial e histórico.
 *
 * Solución: campos adicionales que acumulan el ajuste por CM.
 *
 * Campos:
 * - cm_ajuste_acumulado: suma histórica de todos los ajustes CM aplicados
 *   al valor bruto del activo. El "valor tributario" del activo es:
 *   valor_adquisicion + cm_ajuste_acumulado
 * - cm_depreciacion_ajuste_acumulado: suma histórica de los ajustes CM
 *   a la depreciación acumulada. La "depreciación tributaria" es:
 *   depreciacion_acumulada + cm_depreciacion_ajuste_acumulado
 * - ultimo_periodo_cm_mes / anio: el último período en que se aplicó CM
 *   a este activo. Para reportes y para el servicio de CM que necesita
 *   saber desde dónde calcular.
 *
 * Ejemplo de uso:
 *   Activo: Edificio, valor_adquisicion = $100.000.000
 *   Después de CM año 2024 (5%): cm_ajuste_acumulado = $5.000.000
 *   Después de CM año 2025 (4%): cm_ajuste_acumulado = $5.000.000 + $4.200.000 = $9.200.000
 *   → El valor tributario es $109.200.000
 *
 * Esto es distinto al valor contable IFRS (que usa revaluación a valor justo),
 * pero para PYMEs chilenas bajo Art. 41 LIR es el método correcto.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('activos_fijos', function (Blueprint $table) {
            $table->decimal('cm_ajuste_acumulado', 15, 2)->default(0)
                  ->after('depreciacion_acumulada')
                  ->comment('Suma acumulada de ajustes CM al valor bruto del activo');

            $table->decimal('cm_depreciacion_ajuste_acumulado', 15, 2)->default(0)
                  ->after('cm_ajuste_acumulado')
                  ->comment('Suma acumulada de ajustes CM a la depreciacion acumulada');

            $table->unsignedTinyInteger('ultimo_periodo_cm_mes')->nullable()
                  ->after('cm_depreciacion_ajuste_acumulado')
                  ->comment('Mes del ultimo cierre CM aplicado a este activo');

            $table->unsignedSmallInteger('ultimo_periodo_cm_anio')->nullable()
                  ->after('ultimo_periodo_cm_mes')
                  ->comment('Ano del ultimo cierre CM aplicado a este activo');

            $table->index(['empresa_id', 'ultimo_periodo_cm_anio', 'ultimo_periodo_cm_mes'],
                          'idx_activos_ultimo_cm');
        });
    }

    public function down(): void
    {
        Schema::table('activos_fijos', function (Blueprint $table) {
            $table->dropIndex('idx_activos_ultimo_cm');
            $table->dropColumn([
                'cm_ajuste_acumulado',
                'cm_depreciacion_ajuste_acumulado',
                'ultimo_periodo_cm_mes',
                'ultimo_periodo_cm_anio',
            ]);
        });
    }
};
