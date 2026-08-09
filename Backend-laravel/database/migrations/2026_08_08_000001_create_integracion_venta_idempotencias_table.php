<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deduplicacion de POST /api/integraciones/v2/ventas: un reintento con el mismo header
 * `Idempotency-Key` (o `referencia_externa`, ver VentaIntegracionService::confirmar) no debe
 * crear una segunda Factura ni consumir la reserva dos veces. El unique(empresa_id, clave) es
 * la garantia real ante llamadas concurrentes -- la fila se inserta dentro de la MISMA
 * transaccion que crea la Factura, asi que si el insert choca por duplicado toda la venta
 * revierte y el caller recibe la respuesta ya guardada de la primera vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integracion_venta_idempotencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('clave', 191);
            $table->foreignId('factura_id')->nullable()->constrained('facturas')->nullOnDelete();
            $table->unsignedSmallInteger('respuesta_status');
            $table->json('respuesta_json');
            $table->timestamps();

            $table->unique(['empresa_id', 'clave'], 'integracion_venta_idempotencias_empresa_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integracion_venta_idempotencias');
    }
};
