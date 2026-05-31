<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6.1 — Vincula facturas con su SiiDteEmitido (1:1 opcional).
 *
 * Migracion vive bajo app/Domains/Sii/Database/Migrations/ (cero archivos
 * del Comercial modificados) y solo agrega una columna FK. Cuando una
 * factura aun no se emite al SII, sii_dte_emitido_id es null.
 *
 * Idempotente: defensivo si la migracion corre dos veces tras un fix CI.
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
