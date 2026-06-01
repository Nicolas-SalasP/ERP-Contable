<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_token_sesion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('ambiente', 20);
            $table->string('token', 255);
            $table->string('semilla_usada', 100);
            $table->char('hash_firma_semilla', 64);
            $table->dateTime('fecha_obtencion');
            $table->dateTime('fecha_expiracion');
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->unsignedInteger('intentos_uso')->default(0);
            $table->timestamps();

            $table->index(
                ['empresa_id', 'ambiente', 'fecha_expiracion'],
                'sii_token_sesion_activa_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_token_sesion');
    }
};
