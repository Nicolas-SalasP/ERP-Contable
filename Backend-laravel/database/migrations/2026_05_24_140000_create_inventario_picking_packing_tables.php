<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_picking_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('bodega_id')->constrained('inventario_bodegas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('inventario_reservas')->cascadeOnUpdate()->nullOnDelete();
            $table->string('codigo', 60);
            $table->enum('estado', ['BORRADOR', 'PENDIENTE', 'EN_PREPARACION', 'PICKING_COMPLETO', 'CON_DIFERENCIAS', 'CANCELADO'])->default('PENDIENTE');
            $table->enum('prioridad', ['BAJA', 'NORMAL', 'ALTA', 'URGENTE'])->default('NORMAL');
            $table->string('referencia', 120)->nullable();
            $table->string('motivo', 120)->nullable();
            $table->text('observacion')->nullable();
            $table->string('origen_modulo', 80)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->unsignedBigInteger('usuario_creador_id')->nullable()->index();
            $table->unsignedBigInteger('usuario_asignado_id')->nullable()->index();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_asignacion')->nullable();
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo'], 'inv_pick_ord_empresa_codigo_uq');
            $table->index(['empresa_id', 'estado'], 'idx_inv_pick_ord_empresa_estado');
            $table->index(['empresa_id', 'bodega_id', 'estado'], 'idx_inv_pick_ord_empresa_bodega_estado');
            $table->index(['empresa_id', 'origen_modulo', 'origen_id'], 'idx_inv_pick_ord_empresa_origen');
        });

        Schema::create('inventario_picking_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('picking_orden_id')->constrained('inventario_picking_ordenes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('reserva_detalle_id')->nullable()->constrained('inventario_reserva_detalles')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('inventario_bodegas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('ubicacion_origen_id')->nullable()->constrained('inventario_ubicaciones')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('inventario_lotes')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('cantidad_solicitada', 18, 4);
            $table->decimal('cantidad_asignada', 18, 4)->default(0);
            $table->decimal('cantidad_pickeada', 18, 4)->default(0);
            $table->decimal('cantidad_faltante', 18, 4)->default(0);
            $table->decimal('cantidad_cancelada', 18, 4)->default(0);
            $table->enum('estado', ['PENDIENTE', 'PARCIAL', 'COMPLETO', 'SIN_STOCK', 'CANCELADO'])->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'picking_orden_id'], 'idx_inv_pick_det_empresa_orden');
            $table->index(['empresa_id', 'producto_id', 'bodega_id'], 'idx_inv_pick_det_empresa_producto_bodega');
            $table->index(['empresa_id', 'ubicacion_origen_id'], 'idx_inv_pick_det_empresa_ubicacion');
            $table->index(['empresa_id', 'lote_id'], 'idx_inv_pick_det_empresa_lote');
        });

        Schema::create('inventario_picking_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('picking_orden_id')->constrained('inventario_picking_ordenes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('picking_detalle_id')->constrained('inventario_picking_detalles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('reserva_detalle_id')->nullable()->constrained('inventario_reserva_detalles')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('inventario_bodegas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('ubicacion_origen_id')->constrained('inventario_ubicaciones')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('inventario_lotes')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('cantidad_asignada', 18, 4);
            $table->decimal('cantidad_pickeada', 18, 4)->default(0);
            $table->decimal('cantidad_faltante', 18, 4)->default(0);
            $table->enum('estado', ['PENDIENTE', 'PARCIAL', 'COMPLETO', 'SIN_STOCK', 'CANCELADO'])->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'picking_orden_id'], 'idx_inv_pick_asig_empresa_orden');
            $table->index(['empresa_id', 'picking_detalle_id'], 'idx_inv_pick_asig_empresa_detalle');
            $table->index(['empresa_id', 'producto_id', 'bodega_id'], 'idx_inv_pick_asig_empresa_producto_bodega');
            $table->index(['empresa_id', 'ubicacion_origen_id'], 'idx_inv_pick_asig_empresa_ubicacion');
            $table->index(['empresa_id', 'lote_id'], 'idx_inv_pick_asig_empresa_lote');
        });

        Schema::create('inventario_packing_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('picking_orden_id')->constrained('inventario_picking_ordenes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('inventario_bodegas')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('codigo', 60);
            $table->enum('estado', ['PENDIENTE', 'EN_EMPAQUE', 'EMPACADO', 'CON_DIFERENCIAS', 'CANCELADO'])->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_creador_id')->nullable()->index();
            $table->unsignedBigInteger('usuario_confirmador_id')->nullable()->index();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo'], 'inv_pack_ord_empresa_codigo_uq');
            $table->unique(['empresa_id', 'picking_orden_id'], 'inv_pack_ord_empresa_picking_uq');
            $table->index(['empresa_id', 'estado'], 'idx_inv_pack_ord_empresa_estado');
            $table->index(['empresa_id', 'bodega_id', 'estado'], 'idx_inv_pack_ord_empresa_bodega_estado');
        });

        Schema::create('inventario_packing_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('packing_orden_id')->constrained('inventario_packing_ordenes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('picking_detalle_id')->nullable()->constrained('inventario_picking_detalles')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('picking_asignacion_id')->nullable()->constrained('inventario_picking_asignaciones')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('ubicacion_origen_id')->nullable()->constrained('inventario_ubicaciones')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('inventario_lotes')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('cantidad_pickeada', 18, 4);
            $table->decimal('cantidad_empacada', 18, 4)->default(0);
            $table->decimal('cantidad_faltante', 18, 4)->default(0);
            $table->enum('estado', ['PENDIENTE', 'PARCIAL', 'EMPACADO', 'CON_DIFERENCIAS', 'CANCELADO'])->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'packing_orden_id'], 'idx_inv_pack_det_empresa_orden');
            $table->index(['empresa_id', 'picking_asignacion_id'], 'idx_inv_pack_det_empresa_asignacion');
            $table->index(['empresa_id', 'producto_id'], 'idx_inv_pack_det_empresa_producto');
            $table->index(['empresa_id', 'ubicacion_origen_id'], 'idx_inv_pack_det_empresa_ubicacion');
            $table->index(['empresa_id', 'lote_id'], 'idx_inv_pack_det_empresa_lote');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_packing_detalles');
        Schema::dropIfExists('inventario_packing_ordenes');
        Schema::dropIfExists('inventario_picking_asignaciones');
        Schema::dropIfExists('inventario_picking_detalles');
        Schema::dropIfExists('inventario_picking_ordenes');
    }
};
