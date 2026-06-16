<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dj_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('codigo_dj', 10);
            $table->unsignedSmallInteger('anio');
            $table->string('estado', 20)->default('GENERADO');
            $table->unsignedInteger('cantidad_registros')->default(0);
            $table->string('archivo_path')->nullable();
            $table->string('folio_presentacion')->nullable();
            $table->timestamp('presentado_at')->nullable();
            $table->json('errores_validacion')->nullable();
            $table->unsignedSmallInteger('anio_40_horas')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'codigo_dj', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dj_envios');
    }
};
