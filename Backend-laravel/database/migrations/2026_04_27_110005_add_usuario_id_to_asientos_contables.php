<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            if (!Schema::hasColumn('asientos_contables', 'usuario_id')) {
                $table->unsignedBigInteger('usuario_id')->nullable()->after('estado');
            }
        });
    }

    public function down()
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            if (Schema::hasColumn('asientos_contables', 'usuario_id')) {
                $table->dropColumn('usuario_id');
            }
        });
    }
};