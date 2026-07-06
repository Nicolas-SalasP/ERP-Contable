<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // asientos_contables y movimientos_bancarios solo tenian codigo_unico/FKs
    // como indice, pero se filtran constantemente por empresa_id + fecha/estado
    // (AsientoContableService, BancoService::obtenerMovimientosPorCuenta,
    // obtenerMovimientosPendientes). facturas ya tiene indice de
    // fecha_vencimiento pero no del patron real de filtro del dashboard/libro
    // compra-venta (empresa_id + tipo + estado + fecha_emision).
    private array $indices = [
        ['asientos_contables', 'asientos_empresa_fecha_idx', ['empresa_id', 'fecha']],
        ['movimientos_bancarios', 'movimientos_empresa_cuenta_fecha_idx', ['empresa_id', 'cuenta_bancaria_id', 'fecha']],
        ['movimientos_bancarios', 'movimientos_empresa_estado_idx', ['empresa_id', 'estado']],
        ['facturas', 'facturas_empresa_tipo_estado_fecha_idx', ['empresa_id', 'tipo', 'estado', 'fecha_emision']],
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
