<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende sii_envio_dte con campos para soportar polling del estado en el WS
 * QueryEstUp del SII. Idempotente: solo agrega columnas faltantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sii_envio_dte', function (Blueprint $table) {
            if (! Schema::hasColumn('sii_envio_dte', 'fecha_ultimo_polling')) {
                $table->timestamp('fecha_ultimo_polling')->nullable()->after('fecha_envio');
            }
            if (! Schema::hasColumn('sii_envio_dte', 'fecha_resolucion')) {
                $table->timestamp('fecha_resolucion')->nullable()->after('fecha_ultimo_polling');
            }
            if (! Schema::hasColumn('sii_envio_dte', 'intentos_polling')) {
                $table->unsignedInteger('intentos_polling')->default(0)->after('intentos_envio');
            }
            if (! Schema::hasColumn('sii_envio_dte', 'http_status_ultimo_polling')) {
                $table->unsignedSmallInteger('http_status_ultimo_polling')->nullable()->after('http_status_ultimo_envio');
            }
            if (! Schema::hasColumn('sii_envio_dte', 'estado_sii_ultimo')) {
                $table->string('estado_sii_ultimo', 10)->nullable()->after('glosa_sii');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sii_envio_dte', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_ultimo_polling',
                'fecha_resolucion',
                'intentos_polling',
                'http_status_ultimo_polling',
                'estado_sii_ultimo',
            ]);
        });
    }
};
