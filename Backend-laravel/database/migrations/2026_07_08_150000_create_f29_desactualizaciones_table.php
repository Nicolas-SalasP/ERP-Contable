<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Registra cuando un F29 ya centralizado (asiento MAYORIZADO) queda desactualizado por la anulación posterior de una factura o DTE del mismo período. No bloquea nada: solo deja constancia consultable para alertar en la UI (ver ImpuestosService::simularF29). */
    public function up(): void
    {
        Schema::create('f29_desactualizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->string('motivo');
            $table->timestamp('detectado_en');
            $table->timestamps();

            $table->unique(['empresa_id', 'mes', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('f29_desactualizaciones');
    }
};
