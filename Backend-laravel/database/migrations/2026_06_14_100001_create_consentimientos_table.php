<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->nullableMorphs('titular');
            $table->string('politica_version')->nullable();
            $table->string('finalidad');
            $table->string('base_licitud');
            $table->boolean('otorgado')->default(true);
            $table->string('ip')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('otorgado_at')->nullable();
            $table->timestamp('revocado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos');
    }
};
