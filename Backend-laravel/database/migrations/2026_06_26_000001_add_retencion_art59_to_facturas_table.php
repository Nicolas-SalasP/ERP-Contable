<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Categoría de gasto según Art. 59 LIR (solo para documentos del exterior)
            $table->string('tipo_gasto_art59')->nullable()->after('es_documento_exterior');
            // Monto retenido al proveedor extranjero, por enterar vía F50
            $table->decimal('retencion_art59', 15, 2)->nullable()->after('tipo_gasto_art59');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['tipo_gasto_art59', 'retencion_art59']);
        });
    }
};
