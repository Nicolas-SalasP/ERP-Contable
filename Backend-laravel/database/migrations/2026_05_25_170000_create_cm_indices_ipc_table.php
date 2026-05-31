<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de índices IPC mensuales para el módulo de Corrección Monetaria.
 *
 * El IPC (Índice de Precios al Consumidor) es publicado mensualmente por el
 * INE (Instituto Nacional de Estadísticas de Chile).
 *
 * Diseño dual (ingreso manual + preparado para API futura):
 * - fuente = 'manual'  → usuario ingresó el valor desde la interfaz
 * - fuente = 'api_ine' → consumido automáticamente desde la API del INE
 *   La columna `url_respuesta_api` guarda la URL exacta consultada para
 *   trazabilidad y debugging de la integración futura.
 *
 * Campos:
 * - anio / mes: identifican el período. UNIQUE para evitar duplicados.
 * - variacion_mensual: la variación % del IPC en ese mes específico.
 *   Ej: 0.4200 = 0.42% de inflación en ese mes.
 *   Puede ser negativa (deflación).
 * - variacion_acumulada_anual: IPC acumulado enero→mes, calculado al ingresar.
 *   Permite calcular CM sin rehacer todos los meses anteriores.
 *   Ej: al ingresar mes 8, se suma variación_mensual meses 1-8.
 * - factor_multiplicador: (1 + variacion_mensual/100). Pre-calculado para
 *   performance y para evitar errores de precisión en cálculos en cadena.
 *   Ej: 0.42% → factor = 1.004200
 * - fuente: origen del dato ('manual' | 'api_ine')
 * - url_respuesta_api: URL de la API del INE consultada (null si manual).
 *   Útil para auditoría y para reproducir la consulta si hay discrepancias.
 * - observacion: nota libre del usuario. Útil para documentar IPC atípicos
 *   o ajustes retroactivos.
 * - creado_por_usuario_id: quién ingresó el dato.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cm_indices_ipc', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');    // 1-12
            $table->decimal('variacion_mensual', 8, 4);        // Ej: 0.4200 (= 0.42%)
            $table->decimal('variacion_acumulada_anual', 8, 4)->default(0); // Acumulado YTD
            $table->decimal('factor_multiplicador', 10, 6)->default(1.000000); // 1 + var/100
            $table->enum('fuente', ['manual', 'api_ine'])->default('manual');
            $table->string('url_respuesta_api', 500)->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('creado_por_usuario_id')->nullable();
            $table->timestamps();

            // Un índice por mes/año. Si ya existe, se actualiza (no se duplica).
            $table->unique(['anio', 'mes']);
            $table->index('anio');

            $table->foreign('creado_por_usuario_id')
                  ->references('id')->on('usuarios')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_indices_ipc');
    }
};
