<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in por producto (default false, igual que visible_web): la mayoria de los productos no
 * necesita numero de serie individual (ej. insumos a granel). Cuando esta en true, el flujo de
 * devolucion via Integraciones (Fase 4 RMA) exige numero_serie en el item devuelto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_productos', function (Blueprint $table) {
            $table->boolean('requiere_serie')->default(false)->after('maneja_lotes');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_productos', function (Blueprint $table) {
            $table->dropColumn('requiere_serie');
        });
    }
};
