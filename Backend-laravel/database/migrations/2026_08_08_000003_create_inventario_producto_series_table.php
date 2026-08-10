<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking por unidad individual (numero de serie), independiente del lote (LoteInventario ya
 * agrupa por partida/vencimiento, no por unidad). Nace para RMA via Integraciones: hoy solo se
 * llena en dos momentos best-effort, no en un flujo completo serie-en-venta (ver decision en
 * VentaIntegracionService / DevolucionIntegracionService):
 *   - en_stock: si se registra la serie al recibir mercaderia (no automatico, opcional).
 *   - vendido/devuelto: la API de devoluciones crea o actualiza la fila con la serie que el
 *     tercero (Tenri-Web-Page) le pasa, sin que el ERP la haya validado contra el movimiento de
 *     venta real (Fase 2 no captura numero_serie en POST /ventas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_producto_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('inventario_lotes')->cascadeOnUpdate()->nullOnDelete();
            $table->string('numero_serie', 120);
            $table->enum('estado', ['en_stock', 'vendido', 'devuelto', 'en_servicio_tecnico'])->default('en_stock');
            $table->string('venta_referencia', 120)->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'producto_id', 'numero_serie'], 'inv_prod_series_empresa_producto_serie_uq');
            $table->index(['empresa_id', 'estado'], 'idx_inv_prod_series_empresa_estado');
            $table->index(['empresa_id', 'venta_referencia'], 'idx_inv_prod_series_empresa_venta_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_producto_series');
    }
};
