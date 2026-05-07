<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('nombre_cliente', 255);
            $table->date('fecha_emision');
            $table->decimal('total', 15, 2)->default(0.00);
            $table->foreignId('estado_id')->constrained('estado_cotizaciones');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('es_afecta')->default(true);
            $table->integer('validez')->default(15);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
