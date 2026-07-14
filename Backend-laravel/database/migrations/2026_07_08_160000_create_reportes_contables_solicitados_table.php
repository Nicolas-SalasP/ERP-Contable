<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_contables_solicitados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->string('tipo_reporte', 30); // libro_diario | libro_mayor
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedTinyInteger('filtro')->default(1);
            $table->string('cuenta_contable', 20)->nullable(); // solo libro_mayor
            $table->string('email_destino');
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE|PROCESANDO|ENVIADO|ERROR
            $table->text('error_mensaje')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_contables_solicitados');
    }
};
