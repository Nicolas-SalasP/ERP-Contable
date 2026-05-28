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
        | Reglas de reposición de inventario
        |--------------------------------------------------------------------------
        |
        | La regla puede ser global por producto o específica por bodega.
        | Inventario solo sugiere reposición y alerta riesgos operativos.
        | No crea órdenes de compra, asientos contables ni documentos SII/DTE.
        |
        */
        Schema::create('inventario_reglas_reposicion', function (Blueprint $table) {
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
                ->nullable()
                ->constrained('inventario_bodegas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('stock_minimo', 18, 4)->default(0);
            $table->decimal('stock_objetivo', 18, 4)->default(0);
            $table->decimal('punto_reorden', 18, 4)->nullable();
            $table->unsignedInteger('dias_alerta_vencimiento')->default(30);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(
                ['empresa_id', 'producto_id', 'bodega_id'],
                'inv_reglas_repo_empresa_producto_bodega_uq'
            );

            $table->index(
                ['empresa_id', 'activo'],
                'idx_inv_reglas_repo_empresa_activo'
            );

            $table->index(
                ['empresa_id', 'producto_id'],
                'idx_inv_reglas_repo_empresa_producto'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_reglas_reposicion');
    }
};
