<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('bodega_id')
                ->constrained('inventario_bodegas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('ubicacion_padre_id')
                ->nullable()
                ->constrained('inventario_ubicaciones')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('codigo', 80);
            $table->string('nombre', 160);
            $table->enum('tipo', ['ZONA', 'PASILLO', 'ESTANTE', 'NIVEL', 'POSICION', 'UBICACION'])
                ->default('UBICACION');
            $table->string('pasillo', 40)->nullable();
            $table->string('estante', 40)->nullable();
            $table->string('nivel', 40)->nullable();
            $table->string('posicion', 40)->nullable();
            $table->decimal('capacidad_maxima', 18, 4)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'bodega_id', 'codigo'], 'inv_ubic_empresa_bodega_codigo_uq');
            $table->index(['empresa_id', 'bodega_id', 'activo'], 'idx_inv_ubic_empresa_bodega_activo');
            $table->index(['empresa_id', 'ubicacion_padre_id'], 'idx_inv_ubic_empresa_padre');
            $table->index(['empresa_id', 'tipo'], 'idx_inv_ubic_empresa_tipo');
        });

        Schema::create('inventario_stock_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('producto_id')
                ->constrained('inventario_productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('bodega_id')
                ->constrained('inventario_bodegas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('ubicacion_id')
                ->constrained('inventario_ubicaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('lote_id')
                ->nullable()
                ->constrained('inventario_lotes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedBigInteger('lote_key')->default(0);
            $table->decimal('stock_actual', 18, 4)->default(0);
            $table->decimal('stock_reservado', 18, 4)->default(0);
            $table->decimal('stock_bloqueado', 18, 4)->default(0);
            $table->decimal('stock_cuarentena', 18, 4)->default(0);
            $table->decimal('stock_en_transito', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(
                ['empresa_id', 'producto_id', 'bodega_id', 'ubicacion_id', 'lote_key'],
                'inv_stock_ubic_empresa_producto_bodega_ubic_lote_uq'
            );
            $table->index(['empresa_id', 'producto_id', 'bodega_id'], 'idx_inv_stock_ubic_empresa_producto_bodega');
            $table->index(['empresa_id', 'ubicacion_id'], 'idx_inv_stock_ubic_empresa_ubicacion');
            $table->index(['empresa_id', 'lote_id'], 'idx_inv_stock_ubic_empresa_lote');
        });

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->foreignId('ubicacion_origen_id')
                ->nullable()
                ->after('bodega_destino_id')
                ->constrained('inventario_ubicaciones')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('ubicacion_destino_id')
                ->nullable()
                ->after('ubicacion_origen_id')
                ->constrained('inventario_ubicaciones')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('estado_stock_origen', 40)->nullable()->after('stock_destino_despues');
            $table->string('estado_stock_destino', 40)->nullable()->after('estado_stock_origen');
            $table->index(['empresa_id', 'ubicacion_origen_id'], 'idx_inv_mov_empresa_ubic_origen');
            $table->index(['empresa_id', 'ubicacion_destino_id'], 'idx_inv_mov_empresa_ubic_destino');
        });

        Schema::table('inventario_reserva_detalles', function (Blueprint $table) {
            $table->foreignId('ubicacion_id')
                ->nullable()
                ->after('bodega_id')
                ->constrained('inventario_ubicaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('estado_stock', 40)->nullable()->after('lote_id');
            $table->index(['empresa_id', 'producto_id', 'bodega_id', 'ubicacion_id'], 'idx_inv_res_det_empresa_producto_bodega_ubic');
        });

        Schema::table('inventario_reserva_consumos', function (Blueprint $table) {
            $table->foreignId('ubicacion_id')
                ->nullable()
                ->after('bodega_id')
                ->constrained('inventario_ubicaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('estado_stock', 40)->nullable()->after('lote_id');
            $table->index(['empresa_id', 'ubicacion_id'], 'idx_inv_res_cons_empresa_ubicacion');
        });

        Schema::table('inventario_toma_fisica_detalles', function (Blueprint $table) {
            $table->foreignId('ubicacion_id')
                ->nullable()
                ->after('bodega_id')
                ->constrained('inventario_ubicaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('estado_stock', 40)->nullable()->after('lote_id');
            $table->index(['empresa_id', 'producto_id', 'bodega_id', 'ubicacion_id'], 'idx_inv_tf_det_empresa_producto_bodega_ubic');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_toma_fisica_detalles', function (Blueprint $table) {
            $table->dropIndex('idx_inv_tf_det_empresa_producto_bodega_ubic');
            $table->dropConstrainedForeignId('ubicacion_id');
            $table->dropColumn('estado_stock');
        });

        Schema::table('inventario_reserva_consumos', function (Blueprint $table) {
            $table->dropIndex('idx_inv_res_cons_empresa_ubicacion');
            $table->dropConstrainedForeignId('ubicacion_id');
            $table->dropColumn('estado_stock');
        });

        Schema::table('inventario_reserva_detalles', function (Blueprint $table) {
            $table->dropIndex('idx_inv_res_det_empresa_producto_bodega_ubic');
            $table->dropConstrainedForeignId('ubicacion_id');
            $table->dropColumn('estado_stock');
        });

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->dropIndex('idx_inv_mov_empresa_ubic_origen');
            $table->dropIndex('idx_inv_mov_empresa_ubic_destino');
            $table->dropConstrainedForeignId('ubicacion_origen_id');
            $table->dropConstrainedForeignId('ubicacion_destino_id');
            $table->dropColumn(['estado_stock_origen', 'estado_stock_destino']);
        });

        Schema::dropIfExists('inventario_stock_ubicaciones');
        Schema::dropIfExists('inventario_ubicaciones');
    }
};
