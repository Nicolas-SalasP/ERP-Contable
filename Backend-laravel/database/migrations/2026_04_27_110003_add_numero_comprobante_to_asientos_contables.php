<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            if (!Schema::hasColumn('asientos_contables', 'numero_comprobante')) {
                $table->string('numero_comprobante')->after('empresa_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            //
        });
    }
};
