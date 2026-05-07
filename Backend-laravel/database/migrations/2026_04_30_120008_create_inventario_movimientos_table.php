<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Multiempresa
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('empresa_id')->index();

            /*
            |--------------------------------------------------------------------------
            | Producto
            |--------------------------------------------------------------------------
            */
            $table->foreignId('producto_id')
                ->constrained('inventario_productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Tipo de movimiento
            |--------------------------------------------------------------------------
            |
            | Inventario NO emite, gestiona ni prepara DTE.
            |
            */
            $table->enum('tipo', [
                'entrada',
                'salida',
                'traspaso',
                'ajuste_positivo',
                'ajuste_negativo',
            ])->index();

            /*
            |--------------------------------------------------------------------------
            | Bodegas
            |--------------------------------------------------------------------------
            */
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

            /*
            |--------------------------------------------------------------------------
            | Cantidad
            |--------------------------------------------------------------------------
            */
            $table->decimal('cantidad', 15, 4);

            /*
            |--------------------------------------------------------------------------
            | Saldos para Kardex
            |--------------------------------------------------------------------------
            */
            $table->decimal('stock_origen_antes', 15, 4)->nullable();
            $table->decimal('stock_origen_despues', 15, 4)->nullable();

            $table->decimal('stock_destino_antes', 15, 4)->nullable();
            $table->decimal('stock_destino_despues', 15, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Valorización futura
            |--------------------------------------------------------------------------
            */
            $table->decimal('costo_unitario', 15, 4)->nullable();
            $table->decimal('costo_total', 15, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Referencias genéricas NO tributarias
            |--------------------------------------------------------------------------
            |
            | No usar codigo_dte, codigo_sii, folio_dte ni campos tributarios.
            |
            */
            $table->string('referencia', 120)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Motivo genérico
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | compra, venta_interna, merma, perdida, correccion_stock.
            |
            */
            $table->string('motivo', 80)->nullable();

            $table->text('observacion')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Auditoría
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamp('fecha_movimiento')->useCurrent();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */
            $table->index(
                ['empresa_id', 'producto_id', 'fecha_movimiento'],
                'idx_inv_mov_empresa_producto_fecha'
            );

            $table->index(
                ['empresa_id', 'tipo', 'fecha_movimiento'],
                'idx_inv_mov_empresa_tipo_fecha'
            );

            $table->index(
                ['empresa_id', 'bodega_origen_id'],
                'idx_inv_mov_empresa_bodega_origen'
            );

            $table->index(
                ['empresa_id', 'bodega_destino_id'],
                'idx_inv_mov_empresa_bodega_destino'
            );

            $table->index(
                ['empresa_id', 'created_at'],
                'idx_inv_mov_empresa_created_at'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};