<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->string('numero_oc', 30);
            $table->date('fecha_emision');
            $table->date('fecha_entrega_esperada')->nullable();
            $table->enum('estado', ['BORRADOR', 'ENVIADA', 'RECIBIDA_PARCIAL', 'RECIBIDA_TOTAL', 'ANULADA'])->default('BORRADOR');
            $table->string('moneda', 10)->default('CLP');
            $table->decimal('tipo_cambio', 10, 4)->default(1);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('impuesto', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
            $table->unique(['empresa_id', 'numero_oc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
