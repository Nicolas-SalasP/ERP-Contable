<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // moneda ya existe en la migración SII (2026_06_01_110003); se omite para evitar duplicado.
            if (!Schema::hasColumn('facturas', 'tipo_cambio')) {
                $table->decimal('tipo_cambio', 12, 4)->nullable()->after('moneda');
            }
            if (!Schema::hasColumn('facturas', 'monto_bruto_origen')) {
                $table->decimal('monto_bruto_origen', 14, 2)->nullable()->after('tipo_cambio');
            }
            if (!Schema::hasColumn('facturas', 'es_documento_exterior')) {
                $table->boolean('es_documento_exterior')->default(false)->after('monto_bruto_origen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(array_filter(
                ['tipo_cambio', 'monto_bruto_origen', 'es_documento_exterior'],
                fn (string $col) => Schema::hasColumn('facturas', $col),
            ));
        });
    }
};
