<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('metodo_pago', 100)->nullable()->after('notas_condiciones');
            $table->string('condiciones_pago', 150)->nullable()->after('metodo_pago');
            $table->string('plazo_entrega', 150)->nullable()->after('condiciones_pago');
            $table->text('comentarios')->nullable()->after('plazo_entrega');
            $table->string('garantia', 255)->nullable()->after('comentarios');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['metodo_pago', 'condiciones_pago', 'plazo_entrega', 'comentarios', 'garantia']);
        });
    }
};
