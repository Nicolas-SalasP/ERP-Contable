<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('permisos')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {

        });
    }
};
