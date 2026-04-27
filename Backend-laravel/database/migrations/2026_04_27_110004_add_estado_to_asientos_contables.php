<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            if (!Schema::hasColumn('asientos_contables', 'estado')) {
                // Agregamos la columna estado, por defecto será CONTABILIZADO
                $table->string('estado')->default('CONTABILIZADO')->after('glosa');
            }
        });
    }

    public function down()
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            if (Schema::hasColumn('asientos_contables', 'estado')) {
                $table->dropColumn('estado');
            }
        });
    }
};