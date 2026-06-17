<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('rol_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'empresa_id']);
            $table->index('user_id');
            $table->index('empresa_id');

            $table->foreign('user_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('rol_id')->references('id')->on('roles');
        });

        // Data migration: poblar el pivote con los datos actuales.
        // Cada usuario obtiene una fila en empresa_user con su empresa y rol actuales.
        // Se usa DB directo para evitar dependencia en modelos Eloquent durante la migración.
        \DB::table('usuarios')
            ->whereNotNull('empresa_id')
            ->whereNotNull('rol_id')
            ->orderBy('id')
            ->chunk(200, function ($usuarios) {
                $filas = $usuarios->map(fn ($u) => [
                    'user_id'    => $u->id,
                    'empresa_id' => $u->empresa_id,
                    'rol_id'     => $u->rol_id,
                    'created_at' => now(),
                ])->all();

                \DB::table('empresa_user')->insertOrIgnore($filas);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_user');
    }
};
