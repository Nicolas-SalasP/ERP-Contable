<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            // Fix 3: salud Isapre — parte legal (rebaja base tributable) vs exceso plan
            $table->decimal('salud_legal', 14, 2)->default(0)->after('aporte_empleador_mutual');
            $table->decimal('salud_adicional', 14, 2)->default(0)->after('salud_legal');

            // Fix 5: aporte empleador Ley 21.735 (cargo patronal, no descuenta trabajador)
            $table->decimal('aporte_empleador_reforma', 14, 2)->default(0)->after('salud_adicional');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropColumn(['salud_legal', 'salud_adicional', 'aporte_empleador_reforma']);
        });
    }
};
