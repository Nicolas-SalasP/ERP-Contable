<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conecta la línea de una cotización con el producto real de Inventario (opcional: los
     * ítems tipo servicio no llevan producto_id y por lo tanto nunca disparan movimientos de
     * inventario). También guarda el movimiento de salida generado al facturar, para poder
     * revertirlo si la factura se anula (ver CotizacionService::convertirEnFactura y
     * FacturaService::anularFactura).
     */
    public function up(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->foreignId('producto_id')
                ->nullable()
                ->after('cotizacion_id')
                ->constrained('inventario_productos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('movimiento_inventario_id')
                ->nullable()
                ->after('producto_id')
                ->constrained('inventario_movimientos')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_inventario_id');
            $table->dropConstrainedForeignId('producto_id');
        });
    }
};
