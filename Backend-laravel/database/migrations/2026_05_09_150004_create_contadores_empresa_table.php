<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla para contadores correlativos por empresa.
 *
 * Esta tabla mantiene secuencias correlativas por empresa para
 * identificadores de negocio (numero_comprobante, codigo de activo,
 * numero de cotizacion, etc.).
 *
 * Razon: el id auto-increment global mezcla las secuencias entre
 * empresas. Si empresa A crea el asiento con id 100 y empresa B
 * crea el siguiente, B tiene id 101. Para auditoria contable cada
 * empresa debe tener su propia secuencia 1, 2, 3...
 *
 * Uso via ContadorEmpresaService::siguienteNumero() que hace
 * SELECT FOR UPDATE para evitar race conditions en concurrencia.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contadores_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');

            // Tipo de contador. Valores conocidos:
            // - 'asiento_comprobante' (numero correlativo del asiento contable)
            // - 'activo_codigo'        (codigo correlativo del activo fijo)
            // - 'cotizacion_numero'    (preparado para futuro uso)
            // - 'factura_interna'      (preparado para futuro uso)
            $table->string('tipo', 50);

            // Ultimo valor asignado. La proxima asignacion sera ultimo_valor + 1.
            $table->unsignedBigInteger('ultimo_valor')->default(0);

            $table->timestamps();

            // Un solo contador por (empresa, tipo)
            $table->unique(['empresa_id', 'tipo'], 'idx_contador_empresa_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contadores_empresa');
    }
};
