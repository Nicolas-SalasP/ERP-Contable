<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de notificaciones de vencimiento de certificado digital SII.
 * La logica anti-spam (one-shot vs daily) vive en el Job, no en BD: no
 * usamos UNIQUE compuesto con DATE(enviada_at) por portabilidad SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sii_certificado_notificaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('certificado_id')
                ->constrained('sii_certificado_empresa')
                ->cascadeOnDelete();

            $table->string('nivel', 20);                  // VENCIDO|CRITICA_T1|...|BAJA_T60
            $table->string('enviada_a', 255);             // email destinatario efectivo
            $table->smallInteger('dias_para_vencer');     // snapshot al envio; puede ser negativo
            $table->string('estado_envio', 20)->default('enviada'); // enviada|fallida
            $table->text('error_mensaje')->nullable();
            $table->timestamp('enviada_at');

            $table->timestamps();

            $table->index(['certificado_id', 'nivel'], 'sii_cert_notif_cert_nivel_idx');
            $table->index('enviada_at', 'sii_cert_notif_enviada_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sii_certificado_notificaciones');
    }
};
