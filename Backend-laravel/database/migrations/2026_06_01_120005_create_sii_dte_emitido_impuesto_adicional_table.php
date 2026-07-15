<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impuestos adicionales (ILA, IABA, retenciones, suntuarios, especificos)
 * a nivel de DTE o de linea. Cuando dte_emitido_detalle_id es NULL,
 * el impuesto se aplica a la cabecera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_dte_emitido_impuesto_adicional', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dte_emitido_id')
                ->constrained('sii_dte_emitido')
                ->cascadeOnDelete();

            // Nombre explícito: el autogenerado tiene 65 chars, límite MySQL es 64.
            // sii_dte_emitido_impuesto_adicional_dte_emitido_detalle_id_foreign = 65 chars
            $table->foreignId('dte_emitido_detalle_id')
                ->nullable()
                ->constrained('sii_dte_emitido_detalle', 'id', 'sii_dte_imp_adic_detalle_fk')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('codigo_impuesto'); // FK logica a sii_cat_impuestos.codigo
            $table->decimal('tasa', 5, 2)->nullable(); // null para impuestos especificos por unidad
            $table->decimal('monto', 18, 2);

            $table->timestamps();

            $table->index(['dte_emitido_id', 'codigo_impuesto'], 'sii_dte_imp_adic_dte_codigo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_dte_emitido_impuesto_adicional');
    }
};
