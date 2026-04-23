<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sii_categorias_activos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->integer('vida_util_normal');
            $table->integer('vida_util_acelerada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_categorias_activos');
    }
};
