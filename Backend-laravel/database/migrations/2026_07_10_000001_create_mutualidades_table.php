<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica los dos esquemas de código de mutualidad que coexistían sin relación entre sí:
 * `parametros_previsionales.mutual_codigo` (01=ACHS/02=ISL/03=Mutual, global) usado por
 * PreviredService, y `empleados.organismo_mutual_codigo` (1=ACHS/2=IST/3=MUTUAL/4=CChC, por
 * trabajador) usado por GenerarLreService — el código "2" significaba organismos distintos en
 * cada uno. La afiliación a una mutualidad (Ley 16.744) es una decisión por empresa, no por
 * trabajador ni un parámetro legal nacional único, así que este catálogo pasa a ser la fuente
 * única y se referencia desde `empresas`.
 *
 * Códigos LRE tomados de la migración 2026_06_16_000001 (ya en uso en producción, no se tocan).
 * Códigos Previred tomados del comentario preexistente en PreviredService (01/02/03) — quedan
 * marcados como pendientes de verificación contra la especificación oficial vigente en
 * previred.com (ver docs/integraciones/INVESTIGACION-SII-F7-Y-PREVIRED-MUTUALIDAD.md, sección 2.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutualidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('codigo_previred', 2)->nullable()->comment('Campo 59 archivo Previred — null donde el mapeo previo (01/02/03) no distingue el organismo sin ambigüedad; pendiente verificación oficial');
            $table->string('codigo_lre', 2)->comment('Código 1152 LRE (ya validado en producción)');
            $table->timestamps();
        });

        // ACHS (01) e ISL (02) son inequívocos en el esquema Previred previo. El esquema LRE
        // distingue además IST (3) y Mutual de Seguridad CChC (4) como organismos separados, pero
        // el esquema Previred previo solo tenía un tercer código genérico "03=Mutual" sin aclarar
        // a cuál de los dos se refería — no se adivina esa asignación aquí, queda codigo_previred
        // en null para ambos hasta confirmar contra la especificación oficial de Previred.
        DB::table('mutualidades')->insert([
            ['nombre' => 'ACHS', 'codigo_previred' => '01', 'codigo_lre' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'ISL', 'codigo_previred' => '02', 'codigo_lre' => '2', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'IST', 'codigo_previred' => null, 'codigo_lre' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Mutual de Seguridad CChC', 'codigo_previred' => null, 'codigo_lre' => '4', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('mutualidad_id')->nullable()->after('regimen_tributario')->constrained('mutualidades')->nullOnDelete();
            $table->decimal('mutual_tasa_adicional_pct', 7, 4)->default(0)->after('mutualidad_id')
                ->comment('Tasa adicional diferenciada Ley 16.744 según resolución de afiliación de la empresa; se suma a parametros_previsionales.mutual_cotizacion_basica_pct');
        });

        // Migra el dato existente por empleado a nivel de empresa: si todos los empleados de una
        // empresa comparten el mismo organismo_mutual_codigo, se asigna esa mutualidad a la
        // empresa. Si hay valores heterogéneos entre trabajadores de la misma empresa, se deja sin
        // asignar (null) — es una señal de datos inconsistentes que requiere revisión funcional,
        // no una decisión que la migración deba tomar por si sola.
        $codigoLreAMutualidadId = DB::table('mutualidades')->pluck('id', 'codigo_lre');

        $gruposPorEmpresa = DB::table('empleados')
            ->whereNotNull('organismo_mutual_codigo')
            ->select('empresa_id', 'organismo_mutual_codigo')
            ->distinct()
            ->get()
            ->groupBy('empresa_id');

        foreach ($gruposPorEmpresa as $empresaId => $filas) {
            if ($filas->count() !== 1) {
                continue;
            }

            $codigoLre = (string) $filas->first()->organismo_mutual_codigo;
            $mutualidadId = $codigoLreAMutualidadId[$codigoLre] ?? null;

            if ($mutualidadId) {
                DB::table('empresas')->where('id', $empresaId)->update(['mutualidad_id' => $mutualidadId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mutualidad_id');
            $table->dropColumn('mutual_tasa_adicional_pct');
        });

        Schema::dropIfExists('mutualidades');
    }
};
