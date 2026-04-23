<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 20);
            $table->string('razon_social', 150);
            $table->string('direccion', 255)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('color_primario', 7)->default('#10b981');
            $table->enum('regimen_tributario', ['14_D3', '14_D8', '14_A'])->default('14_D3');
            $table->decimal('tasa_impuesto', 5, 2)->default(25.00);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
