<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->default(1)->constrained('empresas')->onDelete('cascade');
            $table->string('codigo', 20);
            $table->string('nombre', 255);
            $table->enum('tipo', ['ACTIVO', 'PASIVO', 'PATRIMONIO', 'INGRESO', 'GASTO']);
            $table->integer('nivel')->default(1);
            $table->boolean('imputable')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['empresa_id', 'codigo']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('plan_cuentas');
    }
};
