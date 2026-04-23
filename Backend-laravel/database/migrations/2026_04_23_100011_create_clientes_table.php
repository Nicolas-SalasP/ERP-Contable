<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 20);
            $table->string('razon_social', 255);
            $table->string('contacto_nombre', 255)->nullable();
            $table->string('contacto_email', 100)->nullable();
            $table->string('contacto_telefono', 50)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['rut', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
