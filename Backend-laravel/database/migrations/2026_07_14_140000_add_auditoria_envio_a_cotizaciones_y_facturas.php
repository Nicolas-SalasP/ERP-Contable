<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria de envio por correo: cuando se mando, quien lo mando. usuario_envio_id
 * sin FK real a proposito (mismo patron que asientos_contables.usuario_id) -- no debe
 * bloquear el envio si el usuario fue eliminado despues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->timestamp('enviada_at')->nullable()->after('estado_id');
            $table->unsignedBigInteger('usuario_envio_id')->nullable()->after('enviada_at');
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->timestamp('notificada_at')->nullable()->after('estado');
            $table->unsignedBigInteger('usuario_notificacion_id')->nullable()->after('notificada_at');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['enviada_at', 'usuario_envio_id']);
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['notificada_at', 'usuario_notificacion_id']);
        });
    }
};
