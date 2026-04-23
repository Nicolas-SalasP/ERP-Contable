<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cuentas_bancarias_proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('cascade');
            $table->string('banco', 100);
            $table->string('numero_cuenta', 50);
            $table->string('tipo_cuenta', 50)->nullable();
            $table->char('pais_iso', 2);
            $table->string('swift_bic', 20)->nullable();
            $table->boolean('activo')->default(true);

            $table->foreign('pais_iso')->references('iso')->on('paises');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias_proveedores');
    }
};
