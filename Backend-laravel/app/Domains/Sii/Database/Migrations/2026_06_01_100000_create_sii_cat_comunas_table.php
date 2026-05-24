<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_cat_comunas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 5)->unique();
            $table->string('nombre', 80);
            $table->string('region_codigo', 2)->nullable();
            $table->string('region_nombre', 80)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_cat_comunas');
    }
};
