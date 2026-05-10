<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migracion de multi-tenancy fixes.
 *
 * Cambios:
 * 1. activos_fijos.codigo: unique global -> unique compuesto (empresa_id, codigo).
 *    Antes: si empresa A tenia AF-00001, empresa B no podia tener AF-00001.
 *    Ahora: cada empresa puede tener AF-00001, AF-00002... independientes.
 *
 * Razonamiento: el codigo es identificador de negocio para auditoria,
 * no un id interno. Cada empresa debe poder llevar su propia secuencia.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // 1. activos_fijos.codigo
        if (in_array($driver, ['mysql', 'mariadb'])) {
            // En MySQL hay que dropear el unique antes de crear el compuesto
            try {
                DB::statement('ALTER TABLE activos_fijos DROP INDEX activos_fijos_codigo_unique');
            } catch (\Throwable $e) {
                // Index puede tener nombre distinto; intentar con detector
                $indexes = DB::select("SHOW INDEX FROM activos_fijos WHERE Column_name = 'codigo' AND Non_unique = 0");
                foreach ($indexes as $idx) {
                    if ($idx->Key_name !== 'PRIMARY') {
                        DB::statement("ALTER TABLE activos_fijos DROP INDEX {$idx->Key_name}");
                    }
                }
            }

            Schema::table('activos_fijos', function (Blueprint $table) {
                $table->unique(['empresa_id', 'codigo'], 'idx_activos_empresa_codigo');
            });
        } elseif ($driver === 'sqlite') {
            // SQLite no soporta DROP INDEX por nombre auto-generado de Laravel facilmente.
            // Por suerte, en SQLite el unique se maneja como constraint de tabla.
            // Approach: recrear la tabla con el nuevo unique compuesto.
            Schema::table('activos_fijos', function (Blueprint $table) {
                $table->dropUnique(['codigo']);
                $table->unique(['empresa_id', 'codigo'], 'idx_activos_empresa_codigo');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE activos_fijos DROP INDEX idx_activos_empresa_codigo');
            Schema::table('activos_fijos', function (Blueprint $table) {
                $table->unique('codigo');
            });
        } elseif ($driver === 'sqlite') {
            Schema::table('activos_fijos', function (Blueprint $table) {
                $table->dropUnique('idx_activos_empresa_codigo');
                $table->unique('codigo');
            });
        }
    }
};
