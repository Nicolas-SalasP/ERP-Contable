<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('movimientos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('cuenta_bancaria_id')->constrained('cuentas_bancarias_empresa')->onDelete('cascade');
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('descripcion');
            $table->string('nro_documento')->nullable();
            $table->decimal('cargo', 15, 2)->default(0);
            $table->decimal('abono', 15, 2)->default(0);
            $table->string('estado', 50)->default('PENDIENTE');
            $table->unsignedBigInteger('asiento_id')->nullable();
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('movimientos_bancarios');
    }
};
