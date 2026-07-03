<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // DashboardResumenService::flujoCaja30d() y proximasVencer() filtran
    // facturas por empresa_id + fecha_vencimiento en cada carga del dashboard,
    // pero esa columna nunca tuvo índice (ni en 2026_06_29_000001 ni en
    // 2026_07_03_000001, que solo cubren fecha_emision/tipo/estado).
    private array $indices = [
        ['facturas', 'facturas_empresa_fecha_vencimiento_idx', ['empresa_id', 'fecha_vencimiento']],
    ];

    public function up(): void
    {
        foreach ($this->indices as [$table, $name, $columns]) {
            if (!Schema::hasIndex($table, $name)) {
                Schema::table($table, function (Blueprint $t) use ($name, $columns) {
                    $t->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indices as [$table, $name]) {
            if (Schema::hasIndex($table, $name)) {
                Schema::table($table, function (Blueprint $t) use ($name) {
                    $t->dropIndex($name);
                });
            }
        }
    }
};
