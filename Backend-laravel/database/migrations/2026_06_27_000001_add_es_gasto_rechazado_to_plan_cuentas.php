<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_cuentas', function (Blueprint $table) {
            $table->boolean('es_gasto_rechazado')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('plan_cuentas', function (Blueprint $table) {
            $table->dropColumn('es_gasto_rechazado');
        });
    }
};
