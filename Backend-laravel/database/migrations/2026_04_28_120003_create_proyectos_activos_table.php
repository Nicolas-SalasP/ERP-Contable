<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('proyectos_activos', function (Blueprint $table) {
            $table->id('id_proyecto'); // Coincide con tu frontend p.id_proyecto
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('nombre');
            $table->unsignedBigInteger('tipo_activo_id')->nullable();
            $table->integer('anio_fabricacion')->nullable();
            $table->integer('vida_util_meses')->default(60);
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->onDelete('set null');
            $table->unsignedBigInteger('empleado_id')->nullable();
            $table->decimal('valor_total_original', 15, 2)->default(0);
            $table->string('estado')->default('EN_CONSTRUCCION');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('proyectos_activos');
    }
};