<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales de la API publica de integraciones (dominio Integraciones): auth por API-key,
 * no por HMAC (ese sigue siendo solo para Web) ni por sesion Sanctum (los consumidores externos
 * -Tenri-Web-Page, WordPress, etc.- no tienen usuario logueado). Nunca se guarda el token en claro:
 * solo su hash sha256. El prefijo es publico y sirve para indexar/identificar sin exponer el secreto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integracion_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('prefijo', 16)->unique();
            $table->string('token_hash', 64);
            $table->json('scopes');
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->timestamp('expira_at')->nullable();
            $table->timestamp('revocada_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'revocada_at'], 'integracion_api_keys_empresa_revocada_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integracion_api_keys');
    }
};
