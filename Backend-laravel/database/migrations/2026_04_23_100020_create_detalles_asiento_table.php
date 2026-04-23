<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalles_asiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asiento_id')->constrained('asientos_contables')->onDelete('cascade');
            $table->string('cuenta_contable', 100);
            $table->date('fecha')->nullable();
            $table->string('tipo_operacion', 100)->nullable();
            $table->decimal('debe', 15, 2)->default(0.00);
            $table->decimal('haber', 15, 2)->default(0.00);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_asiento');
    }
};
