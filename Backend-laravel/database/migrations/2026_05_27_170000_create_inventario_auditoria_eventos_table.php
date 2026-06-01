<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_auditoria_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->string('modulo', 40)->default('INVENTARIO');
            $table->string('accion', 100);
            $table->string('entidad_tipo', 120);
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->enum('severidad', ['INFO', 'WARNING', 'CRITICAL'])->default('INFO');
            $table->enum('estado', ['REGISTRADO', 'OBSERVADO', 'RESUELTO', 'IGNORADO'])->default('REGISTRADO');
            $table->string('descripcion', 500);
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('referencia', 160)->nullable();
            $table->string('motivo', 160)->nullable();
            $table->text('observacion')->nullable();
            $table->string('origen_modulo', 80)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->json('antes_json')->nullable();
            $table->json('despues_json')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'created_at'], 'idx_inv_aud_empresa_fecha');
            $table->index(['empresa_id', 'accion', 'created_at'], 'idx_inv_aud_empresa_accion_fecha');
            $table->index(['empresa_id', 'entidad_tipo', 'entidad_id'], 'idx_inv_aud_empresa_entidad');
            $table->index(['empresa_id', 'usuario_id', 'created_at'], 'idx_inv_aud_empresa_usuario_fecha');
            $table->index(['empresa_id', 'severidad', 'created_at'], 'idx_inv_aud_empresa_sev_fecha');
            $table->index(['empresa_id', 'origen_modulo', 'origen_id'], 'idx_inv_aud_empresa_origen');
            $table->index(['modulo', 'accion'], 'idx_inv_aud_modulo_accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_auditoria_eventos');
    }
};
