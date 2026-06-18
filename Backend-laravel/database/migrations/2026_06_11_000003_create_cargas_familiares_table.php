<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Cargas familiares reconocidas (DFL 150/1982 — Asignación Familiar)
        Schema::create('cargas_familiares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');

            $table->string('rut', 12)->nullable();
            $table->string('nombre', 150);
            $table->enum('tipo', [
                'HIJO',          // hijo(a) soltero(a) hasta 18 años o 24 si estudia
                'CONYUGE',       // cónyuge o conviviente civil sin rentas propias
                'ASCENDIENTE',   // padre/madre dependiente
                'INVALIDO',      // familiar inválido (sin límite de edad)
            ]);
            $table->date('fecha_nacimiento')->nullable();
            $table->boolean('estudia')->default(false);
            $table->boolean('activa')->default(true);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'empleado_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_familiares');
    }
};
