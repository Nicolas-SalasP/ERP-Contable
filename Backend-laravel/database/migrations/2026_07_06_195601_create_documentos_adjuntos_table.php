<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('factura_id')->constrained('facturas')->onDelete('cascade');
            $table->string('tipo_documento', 50)->default('OTRO');
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamano_bytes');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'factura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_adjuntos');
    }
};
