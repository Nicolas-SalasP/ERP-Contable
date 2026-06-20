<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('moneda', 3);
            $table->decimal('valor_clp', 12, 4);
            $table->timestamps();
            $table->unique(['fecha', 'moneda']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_cambio');
    }
};
