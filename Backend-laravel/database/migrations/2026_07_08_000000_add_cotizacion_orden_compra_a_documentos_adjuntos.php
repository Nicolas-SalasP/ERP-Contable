<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un documento adjunto ahora puede pertenecer a una Factura, una Cotizacion o una
 * OrdenCompra (exactamente una de las tres). factura_id pasa a ser opcional y se
 * agregan las columnas nullable cotizacion_id / orden_compra_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // MODIFY directo: ->change() de una columna con FK requiere doctrine/dbal, que no esta instalado.
            DB::statement('ALTER TABLE documentos_adjuntos MODIFY factura_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('documentos_adjuntos', function (Blueprint $table) {
                $table->unsignedBigInteger('factura_id')->nullable()->change();
            });
        }

        Schema::table('documentos_adjuntos', function (Blueprint $table) {
            $table->foreignId('cotizacion_id')->nullable()->after('factura_id')->constrained('cotizaciones')->onDelete('cascade');
            $table->foreignId('orden_compra_id')->nullable()->after('cotizacion_id')->constrained('ordenes_compra')->onDelete('cascade');
            $table->index(['empresa_id', 'cotizacion_id']);
            $table->index(['empresa_id', 'orden_compra_id']);
        });
    }

    public function down(): void
    {
        Schema::table('documentos_adjuntos', function (Blueprint $table) {
            $table->dropForeign(['cotizacion_id']);
            $table->dropForeign(['orden_compra_id']);
            $table->dropColumn(['cotizacion_id', 'orden_compra_id']);
        });

        // No se revierte factura_id a NOT NULL: irreversible de forma segura entre drivers.
    }
};
