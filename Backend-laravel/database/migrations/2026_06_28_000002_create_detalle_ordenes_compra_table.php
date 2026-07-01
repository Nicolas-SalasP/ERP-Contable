<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orden_compra_id');
            $table->string('producto_descripcion', 255);
            $table->string('codigo_producto', 100)->nullable();
            $table->decimal('cantidad', 10, 3);
            $table->string('unidad', 20)->default('UN');
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('cantidad_recibida', 10, 3)->default(0);
            $table->timestamps();

            $table->foreign('orden_compra_id')->references('id')->on('ordenes_compra')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ordenes_compra');
    }
};
