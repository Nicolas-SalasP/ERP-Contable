<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('detalles_asiento', function (Blueprint $table) {
            $table->string('descripcion_extensa', 255)->nullable()->after('haber');
        });
    }

    public function down()
    {
        Schema::table('detalles_asiento', function (Blueprint $table) {
            $table->dropColumn('descripcion_extensa');
        });
    }
};