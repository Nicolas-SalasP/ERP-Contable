<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->date('fecha_pago')->nullable()->after('fecha_vencimiento');
            $table->string('medio_pago', 50)->nullable()->after('fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {

        });
    }
};
