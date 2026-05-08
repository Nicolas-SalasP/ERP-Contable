<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            $table->string('archivo_pdf')->nullable()->after('estado');
        });
    }

    public function down()
    {
        Schema::table('anticipos_proveedores', function (Blueprint $table) {
            $table->dropColumn('archivo_pdf');
        });
    }
};