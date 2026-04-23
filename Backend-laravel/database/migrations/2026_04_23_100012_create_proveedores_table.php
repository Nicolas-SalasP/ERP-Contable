<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->default(1)->constrained('empresas')->onDelete('cascade');
            $table->string('codigo_interno', 50);
            $table->string('rut', 20)->nullable();
            $table->string('razon_social', 150);
            $table->char('pais_iso', 2);
            $table->char('moneda_defecto', 3);
            $table->string('region', 100)->nullable();
            $table->string('comuna', 100)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email_contacto', 100)->nullable();
            $table->string('nombre_contacto', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('pais_iso')->references('iso')->on('paises');
            $table->unique(['codigo_interno', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
