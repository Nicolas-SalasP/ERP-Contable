<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('cascade');
            $table->foreignId('producto_id')->constrained('inventario_productos')->onDelete('cascade');
            // SKU del proveedor: puede no coincidir con el sku/codigo_barra interno del producto.
            $table->string('codigo_proveedor', 100)->nullable();
            $table->decimal('costo_neto', 14, 4);
            $table->char('moneda', 3)->default('CLP');
            $table->date('vigente_desde');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Un proveedor tiene a lo sumo un registro vigente por producto; el importador
            // hace upsert sobre esta combinacion en vez de acumular historial de precios.
            $table->unique(['proveedor_id', 'producto_id']);
            $table->index(['empresa_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_productos');
    }
};
