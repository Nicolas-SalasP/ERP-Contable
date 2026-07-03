<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Antes, AnticipoProveedorService::aplicarAFactura no registraba a qué
        // factura se aplicó cada anticipo (el parámetro $facturaId ni siquiera
        // se usaba). Si esa factura se anulaba después, el saldo consumido del
        // anticipo quedaba perdido sin poder rastrearse ni liberarse.
        Schema::create('anticipo_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('anticipo_id')->constrained('anticipos_proveedores')->onDelete('cascade');
            $table->unsignedBigInteger('factura_id');
            $table->decimal('monto', 15, 2);
            $table->timestamp('revertido_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'factura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anticipo_aplicaciones');
    }
};
