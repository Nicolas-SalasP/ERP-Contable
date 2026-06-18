<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 — Ley 21.719 (derechos ARCO+).
 *
 * - bloqueado_at: marca el bloqueo del tratamiento (derecho de oposición/bloqueo).
 *   Se usa cuando una supresión no puede ejecutarse por retención legal obligatoria
 *   (registros de remuneraciones/tributarios ~6 años).
 * - anonimizado_at: marca la anonimización efectiva del titular una vez vencido el
 *   plazo de retención (supresión total).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->timestamp('anonimizado_at')->nullable()->after('observaciones');
            $table->timestamp('bloqueado_at')->nullable()->after('anonimizado_at');
            // La supresión total (vencido el plazo de retención) anula el apellido
            // paterno: debe poder quedar en null tras la anonimización.
            $table->string('apellido_paterno', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['anonimizado_at', 'bloqueado_at']);
            $table->string('apellido_paterno', 60)->nullable(false)->change();
        });
    }
};
