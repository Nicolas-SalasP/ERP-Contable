<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asientos_contables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('codigo_unico')->unique()->nullable();
            $table->foreignId('empresa_id')->default(1)->constrained('empresas')->onDelete('cascade');
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo');
            $table->string('empleado_nombre', 150)->nullable();
            $table->date('fecha');
            $table->string('glosa', 255);
            $table->enum('tipo_asiento', ['ingreso', 'egreso', 'traspaso', ''])->default('traspaso');
            $table->string('origen_modulo', 50)->default('manual');
            $table->integer('origen_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos_contables');
    }
};
