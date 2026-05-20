<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('tenri_user_id')->nullable()->after('id');
            $table->string('plan_slug')->nullable()->after('estado_suscripcion_id');
            $table->json('module_keys')->nullable()->after('plan_slug');
            $table->timestamp('tenri_synced_at')->nullable()->after('module_keys');

            $table->unique('tenri_user_id');
            $table->index('plan_slug');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
        });

        Schema::table('usuarios', function (Blueprint $table) {
            DB::statement('ALTER TABLE usuarios MODIFY empresa_id BIGINT UNSIGNED NULL');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropUnique(['tenri_user_id']);
            $table->dropIndex(['plan_slug']);
            $table->dropColumn([
                'tenri_user_id',
                'plan_slug',
                'module_keys',
                'tenri_synced_at',
            ]);

            DB::statement('ALTER TABLE usuarios MODIFY empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
        });
    }
};
