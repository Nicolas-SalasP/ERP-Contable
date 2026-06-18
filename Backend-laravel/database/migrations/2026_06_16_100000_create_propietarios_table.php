<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propietarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('rut', 12);
            $table->string('nombre', 255);
            $table->decimal('porcentaje_participacion', 5, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'rut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propietarios');
    }
};
