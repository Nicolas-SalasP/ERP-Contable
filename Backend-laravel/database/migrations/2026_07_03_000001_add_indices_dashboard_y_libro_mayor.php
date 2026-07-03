<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Complementa 2026_06_29_000001: DashboardResumenService combina
    // sistemáticamente empresa_id+tipo+estado+fecha en ~10 queries por carga,
    // pero los índices existentes solo cubren pares parciales (sin `tipo`).
    // detalles_asiento.cuenta_contable tampoco tenía índice pese a ser el
    // filtro principal de Libro Mayor/Balance de Comprobación.
    private array $indices = [
        ['facturas', 'facturas_empresa_tipo_estado_fecha_idx', ['empresa_id', 'tipo', 'estado', 'fecha_emision']],
        ['detalles_asiento', 'detalles_asiento_cuenta_idx', ['cuenta_contable']],
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
