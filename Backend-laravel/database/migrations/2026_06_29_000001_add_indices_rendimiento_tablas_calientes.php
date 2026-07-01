<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $indices = [
        ['facturas',           'facturas_empresa_fecha_idx',       ['empresa_id', 'fecha_emision']],
        ['facturas',           'facturas_empresa_estado_idx',      ['empresa_id', 'estado']],
        ['asientos_contables', 'asientos_empresa_fecha_idx',       ['empresa_id', 'fecha']],
        ['asientos_contables', 'asientos_empresa_estado_idx',      ['empresa_id', 'estado']],
        ['movimientos_bancarios', 'movimientos_empresa_fecha_idx', ['empresa_id', 'fecha']],
        ['movimientos_bancarios', 'movimientos_empresa_estado_idx',['empresa_id', 'estado']],
        ['movimientos_bancarios', 'movimientos_empresa_cuenta_idx',['empresa_id', 'cuenta_bancaria_id']],
        ['detalles_asiento',   'detalles_asiento_id_idx',          ['asiento_id']],
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
