<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula facturas con su SiiDteEmitido (1:1 opcional, null si aun no se emite).
 * Idempotente: defensivo si la migracion corre dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('facturas', 'sii_dte_emitido_id')) {
            return;
        }

        Schema::table('facturas', function (Blueprint $table) {
            $table->foreignId('sii_dte_emitido_id')
                ->nullable()
                ->after('emitir_dte_automatico')
                ->constrained('sii_dte_emitido')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('facturas', 'sii_dte_emitido_id')) {
            return;
        }

        Schema::table('facturas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sii_dte_emitido_id');
        });
    }
};
