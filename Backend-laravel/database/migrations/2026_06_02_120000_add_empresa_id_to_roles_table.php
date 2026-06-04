<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Aisla los roles por empresa (multitenant).
     *
     * empresa_id NULL = rol de sistema, compartido por todas las empresas
     * (los 4 roles base sembrados). Los roles personalizados creados por una
     * empresa quedan ligados a su empresa_id y solo son visibles/editables por
     * ella. Esto evita que un tenant edite el rol de otro o escale privilegios
     * modificando un rol global.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
