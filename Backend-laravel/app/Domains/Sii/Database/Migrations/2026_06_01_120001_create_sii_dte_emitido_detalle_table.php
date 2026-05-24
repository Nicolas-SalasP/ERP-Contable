<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_dte_emitido_detalle', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dte_emitido_id')
                ->constrained('sii_dte_emitido')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('numero_linea');

            $table->foreignId('factura_detalle_id')
                ->nullable()
                ->constrained('facturas_detalles')
                ->nullOnDelete();

            $table->string('codigo_item', 35)->nullable();
            $table->string('tipo_codigo', 10)->nullable();
            $table->string('nombre_item', 80);
            $table->string('descripcion', 1000)->nullable();

            $table->decimal('cantidad', 18, 6);
            $table->string('unidad_medida', 4)->nullable();
            $table->decimal('precio_unitario', 18, 4);
            $table->decimal('descuento_pct', 5, 2)->default(0);
            $table->decimal('descuento_monto', 18, 2)->default(0);
            $table->decimal('recargo_pct', 5, 2)->default(0);
            $table->decimal('recargo_monto', 18, 2)->default(0);
            $table->boolean('exento')->default(false);
            $table->decimal('monto_item', 18, 2);

            $table->timestamps();

            $table->unique(['dte_emitido_id', 'numero_linea'], 'sii_dte_emitido_detalle_dte_linea_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_dte_emitido_detalle');
    }
};
