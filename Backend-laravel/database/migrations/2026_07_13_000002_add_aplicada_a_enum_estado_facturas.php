<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ClienteService::compensarPartidas y ProveedorService::compensarPartidas ya
        // escriben 'estado' => 'APLICADA' en una nota de credito compensada contra una
        // factura, pero el enum original de la columna nunca incluyo ese valor: en MySQL
        // modo estricto esa escritura falla. SQLite (tests) no valida el enum, por eso el
        // gap no se detecto en la suite hasta correrla contra MySQL real. Mismo patron que
        // 2026_07_02_000001 (que agrego 'ABONADA' por la misma razon).
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE facturas MODIFY estado ENUM('BORRADOR', 'REGISTRADA', 'PAGADA', 'ABONADA', 'ANULADA', 'APLICADA') DEFAULT 'REGISTRADA'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $existenAplicadas = DB::table('facturas')->where('estado', 'APLICADA')->exists();
            if ($existenAplicadas) {
                throw new RuntimeException(
                    'No se puede revertir: existen facturas/notas de credito en estado APLICADA. '.
                    'Reasigna su estado manualmente antes de hacer rollback de esta migración.'
                );
            }

            DB::statement("ALTER TABLE facturas MODIFY estado ENUM('BORRADOR', 'REGISTRADA', 'PAGADA', 'ABONADA', 'ANULADA') DEFAULT 'REGISTRADA'");
        }
    }
};
