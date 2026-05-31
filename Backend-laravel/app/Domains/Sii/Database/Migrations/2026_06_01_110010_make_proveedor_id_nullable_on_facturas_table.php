<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relaja facturas.proveedor_id a nullable para permitir DTE de venta puros
 * (factura emitida a un cliente, sin proveedor asociado).
 *
 * El modelo legacy de Factura es bidireccional (campo `tipo` = COMPRA|VENTA)
 * pero la tabla heredo NOT NULL en proveedor_id por su origen orientado a
 * compras. Esta migracion lo desbloquea sin tocar mas nada del schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedBigInteger('proveedor_id')->nullable()->change();
        });
    }

    /**
     * ATENCION: este down() FALLARA con violacion NOT NULL si la tabla
     * contiene filas con proveedor_id IS NULL (DTE de venta SII ya emitidos).
     * Esto es el comportamiento correcto: una vez emitidos DTE legitimos
     * sin proveedor, esta migracion no debe revertirse.
     */
    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedBigInteger('proveedor_id')->nullable(false)->change();
        });
    }
};
