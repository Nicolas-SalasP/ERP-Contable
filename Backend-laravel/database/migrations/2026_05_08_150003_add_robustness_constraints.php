<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de robustez para sistema multi-tenant + integridad contable.
 *
 * Cambios:
 * 1. asientos_contables: numero_comprobante UNIQUE por empresa (antes podia duplicarse).
 * 2. activos_fijos: depreciacion_acumulada >= 0 via CHECK constraint (MySQL 8.0+).
 *
 * Estos cambios previenen escenarios de produccion donde la BD permitia
 * datos contablemente imposibles que despues generaban reportes incorrectos.
 */
return new class extends Migration {

    public function up(): void
    {
        // ============================================================
        // FIX 1: numero_comprobante unique por empresa
        // ============================================================
        // Antes podian existir 2 asientos con mismo numero_comprobante en la
        // misma empresa, lo cual rompe trazabilidad contable.
        Schema::table('asientos_contables', function (Blueprint $table) {
            // Si ya existe un index unico simple sobre numero_comprobante, lo dejamos
            // y agregamos el compuesto. Si solo existe non-unique, no tocamos.
            $table->unique(['empresa_id', 'numero_comprobante'], 'asientos_empresa_comprobante_unique');
        });

        // ============================================================
        // FIX 2: depreciacion_acumulada >= 0
        // ============================================================
        // La depreciacion contable nunca puede ser negativa por definicion.
        // Si lo es, hay un bug en la logica de calculo.
        // Usamos CHECK constraint compatible con MySQL 8.0+ y SQLite.
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            // MySQL 8.0+ y MariaDB 10.2+ soportan CHECK constraints
            DB::statement('
                ALTER TABLE activos_fijos
                ADD CONSTRAINT chk_depreciacion_no_negativa
                CHECK (depreciacion_acumulada >= 0)
            ');

            // ============================================================
            // FIX 3: movimientos_bancarios no permite cargo Y abono simultaneos
            // ============================================================
            // Un movimiento bancario debe ser entrada O salida, nunca las dos.
            // Si tiene los dos > 0, los reportes de cuadre se vuelven imposibles.
            DB::statement('
                ALTER TABLE movimientos_bancarios
                ADD CONSTRAINT chk_movimiento_cargo_xor_abono
                CHECK (NOT (cargo > 0 AND abono > 0))
            ');
        } elseif ($driver === 'sqlite') {
            // SQLite no permite ALTER TABLE ADD CONSTRAINT.
            // Usamos triggers como equivalente.
            DB::statement('
                CREATE TRIGGER IF NOT EXISTS check_dep_no_negativa_insert
                BEFORE INSERT ON activos_fijos
                FOR EACH ROW
                WHEN NEW.depreciacion_acumulada < 0
                BEGIN
                    SELECT RAISE(ABORT, "depreciacion_acumulada no puede ser negativa");
                END;
            ');
            DB::statement('
                CREATE TRIGGER IF NOT EXISTS check_dep_no_negativa_update
                BEFORE UPDATE ON activos_fijos
                FOR EACH ROW
                WHEN NEW.depreciacion_acumulada < 0
                BEGIN
                    SELECT RAISE(ABORT, "depreciacion_acumulada no puede ser negativa");
                END;
            ');
            DB::statement('
                CREATE TRIGGER IF NOT EXISTS check_mov_cargo_xor_abono_insert
                BEFORE INSERT ON movimientos_bancarios
                FOR EACH ROW
                WHEN NEW.cargo > 0 AND NEW.abono > 0
                BEGIN
                    SELECT RAISE(ABORT, "movimiento no puede tener cargo y abono simultaneos");
                END;
            ');
            DB::statement('
                CREATE TRIGGER IF NOT EXISTS check_mov_cargo_xor_abono_update
                BEFORE UPDATE ON movimientos_bancarios
                FOR EACH ROW
                WHEN NEW.cargo > 0 AND NEW.abono > 0
                BEGIN
                    SELECT RAISE(ABORT, "movimiento no puede tener cargo y abono simultaneos");
                END;
            ');
        }
    }

    public function down(): void
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->dropUnique('asientos_empresa_comprobante_unique');
        });

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE activos_fijos DROP CHECK chk_depreciacion_no_negativa');
            DB::statement('ALTER TABLE movimientos_bancarios DROP CHECK chk_movimiento_cargo_xor_abono');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS check_dep_no_negativa_insert');
            DB::statement('DROP TRIGGER IF EXISTS check_dep_no_negativa_update');
            DB::statement('DROP TRIGGER IF EXISTS check_mov_cargo_xor_abono_insert');
            DB::statement('DROP TRIGGER IF EXISTS check_mov_cargo_xor_abono_update');
        }
    }
};
