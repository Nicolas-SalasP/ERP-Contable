<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un ajuste crítico positivo sobre un producto FIFO crea una capa de valorización nueva
 * (InventarioValorizacionCapa). Al anular ese ajuste, la reversa debe eliminar exactamente
 * esa capa, no una salida FIFO cronológica genérica (que puede consumir stock más antiguo
 * y dejar la capa del ajuste anulado intacta y huérfana). Esta columna guarda esa referencia
 * en el momento en que el ajuste crítico se registra, para poder ubicarla sin ambigüedad
 * al anular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_ajustes_criticos', function (Blueprint $table) {
            $table->foreignId('valorizacion_capa_id')
                ->nullable()
                ->after('movimiento_reversa_id')
                ->constrained('inventario_valorizacion_capas')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_ajustes_criticos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valorizacion_capa_id');
        });
    }
};
