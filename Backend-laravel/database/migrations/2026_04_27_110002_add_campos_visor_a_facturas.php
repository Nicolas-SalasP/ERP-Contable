<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (!Schema::hasColumn('facturas', 'codigo_interno')) {
                $table->string('codigo_interno')->nullable()->after('id');
            }
            if (!Schema::hasColumn('facturas', 'comprobante_contable')) {
                $table->string('comprobante_contable')->nullable()->after('estado');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
