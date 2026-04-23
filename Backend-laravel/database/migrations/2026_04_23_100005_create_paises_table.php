<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paises', function (Blueprint $table) {
            $table->char('iso', 2)->primary();
            $table->string('nombre', 100);
            $table->char('moneda_defecto', 3);
            $table->string('etiqueta_id', 20)->default('Identificador');
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paises');
    }
};
