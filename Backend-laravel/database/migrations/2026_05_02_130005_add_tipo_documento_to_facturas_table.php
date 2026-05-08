<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('tipo_documento', 50)->default('FACTURA')->after('tipo');
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('tipo_documento');
        });
    }
};