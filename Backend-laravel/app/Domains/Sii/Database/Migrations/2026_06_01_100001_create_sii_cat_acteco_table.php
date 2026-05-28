<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_cat_acteco', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->string('nombre', 200);
            $table->string('categoria', 80)->nullable();
            $table->boolean('afecto_iva')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_cat_acteco');
    }
};
