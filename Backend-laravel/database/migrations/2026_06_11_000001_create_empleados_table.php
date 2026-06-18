<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');

            // Identificación
            $table->string('rut', 12);
            $table->string('nombres', 100);
            $table->string('apellido_paterno', 60);
            $table->string('apellido_materno', 60)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('sexo', ['M', 'F', 'O'])->nullable();
            $table->string('nacionalidad', 3)->default('CHL');
            $table->string('estado_civil', 20)->nullable();

            // Contacto
            $table->string('email', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('pais', 3)->default('CHL');

            // Previsión: AFP (almacenado en claro; la cotización es un número conocido)
            $table->string('afp', 30)->nullable();

            // Salud (Ley 18.469): tipo y datos de isapre si corresponde
            $table->enum('tipo_salud', ['FONASA', 'ISAPRE'])->default('FONASA');
            $table->string('isapre_nombre', 50)->nullable();
            // Plan en UF; cotización adicional si plan > 7% del imponible
            $table->decimal('isapre_plan_uf', 8, 4)->nullable();
            $table->decimal('isapre_cotizacion_adicional_pct', 5, 2)->nullable();

            // Datos bancarios cifrados (Ley 21.719: datos personales sensibles)
            $table->string('banco_nombre', 80)->nullable();
            $table->string('banco_tipo_cuenta', 30)->nullable();
            // Número de cuenta cifrado con Crypt::encryptString
            $table->text('banco_numero_cuenta_cifrado')->nullable();

            // Estado
            $table->enum('estado', ['ACTIVO', 'INACTIVO', 'LICENCIA'])->default('ACTIVO');
            $table->date('fecha_ingreso_empresa')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'rut']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
