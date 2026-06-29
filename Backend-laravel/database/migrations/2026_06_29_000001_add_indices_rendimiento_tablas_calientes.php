<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega índices compuestos en las tablas de mayor tráfico del ERP
 * para optimizar consultas filtradas por empresa_id + columna de rango/estado.
 *
 * Usa CREATE INDEX IF NOT EXISTS (MySQL 8+) para que sea idempotente:
 * se puede ejecutar varias veces sin error aunque el índice ya exista.
 */
return new class extends Migration {
    /**
     * Lista de sentencias CREATE INDEX con su nombre único.
     * Formato: [nombre_índice, sentencia completa].
     *
     * @var array<int, array{string, string}>
     */
    private array $indices = [
        // ── facturas ──────────────────────────────────────────────────────
        // Búsquedas por empresa + rango de fechas (libro de compras, reports)
        ['facturas_empresa_fecha_idx',
            'CREATE INDEX IF NOT EXISTS facturas_empresa_fecha_idx ON facturas (empresa_id, fecha_emision)'],

        // Búsquedas por empresa + estado (PENDIENTE, PAGADA, ANULADA…)
        ['facturas_empresa_estado_idx',
            'CREATE INDEX IF NOT EXISTS facturas_empresa_estado_idx ON facturas (empresa_id, estado)'],

        // ── asientos_contables ────────────────────────────────────────────
        // Búsquedas por empresa + rango de fechas (mayor, balance, cierre)
        ['asientos_empresa_fecha_idx',
            'CREATE INDEX IF NOT EXISTS asientos_empresa_fecha_idx ON asientos_contables (empresa_id, fecha)'],

        // Búsquedas por empresa + estado (CONTABILIZADO, BORRADOR…)
        ['asientos_empresa_estado_idx',
            'CREATE INDEX IF NOT EXISTS asientos_empresa_estado_idx ON asientos_contables (empresa_id, estado)'],

        // ── movimientos_bancarios ─────────────────────────────────────────
        // Búsquedas por empresa + rango de fechas (conciliación bancaria)
        ['movimientos_empresa_fecha_idx',
            'CREATE INDEX IF NOT EXISTS movimientos_empresa_fecha_idx ON movimientos_bancarios (empresa_id, fecha)'],

        // Búsquedas por empresa + estado (PENDIENTE, CONCILIADO…)
        ['movimientos_empresa_estado_idx',
            'CREATE INDEX IF NOT EXISTS movimientos_empresa_estado_idx ON movimientos_bancarios (empresa_id, estado)'],

        // Búsquedas por empresa + cuenta bancaria (extracto por cuenta)
        ['movimientos_empresa_cuenta_idx',
            'CREATE INDEX IF NOT EXISTS movimientos_empresa_cuenta_idx ON movimientos_bancarios (empresa_id, cuenta_bancaria_id)'],

        // ── detalles_asiento ──────────────────────────────────────────────
        // JOIN con asientos_contables: es la relación más consultada del módulo contable
        ['detalles_asiento_id_idx',
            'CREATE INDEX IF NOT EXISTS detalles_asiento_id_idx ON detalles_asiento (asiento_id)'],
    ];

    public function up(): void
    {
        foreach ($this->indices as [$nombre, $sentencia]) {
            DB::statement($sentencia);
        }
    }

    public function down(): void
    {
        // DROP INDEX IF EXISTS requiere especificar la tabla en MySQL
        DB::statement('DROP INDEX IF EXISTS facturas_empresa_fecha_idx ON facturas');
        DB::statement('DROP INDEX IF EXISTS facturas_empresa_estado_idx ON facturas');
        DB::statement('DROP INDEX IF EXISTS asientos_empresa_fecha_idx ON asientos_contables');
        DB::statement('DROP INDEX IF EXISTS asientos_empresa_estado_idx ON asientos_contables');
        DB::statement('DROP INDEX IF EXISTS movimientos_empresa_fecha_idx ON movimientos_bancarios');
        DB::statement('DROP INDEX IF EXISTS movimientos_empresa_estado_idx ON movimientos_bancarios');
        DB::statement('DROP INDEX IF EXISTS movimientos_empresa_cuenta_idx ON movimientos_bancarios');
        DB::statement('DROP INDEX IF EXISTS detalles_asiento_id_idx ON detalles_asiento');
    }
};
