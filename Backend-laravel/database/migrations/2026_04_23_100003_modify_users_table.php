<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->rename('usuarios');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('empresa_id')->default(1)->constrained('empresas')->onDelete('cascade');
            $table->integer('intentos_fallidos')->default(0);
            $table->integer('nivel_bloqueo')->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();
            $table->string('reset_token', 10)->nullable();
            $table->dateTime('reset_expires_at')->nullable();
            $table->foreignId('rol_id')->constrained('roles');
            $table->foreignId('estado_suscripcion_id')->default(2)->constrained('estados_suscripcion');
            $table->date('fecha_fin_suscripcion')->nullable();
            
            $table->dropColumn('email_verified_at');
        });
    }
    
    public function down(): void
    {
        //
    }
};
