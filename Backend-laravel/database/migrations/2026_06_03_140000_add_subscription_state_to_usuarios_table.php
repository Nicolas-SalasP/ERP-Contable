<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'subscription_status')) {
                $table->string('subscription_status')->default('active')->after('module_keys');
            }
            if (!Schema::hasColumn('usuarios', 'subscription_ends_at')) {
                $table->timestamp('subscription_ends_at')->nullable()->after('subscription_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            foreach (['subscription_status', 'subscription_ends_at'] as $col) {
                if (Schema::hasColumn('usuarios', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
