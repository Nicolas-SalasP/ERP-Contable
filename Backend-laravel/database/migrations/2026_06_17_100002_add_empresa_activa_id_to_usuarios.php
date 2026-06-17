<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_activa_id')->nullable()->after('empresa_id');
            $table->foreign('empresa_activa_id')->references('id')->on('empresas')->nullOnDelete();
            $table->index('empresa_activa_id');
        });

        // Data migration: setear empresa_activa_id = empresa_id para todos los usuarios existentes.
        // Ningún usuario existente queda sin empresa activa.
        \DB::table('usuarios')
            ->whereNotNull('empresa_id')
            ->update(['empresa_activa_id' => \DB::raw('empresa_id')]);
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['empresa_activa_id']);
            $table->dropIndex(['empresa_activa_id']);
            $table->dropColumn('empresa_activa_id');
        });
    }
};
