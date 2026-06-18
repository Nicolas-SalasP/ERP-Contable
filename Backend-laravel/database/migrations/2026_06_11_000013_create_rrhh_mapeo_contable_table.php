<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapeo entre los tipos de cuenta RRHH y los códigos del Plan de Cuentas
 * de cada empresa. Necesario para centralización contable (R5).
 *
 * Cada empresa configura una sola vez qué cuenta usar para cada categoría
 * (GASTO_REMUNERACIONES, PASIVO_LIQUIDO_PAGAR, etc.) y el servicio de
 * centralización lee este mapeo al generar el asiento mensual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rrhh_mapeo_contable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo_cuenta', 50);       // Constante de RrhhMapeoContable
            $table->string('cuenta_contable_codigo', 20); // Código en plan_cuentas (no FK: mismo patrón que detalle_asiento)
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo_cuenta']);
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rrhh_mapeo_contable');
    }
};
