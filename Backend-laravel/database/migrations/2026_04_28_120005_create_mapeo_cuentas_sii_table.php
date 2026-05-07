<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mapeo_cuentas_sii', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('codigo_cuenta');
            $table->string('concepto_sii');
            $table->timestamps();
            $table->unique(['empresa_id', 'codigo_cuenta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapeo_cuentas_sii');
    }
};