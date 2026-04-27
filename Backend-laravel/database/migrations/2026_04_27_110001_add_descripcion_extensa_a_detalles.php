<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizacion_detalles', 'descripcion')) {
                $table->text('descripcion')->nullable()->after('producto_nombre');
            }
        });
    }

    public function down(): void
    {
    }
};
