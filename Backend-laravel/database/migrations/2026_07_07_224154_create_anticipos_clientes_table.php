<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espejo de anticipos_proveedores: hasta ahora un cobro de más (anticipo de
 * cliente) generado en Mesa de Conciliación solo dejaba el asiento contable
 * suelto, sin cliente_id -- Visor 360 Cliente no podía mostrarlo nunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anticipos_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->decimal('monto', 15, 2);
            $table->decimal('monto_original', 15, 2)->nullable();
            $table->decimal('saldo_disponible', 15, 2)->nullable();
            $table->string('referencia')->nullable();
            $table->string('estado', 50)->default('PENDIENTE');
            $table->unsignedBigInteger('movimiento_id')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->date('fecha_real')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anticipos_clientes');
    }
};
