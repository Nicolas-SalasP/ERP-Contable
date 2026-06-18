<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_ppm_propyme', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique();
            $table->decimal('tasa_base_pct', 6, 3);           // 0.125 rebajada / 0.250 general
            $table->decimal('tasa_sobre_50000uf_pct', 6, 3);  // 0.250 rebajada / 0.500 general
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_ppm_propyme');
    }
};
