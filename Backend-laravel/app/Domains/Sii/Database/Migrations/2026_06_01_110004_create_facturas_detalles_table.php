<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('factura_id')
                ->constrained('facturas')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('numero_linea');

            $table->foreignId('producto_id')
                ->nullable()
                ->constrained('inventario_productos')
                ->nullOnDelete();

            // Snapshots: lo emitido al SII queda inmutable aunque el catalogo cambie.
            $table->string('codigo_item', 35)->nullable();
            $table->string('tipo_codigo', 10)->nullable();
            $table->string('nombre_item', 80);
            $table->string('descripcion', 1000)->nullable();

            $table->decimal('cantidad', 18, 6);
            $table->string('unidad_medida', 4)->nullable();
            $table->decimal('precio_unitario', 18, 4);
            $table->decimal('descuento_pct', 5, 2)->default(0);
            $table->decimal('descuento_monto', 18, 4)->default(0);
            $table->decimal('recargo_pct', 5, 2)->default(0);
            $table->decimal('recargo_monto', 18, 4)->default(0);
            $table->boolean('exento')->default(false);
            $table->unsignedSmallInteger('codigo_impuesto_adicional')->nullable();
            $table->decimal('monto_item', 18, 2);

            $table->timestamps();

            $table->unique(['factura_id', 'numero_linea']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas_detalles');
    }
};
