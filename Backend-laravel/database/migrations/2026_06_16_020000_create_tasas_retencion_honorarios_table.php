<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_retencion_honorarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique();
            $table->decimal('tasa_pct', 5, 2); // 15.25 para 2026
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_retencion_honorarios');
    }
};
