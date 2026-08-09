<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deduplicacion de POST /api/integraciones/v2/devoluciones, mismo patron que
 * integracion_venta_idempotencias (ver esa migracion): unique(empresa_id, clave) + insert dentro
 * de la misma transaccion que crea la devolucion/NC, tabla separada porque el recurso que
 * referencia (devolucion_orden_id) es de otro dominio (Inventario, no Comercial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integracion_devolucion_idempotencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('clave', 191);
            $table->foreignId('devolucion_orden_id')->nullable()->constrained('inventario_devolucion_ordenes')->nullOnDelete();
            $table->unsignedSmallInteger('respuesta_status');
            $table->json('respuesta_json');
            $table->timestamps();

            $table->unique(['empresa_id', 'clave'], 'integracion_devolucion_idempotencias_empresa_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integracion_devolucion_idempotencias');
    }
};
