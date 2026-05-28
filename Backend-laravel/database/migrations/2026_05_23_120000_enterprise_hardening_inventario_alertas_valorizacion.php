<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_lotes', function (Blueprint $table) {
            if (!Schema::hasColumn('inventario_lotes', 'estado_operativo')) {
                $table->string('estado_operativo', 30)
                    ->default('DISPONIBLE')
                    ->after('activo');

                $table->index(
                    ['empresa_id', 'estado_operativo'],
                    'idx_inv_lotes_empresa_estado_operativo'
                );
            }
        });

        Schema::create('inventario_alertas_estado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->string('severidad', 30)->default('media');
            $table->string('titulo', 180);
            $table->text('descripcion')->nullable();
            $table->foreignId('producto_id')->nullable()->constrained('inventario_productos')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('bodega_id')->nullable()->constrained('inventario_bodegas')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('inventario_lotes')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('cantidad_actual', 18, 4)->nullable();
            $table->decimal('stock_minimo', 18, 4)->nullable();
            $table->decimal('stock_objetivo', 18, 4)->nullable();
            $table->decimal('cantidad_sugerida', 18, 4)->nullable();
            $table->date('fecha_referencia')->nullable();
            $table->string('referencia', 120);
            $table->json('metadata')->nullable();
            $table->timestamp('calculado_en')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo', 'referencia'], 'inv_alertas_estado_empresa_tipo_ref_uq');
            $table->index(['empresa_id', 'severidad', 'tipo'], 'idx_inv_alertas_estado_empresa_severidad_tipo');
            $table->index(['empresa_id', 'producto_id'], 'idx_inv_alertas_estado_empresa_producto');
            $table->index(['empresa_id', 'bodega_id'], 'idx_inv_alertas_estado_empresa_bodega');
        });

        Schema::create('inventario_valorizacion_capas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('inventario_bodegas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('inventario_lotes')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('movimiento_origen_id')->nullable()->constrained('inventario_movimientos')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('cantidad_inicial', 18, 4);
            $table->decimal('cantidad_disponible', 18, 4);
            $table->decimal('costo_unitario', 18, 4);
            $table->decimal('valor_disponible', 18, 4);
            $table->dateTime('fecha_entrada');
            $table->string('estado', 30)->default('ABIERTA');
            $table->timestamps();

            $table->index(['empresa_id', 'producto_id', 'bodega_id', 'estado'], 'idx_inv_val_capas_empresa_producto_bodega_estado');
            $table->index(['empresa_id', 'producto_id', 'bodega_id', 'fecha_entrada'], 'idx_inv_val_capas_fifo');
            $table->index(['empresa_id', 'lote_id'], 'idx_inv_val_capas_empresa_lote');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_valorizacion_capas');
        Schema::dropIfExists('inventario_alertas_estado');

        Schema::table('inventario_lotes', function (Blueprint $table) {
            if (Schema::hasColumn('inventario_lotes', 'estado_operativo')) {
                $table->dropIndex('idx_inv_lotes_empresa_estado_operativo');
                $table->dropColumn('estado_operativo');
            }
        });
    }
};
