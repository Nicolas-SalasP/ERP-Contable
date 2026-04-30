<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalogo_plan_maestro', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 255);
            $table->enum('tipo', ['ACTIVO', 'PASIVO', 'PATRIMONIO', 'INGRESO', 'GASTO']);
            $table->integer('nivel')->default(1);
            $table->boolean('imputable')->default(true);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('catalogo_plan_maestro');
    }
};
