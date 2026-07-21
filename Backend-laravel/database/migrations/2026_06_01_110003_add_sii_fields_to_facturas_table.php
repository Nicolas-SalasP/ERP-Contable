<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // FK opcional a clientes (modelo legado orientado a compras: cliente_id queda nullable).
            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->nullOnDelete();

            $table->unsignedSmallInteger('tipo_dte')->nullable();
            $table->unsignedTinyInteger('forma_pago_codigo')->nullable();
            $table->string('condicion_pago', 100)->nullable();
            $table->string('moneda', 3)->default('CLP');
            $table->decimal('monto_exento', 15, 2)->default(0);
            $table->decimal('descuento_global_monto', 15, 2)->default(0);
            $table->decimal('descuento_global_porcentaje', 5, 2)->nullable();
            $table->boolean('emitir_dte_automatico')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Drop FK primero, despues columna.
            $table->dropForeign(['cliente_id']);
            $table->dropColumn([
                'cliente_id',
                'tipo_dte',
                'forma_pago_codigo',
                'condicion_pago',
                'moneda',
                'monto_exento',
                'descuento_global_monto',
                'descuento_global_porcentaje',
                'emitir_dte_automatico',
            ]);
        });
    }
};
