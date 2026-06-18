<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('honorarios_recibidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->string('rut_prestador', 12);
            $table->string('nombre_prestador');
            $table->string('numero_boleta', 20)->nullable();
            $table->date('fecha');
            $table->unsignedBigInteger('monto_bruto');
            $table->decimal('tasa_retencion_pct', 5, 2);
            $table->unsignedBigInteger('monto_retencion');
            $table->unsignedBigInteger('monto_liquido');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honorarios_recibidos');
    }
};
