<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_cat_impuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('codigo')->unique();
            $table->string('nombre', 100);
            $table->decimal('tasa', 5, 2)->nullable();
            $table->string('tipo', 20);
            $table->boolean('es_adicional')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_cat_impuestos');
    }
};
