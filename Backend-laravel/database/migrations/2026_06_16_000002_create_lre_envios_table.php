<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lre_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('estado', 20)->default('GENERADO');
            // Estados posibles: GENERADO, VALIDADO, CONFIRMADO_DT, ERROR_VALIDACION
            $table->unsignedInteger('cantidad_trabajadores')->default(0);
            $table->string('archivo_path')->nullable();
            $table->string('numero_confirmacion_dt')->nullable(); // número que entrega portal Mi DT al subir
            $table->json('errores_validacion')->nullable();       // lista de errores de ValidarLreService
            $table->timestamp('confirmado_at')->nullable();       // cuando el usuario marcó como enviado
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lre_envios');
    }
};
