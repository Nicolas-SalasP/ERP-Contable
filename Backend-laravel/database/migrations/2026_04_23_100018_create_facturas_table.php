<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->default(1)->constrained('empresas')->onDelete('cascade');
            $table->unsignedBigInteger('codigo_unico')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('cuenta_bancaria_id')->nullable()->constrained('cuentas_bancarias_proveedores');
            $table->string('numero_factura', 50);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('monto_bruto', 15, 2);
            $table->decimal('monto_neto', 15, 2);
            $table->decimal('monto_iva', 15, 2)->default(0.00);
            $table->string('motivo_correccion_iva', 255)->nullable();
            $table->integer('autorizador_id')->nullable();
            $table->enum('estado', ['BORRADOR', 'REGISTRADA', 'PAGADA', 'ANULADA'])->default('REGISTRADA');
            $table->timestamp('created_at')->useCurrent();
            $table->string('archivo_pdf', 255)->nullable();

            $table->unique(['proveedor_id', 'numero_factura']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
