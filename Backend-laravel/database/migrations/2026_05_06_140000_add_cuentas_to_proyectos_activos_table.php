<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos_activos', function (Blueprint $table) {
            $table->unsignedBigInteger('cuenta_depreciacion_id')->nullable()->after('tipo_activo_id');
            $table->unsignedBigInteger('cuenta_gasto_id')->nullable()->after('cuenta_depreciacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('proyectos_activos', function (Blueprint $table) {
            $table->dropColumn(['cuenta_depreciacion_id', 'cuenta_gasto_id']);
        });
    }
};