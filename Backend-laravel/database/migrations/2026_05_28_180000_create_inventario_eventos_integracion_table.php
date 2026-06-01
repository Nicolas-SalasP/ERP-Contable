<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_eventos_integracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->string('evento', 120);
            $table->string('modulo_origen', 40)->default('INVENTARIO');
            $table->string('entidad_tipo', 120);
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->enum('estado', ['PENDIENTE', 'PROCESADO', 'IGNORADO', 'ERROR'])->default('PENDIENTE');
            $table->enum('prioridad', ['BAJA', 'NORMAL', 'ALTA', 'CRITICA'])->default('NORMAL');
            $table->json('payload_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('correlacion_id', 120)->nullable();
            $table->string('origen_modulo', 80)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->timestamp('procesado_at')->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'created_at'], 'idx_inv_evt_empresa_fecha');
            $table->index(['empresa_id', 'evento', 'created_at'], 'idx_inv_evt_empresa_evento_fecha');
            $table->index(['empresa_id', 'estado', 'created_at'], 'idx_inv_evt_empresa_estado_fecha');
            $table->index(['empresa_id', 'prioridad', 'created_at'], 'idx_inv_evt_empresa_prioridad_fecha');
            $table->index(['empresa_id', 'entidad_tipo', 'entidad_id'], 'idx_inv_evt_empresa_entidad');
            $table->index(['empresa_id', 'correlacion_id'], 'idx_inv_evt_empresa_correlacion');
            $table->index(['empresa_id', 'origen_modulo', 'origen_id'], 'idx_inv_evt_empresa_origen');
            $table->index(['modulo_origen', 'evento'], 'idx_inv_evt_modulo_evento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_eventos_integracion');
    }
};
