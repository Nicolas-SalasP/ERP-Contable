<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_dte_emitido_referencia', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dte_emitido_id')
                ->constrained('sii_dte_emitido')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('numero_linea'); // NroLinRef 1-40
            $table->string('tipo_documento_referencia', 3); // TpoDocRef
            $table->string('folio_referencia', 18);         // alfanumerico (OC, HES, etc.)
            $table->date('fecha_referencia');
            $table->unsignedSmallInteger('codigo_referencia')->nullable(); // CodRef 1|2|3
            $table->string('razon_referencia', 90)->nullable();
            $table->string('rut_otro_contribuyente', 10)->nullable();

            $table->timestamps();

            $table->unique(['dte_emitido_id', 'numero_linea'], 'sii_dte_emitido_ref_dte_linea_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_dte_emitido_referencia');
    }
};
