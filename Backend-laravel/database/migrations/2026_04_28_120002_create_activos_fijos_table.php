<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('activos_fijos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            
            // Relaciones contables
            $table->string('cuenta_activo_codigo')->nullable();
            $table->string('cuenta_depreciacion_codigo')->nullable();
            $table->string('cuenta_gasto_codigo')->nullable();
            
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->onDelete('set null');
            
            // Datos financieros
            $table->decimal('valor_adquisicion', 15, 2);
            $table->date('fecha_adquisicion');
            $table->integer('vida_util_meses');
            $table->decimal('valor_residual', 15, 2)->default(1);
            
            // Estado y depreciación
            $table->string('estado')->default('ACTIVO');
            $table->decimal('depreciacion_acumulada', 15, 2)->default(0);
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('activos_fijos');
    }
};