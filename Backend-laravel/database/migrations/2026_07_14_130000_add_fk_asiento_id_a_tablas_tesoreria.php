<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * movimientos_bancarios.asiento_id, anticipos_proveedores.asiento_id y
 * anticipos_clientes.asiento_id nunca tuvieron FK real hacia asientos_contables
 * -- integridad referencial dependia 100% de que el codigo de aplicacion nunca
 * escribiera un id inexistente. nullOnDelete() porque el asiento puede
 * reversarse/eliminarse sin que el movimiento/anticipo deba desaparecer con el.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Limpia huerfanos preexistentes antes del constraint (si los hay, el
        // ADD CONSTRAINT fallaria de otro modo).
        DB::table('movimientos_bancarios')
            ->whereNotNull('asiento_id')
            ->whereNotIn('asiento_id', DB::table('asientos_contables')->select('id'))
            ->update(['asiento_id' => null]);
        DB::table('anticipos_proveedores')
            ->whereNotNull('asiento_id')
            ->whereNotIn('asiento_id', DB::table('asientos_contables')->select('id'))
            ->update(['asiento_id' => null]);
        DB::table('anticipos_clientes')
            ->whereNotNull('asiento_id')
            ->whereNotIn('asiento_id', DB::table('asientos_contables')->select('id'))
            ->update(['asiento_id' => null]);

        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->foreign('asiento_id')->references('id')->on('asientos_contables')->nullOnDelete();
        });
        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            $table->foreign('asiento_id')->references('id')->on('asientos_contables')->nullOnDelete();
        });
        Schema::table('anticipos_clientes', function (Blueprint $table) {
            $table->foreign('asiento_id')->references('id')->on('asientos_contables')->nullOnDelete();
        });

        // SQLite no soporta ADD CONSTRAINT: Schema::table() reconstruye la tabla entera
        // (copia a una temporal, dropea la original, renombra) para simular el ALTER TABLE.
        // Esa reconstruccion NO preserva triggers -- se pierden silenciosamente los que
        // 2026_05_08_150003_add_robustness_constraints.php crea para emular el CHECK
        // "cargo XOR abono" en movimientos_bancarios. Hay que recrearlos aqui.
        if (DB::connection()->getDriverName() === 'sqlite') {
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
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->dropForeign(['asiento_id']);
        });
        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            $table->dropForeign(['asiento_id']);
        });
        Schema::table('anticipos_clientes', function (Blueprint $table) {
            $table->dropForeign(['asiento_id']);
        });
    }
};
