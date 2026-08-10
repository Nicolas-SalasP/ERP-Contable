<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inventario_devolucion_ordenes/detalles nacieron acopladas 1:1 a un despacho fisico
 * (InventarioDespachoOrden) -- flujo interno de picking/packing. Las devoluciones que entran por
 * Integraciones (Fase 4 RMA) no tienen despacho: la venta de Fase 2 sale directo de una reserva
 * consumida (VentaIntegracionService::confirmar), sin pasar por el modulo de despachos. Se
 * relaja la nulabilidad de ambas FK para permitir ese origen alternativo (la constraint de FK en
 * si no impide nulls, solo restringe el valor si esta presente); el flujo despacho-based
 * existente no cambia (siempre informa despacho_orden_id/despacho_detalle_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_devolucion_ordenes', function (Blueprint $table) {
            $table->unsignedBigInteger('despacho_orden_id')->nullable()->change();
        });

        Schema::table('inventario_devolucion_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('despacho_detalle_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_devolucion_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('despacho_detalle_id')->nullable(false)->change();
        });

        Schema::table('inventario_devolucion_ordenes', function (Blueprint $table) {
            $table->unsignedBigInteger('despacho_orden_id')->nullable(false)->change();
        });
    }
};
