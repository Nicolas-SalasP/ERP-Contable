<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('comuna', 20)->nullable();
            $table->string('ciudad', 20)->nullable();
            $table->string('giro', 80)->nullable();
            $table->unsignedInteger('codigo_actividad')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'comuna',
                'ciudad',
                'giro',
                'codigo_actividad',
            ]);
        });
    }
};
