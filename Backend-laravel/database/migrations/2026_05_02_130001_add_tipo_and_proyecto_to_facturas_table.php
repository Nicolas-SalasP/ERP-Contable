<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('facturas', 'tipo')) {
                $blueprint->string('tipo', 20)->default('COMPRA')->after('numero_factura');
            }
            
            if (!Schema::hasColumn('facturas', 'proyecto_activo_id')) {
                $blueprint->unsignedBigInteger('proyecto_activo_id')->nullable()->after('empresa_id');
                $blueprint->foreign('proyecto_activo_id')->references('id_proyecto')->on('proyectos_activos')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['proyecto_activo_id']);
            $blueprint->dropColumn(['tipo', 'proyecto_activo_id']);
        });
    }
};