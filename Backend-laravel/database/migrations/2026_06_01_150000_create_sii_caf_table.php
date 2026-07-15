<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folios CAF (Codigo de Autorizacion de Folios) por empresa y tipo DTE.
 * Persiste el XML cifrado + clave RSA privada cifrada (AES-256/APP_KEY).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_caf', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->unsignedSmallInteger('tipo_dte');
            $table->unsignedInteger('folio_desde');
            $table->unsignedInteger('folio_hasta');
            $table->unsignedInteger('folio_actual'); // siguiente a entregar; init = folio_desde
            $table->unsignedInteger('folios_usados')->default(0);
            $table->unsignedInteger('folios_huerfanos')->default(0);

            $table->date('fecha_autorizacion');
            $table->date('fecha_vencimiento')->nullable();

            // Snapshots del CAF (inmutables al momento de la carga).
            $table->string('rut_empresa_caf', 10);
            $table->string('razon_social_caf', 200);
            $table->string('sii_idk', 50);

            // Sensibles - persistidos CIFRADOS con Crypt::encryptString.
            $table->text('rsa_sk_cifrada');
            $table->longText('xml_completo_cifrado');

            // No sensibles - en claro.
            $table->text('rsa_pubk');
            $table->text('firma_caf');

            $table->string('estado', 20)->default('activo'); // activo|agotado|vencido|revocado

            $table->timestamps();

            $table->unique(['empresa_id', 'sii_idk'], 'sii_caf_empresa_idk_unique');
            $table->index(['empresa_id', 'tipo_dte', 'estado'], 'sii_caf_empresa_tipo_estado_idx');
            $table->index('fecha_vencimiento', 'sii_caf_fecha_vencimiento_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_caf');
    }
};
