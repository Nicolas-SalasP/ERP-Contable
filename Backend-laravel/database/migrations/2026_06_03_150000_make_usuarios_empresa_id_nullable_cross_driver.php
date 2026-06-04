<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // En MySQL empresa_id ya quedo nullable via 2026_05_21_160000 (ALTER especifico).
        // Esta migracion cubre los drivers donde ese ALTER no aplica (sqlite en tests),
        // para que la provision SSO (usuarios sin empresa) funcione tambien ahi.
        if (DB::getDriverName() === 'mysql') {
            return;
        }

        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Irreversible de forma segura entre drivers; no-op.
    }
};
