<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_cat_unidades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 4)->unique();
            $table->string('nombre', 40);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_cat_unidades');
    }
};
