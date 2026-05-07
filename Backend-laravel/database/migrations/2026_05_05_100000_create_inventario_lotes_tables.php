<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Configuración de trazabilidad por lote en productos
        |--------------------------------------------------------------------------
        |
        | No rompe productos existentes:
        | - maneja_lotes = false
        | - requiere_fecha_vencimiento = false
        |
        */
        Schema::table('inventario_productos', function (Blueprint $table) {
            $table->boolean('maneja_lotes')
                ->default(false)
                ->after('permite_merma');

            $table->boolean('requiere_fecha_vencimiento')
                ->default(false)
                ->after('maneja_lotes');
        });

        /*
        |--------------------------------------------------------------------------
        | Lotes de inventario
        |--------------------------------------------------------------------------
        |
        | Un lote pertenece a una empresa y a un producto.
        | codigo_lote es único por empresa + producto.
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | No usar codigo_dte, codigo_sii, folio_dte ni lógica SII.
        |
        */
        Schema::create('inventario_lotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('producto_id')
                ->constrained('inventario_productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('codigo_lote', 80);
            $table->date('fecha_fabricacion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(
                ['empresa_id', 'producto_id', 'codigo_lote'],
                'inv_lotes_empresa_producto_codigo_lote_uq'
            );

            $table->index(
                ['empresa_id', 'producto_id'],
                'idx_inv_lotes_empresa_producto'
            );

            $table->index(
                ['empresa_id', 'activo'],
                'idx_inv_lotes_empresa_activo'
            );

            $table->index(
                ['fecha_vencimiento'],
                'idx_inv_lotes_fecha_vencimiento'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Stock granular por lote
        |--------------------------------------------------------------------------
        |
        | inventario_stock sigue siendo el stock consolidado.
        | inventario_stock_lotes solo guarda el desglose por lote/bodega.
        |
        */
        Schema::create('inventario_stock_lotes', function (Blueprint $table) {
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

            $table->foreignId('lote_id')
                ->constrained('inventario_lotes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('stock_actual', 18, 4)->default(0);

            $table->timestamps();

            $table->unique(
                ['empresa_id', 'producto_id', 'bodega_id', 'lote_id'],
                'inv_stock_lotes_empresa_producto_bodega_lote_uq'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'bodega_id'],
                'idx_inv_stock_lotes_empresa_producto_bodega'
            );

            $table->index(
                ['empresa_id', 'lote_id'],
                'idx_inv_stock_lotes_empresa_lote'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Detalle de lotes por movimiento
        |--------------------------------------------------------------------------
        |
        | inventario_movimientos sigue siendo la cabecera principal de Kardex.
        | Esta tabla guarda la trazabilidad granular del lote afectado.
        |
        */
        Schema::create('inventario_movimiento_lotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('movimiento_inventario_id')
                ->constrained('inventario_movimientos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('producto_id')
                ->constrained('inventario_productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('lote_id')
                ->constrained('inventario_lotes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('bodega_origen_id')
                ->nullable()
                ->constrained('inventario_bodegas')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('bodega_destino_id')
                ->nullable()
                ->constrained('inventario_bodegas')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->decimal('cantidad', 15, 4);

            $table->decimal('stock_lote_origen_antes', 15, 4)->nullable();
            $table->decimal('stock_lote_origen_despues', 15, 4)->nullable();

            $table->decimal('stock_lote_destino_antes', 15, 4)->nullable();
            $table->decimal('stock_lote_destino_despues', 15, 4)->nullable();

            $table->decimal('costo_unitario', 15, 4)->nullable();
            $table->decimal('costo_total', 15, 4)->nullable();

            $table->timestamps();

            $table->index(
                ['empresa_id', 'movimiento_inventario_id'],
                'idx_inv_mov_lotes_empresa_movimiento'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'lote_id'],
                'idx_inv_mov_lotes_empresa_producto_lote'
            );

            $table->index(
                ['empresa_id', 'lote_id', 'created_at'],
                'idx_inv_mov_lotes_empresa_lote_fecha'
            );

            $table->index(
                ['empresa_id', 'bodega_origen_id'],
                'idx_inv_mov_lotes_empresa_bodega_origen'
            );

            $table->index(
                ['empresa_id', 'bodega_destino_id'],
                'idx_inv_mov_lotes_empresa_bodega_destino'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Lote opcional en ajustes críticos
        |--------------------------------------------------------------------------
        |
        | Permite vincular mermas, deterioros, pérdidas o vencimientos
        | a un lote específico cuando el producto maneje trazabilidad por lote.
        |
        */
        Schema::table('inventario_ajustes_criticos', function (Blueprint $table) {
            $table->foreignId('lote_id')
                ->nullable()
                ->after('bodega_id')
                ->constrained('inventario_lotes')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(
                ['empresa_id', 'lote_id', 'created_at'],
                'idx_inv_ajuste_empresa_lote_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventario_ajustes_criticos', function (Blueprint $table) {
            $table->dropIndex('idx_inv_ajuste_empresa_lote_fecha');
            $table->dropForeign(['lote_id']);
            $table->dropColumn('lote_id');
        });

        Schema::dropIfExists('inventario_movimiento_lotes');
        Schema::dropIfExists('inventario_stock_lotes');
        Schema::dropIfExists('inventario_lotes');

        Schema::table('inventario_productos', function (Blueprint $table) {
            $table->dropColumn([
                'maneja_lotes',
                'requiere_fecha_vencimiento',
            ]);
        });
    }
};