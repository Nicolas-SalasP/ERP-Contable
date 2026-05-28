<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_devolucion_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('despacho_orden_id')->constrained('inventario_despacho_ordenes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('inventario_bodegas')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('codigo', 60);
            $table->enum('tipo', ['DEVOLUCION', 'REVERSA_TOTAL', 'REVERSA_PARCIAL', 'DIFERENCIA_POST_DESPACHO']);
            $table->enum('estado', ['PENDIENTE', 'CONFIRMADA', 'CANCELADA', 'CON_DIFERENCIAS'])->default('PENDIENTE');
            $table->string('motivo', 120);
            $table->string('referencia', 120)->nullable();
            $table->text('observacion')->nullable();
            $table->string('origen_modulo', 80)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->unsignedBigInteger('usuario_creador_id')->nullable()->index();
            $table->unsignedBigInteger('usuario_confirmador_id')->nullable()->index();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo'], 'inv_dev_ord_empresa_codigo_uq');
            $table->index(['empresa_id', 'despacho_orden_id'], 'idx_inv_dev_ord_empresa_despacho');
            $table->index(['empresa_id', 'estado'], 'idx_inv_dev_ord_empresa_estado');
            $table->index(['empresa_id', 'tipo', 'estado'], 'idx_inv_dev_ord_empresa_tipo_estado');
            $table->index(['empresa_id', 'origen_modulo', 'origen_id'], 'idx_inv_dev_ord_empresa_origen');
        });

        Schema::create('inventario_devolucion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('devolucion_orden_id')->constrained('inventario_devolucion_ordenes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('despacho_detalle_id')->constrained('inventario_despacho_detalles')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('inventario_bodegas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('ubicacion_destino_id')->nullable()->constrained('inventario_ubicaciones')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('inventario_lotes')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('cantidad_despachada_original', 18, 4)->default(0);
            $table->decimal('cantidad_ya_reversada', 18, 4)->default(0);
            $table->decimal('cantidad_devolver', 18, 4)->default(0);
            $table->decimal('cantidad_aceptada', 18, 4)->default(0);
            $table->decimal('cantidad_rechazada', 18, 4)->default(0);
            $table->enum('estado', ['PENDIENTE', 'ACEPTADO', 'PARCIAL', 'RECHAZADO', 'CANCELADO'])->default('PENDIENTE');
            $table->string('motivo', 120)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('movimiento_inventario_id')->nullable()->constrained('inventario_movimientos')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'devolucion_orden_id'], 'idx_inv_dev_det_empresa_orden');
            $table->index(['empresa_id', 'despacho_detalle_id'], 'idx_inv_dev_det_empresa_desp_det');
            $table->index(['empresa_id', 'producto_id', 'bodega_id'], 'idx_inv_dev_det_empresa_producto_bod');
            $table->index(['empresa_id', 'ubicacion_destino_id'], 'idx_inv_dev_det_empresa_ubic_dest');
            $table->index(['empresa_id', 'lote_id'], 'idx_inv_dev_det_empresa_lote');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_devolucion_detalles');
        Schema::dropIfExists('inventario_devolucion_ordenes');
    }
};
