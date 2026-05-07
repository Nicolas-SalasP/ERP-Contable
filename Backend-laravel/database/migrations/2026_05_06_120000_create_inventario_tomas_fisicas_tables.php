<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Tomas físicas de inventario
        |--------------------------------------------------------------------------
        |
        | Una toma física registra un proceso de conteo real de stock físico.
        | No modifica stock al crearse, iniciarse, contarse ni cerrarse.
        |
        | El stock real solo se modifica cuando una toma CERRADA se AJUSTA,
        | delegando los movimientos reales a InventarioMovimientoService.
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | No usar codigo_dte, codigo_sii, folio_dte, xml_dte ni lógica SII.
        |
        */
        Schema::create('inventario_tomas_fisicas', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Multiempresa
            |--------------------------------------------------------------------------
            */
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identificación de toma física
            |--------------------------------------------------------------------------
            |
            | codigo_toma es único por empresa para trazabilidad operacional,
            | auditoría, Postman, frontend y reportes futuros.
            |
            */
            $table->string('codigo_toma', 60);

            /*
            |--------------------------------------------------------------------------
            | Estado de la toma física
            |--------------------------------------------------------------------------
            |
            | BORRADOR:
            | - Toma creada y detalles preparados con snapshot de stock_sistema.
            |
            | EN_CONTEO:
            | - Permite registrar conteos físicos.
            |
            | CERRADA:
            | - Conteos bloqueados y diferencias listas para revisión/ajuste.
            |
            | AJUSTADA:
            | - Ya generó movimientos reales de ajuste.
            |
            | CANCELADA:
            | - Toma anulada antes de impactar stock.
            |
            */
            $table->enum('estado', [
                'BORRADOR',
                'EN_CONTEO',
                'CERRADA',
                'AJUSTADA',
                'CANCELADA',
            ])->default('BORRADOR');

            /*
            |--------------------------------------------------------------------------
            | Tipo de toma física
            |--------------------------------------------------------------------------
            |
            | GENERAL:
            | - Conteo amplio de inventario.
            |
            | BODEGA:
            | - Conteo focalizado en una bodega.
            |
            | CICLICA:
            | - Conteo operativo recurrente. En Fase 7.0 se controlará por Service.
            |
            */
            $table->enum('tipo', [
                'GENERAL',
                'BODEGA',
                'CICLICA',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Bodega opcional de cabecera
            |--------------------------------------------------------------------------
            |
            | GENERAL puede no tener bodega_id.
            | BODEGA y CICLICA pueden exigir bodega_id por regla de Service.
            |
            */
            $table->foreignId('bodega_id')
                ->nullable()
                ->constrained('inventario_bodegas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Referencias genéricas NO tributarias
            |--------------------------------------------------------------------------
            |
            | Permiten relacionar la toma física con procesos internos,
            | ciclos operativos o módulos futuros sin introducir lógica SII/DTE.
            |
            */
            $table->string('referencia', 120)->nullable();
            $table->string('motivo', 120)->nullable();
            $table->text('observacion')->nullable();

            $table->string('origen_modulo', 80)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Auditoría operacional
            |--------------------------------------------------------------------------
            |
            | Se sigue el patrón actual del dominio Inventario:
            | guardar ID del usuario actor sin imponer FK dura contra users.
            |
            */
            $table->unsignedBigInteger('creado_por')->nullable()->index();
            $table->unsignedBigInteger('cerrado_por')->nullable()->index();
            $table->unsignedBigInteger('ajustado_por')->nullable()->index();
            $table->unsignedBigInteger('cancelado_por')->nullable()->index();

            /*
            |--------------------------------------------------------------------------
            | Fechas de ciclo de vida
            |--------------------------------------------------------------------------
            |
            | created_at registra la creación real de la cabecera.
            | fecha_inicio se completa cuando la toma pasa a EN_CONTEO.
            |
            */
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamp('fecha_ajuste')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */
            $table->unique(
                ['empresa_id', 'codigo_toma'],
                'inv_tomas_fisicas_empresa_codigo_uq'
            );

            $table->index(
                ['empresa_id', 'estado'],
                'idx_inv_tf_empresa_estado'
            );

            $table->index(
                ['empresa_id', 'tipo'],
                'idx_inv_tf_empresa_tipo'
            );

            $table->index(
                ['empresa_id', 'bodega_id'],
                'idx_inv_tf_empresa_bodega'
            );

            $table->index(
                ['empresa_id', 'fecha_inicio'],
                'idx_inv_tf_empresa_fecha_inicio'
            );

            $table->index(
                ['empresa_id', 'fecha_cierre'],
                'idx_inv_tf_empresa_fecha_cierre'
            );

            $table->index(
                ['empresa_id', 'fecha_ajuste'],
                'idx_inv_tf_empresa_fecha_ajuste'
            );

            $table->index(
                ['empresa_id', 'origen_modulo', 'origen_id'],
                'idx_inv_tf_empresa_origen'
            );

            $table->index(
                ['empresa_id', 'referencia'],
                'idx_inv_tf_empresa_referencia'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Detalles de toma física
        |--------------------------------------------------------------------------
        |
        | Cada detalle representa el stock físico esperado y contado para:
        | - producto
        | - bodega
        | - lote opcional
        |
        | stock_sistema es snapshot y NO debe recalcularse automáticamente.
        | stock_contado se registra durante el conteo.
        | diferencia = stock_contado - stock_sistema.
        |
        */
        Schema::create('inventario_toma_fisica_detalles', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Multiempresa
            |--------------------------------------------------------------------------
            */
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Cabecera de toma física
            |--------------------------------------------------------------------------
            */
            $table->foreignId('toma_fisica_id')
                ->constrained('inventario_tomas_fisicas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Producto, bodega y lote opcional
            |--------------------------------------------------------------------------
            |
            | Para productos con lotes, lote_id será obligatorio por Service.
            | Para productos sin lotes, lote_id debe rechazarse por Service.
            |
            */
            $table->foreignId('producto_id')
                ->constrained('inventario_productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('bodega_id')
                ->constrained('inventario_bodegas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('lote_id')
                ->nullable()
                ->constrained('inventario_lotes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Cantidades de conteo
            |--------------------------------------------------------------------------
            |
            | stock_sistema:
            | - Snapshot del stock físico al preparar la toma.
            |
            | stock_contado:
            | - Cantidad registrada por usuario durante EN_CONTEO.
            |
            | diferencia:
            | - stock_contado - stock_sistema.
            |
            */
            $table->decimal('stock_sistema', 18, 4)->default(0);
            $table->decimal('stock_contado', 18, 4)->nullable();
            $table->decimal('diferencia', 18, 4)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Movimiento real generado
            |--------------------------------------------------------------------------
            |
            | Se completa solo al ajustar la toma física.
            | Si diferencia = 0, puede permanecer null porque no se genera movimiento.
            |
            */
            $table->foreignId('movimiento_ajuste_id')
                ->nullable()
                ->constrained('inventario_movimientos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Auditoría de conteo
            |--------------------------------------------------------------------------
            */
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('contado_por')->nullable()->index();
            $table->timestamp('fecha_conteo')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */
            $table->index(
                ['empresa_id', 'toma_fisica_id'],
                'idx_inv_tf_det_empresa_toma'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'bodega_id'],
                'idx_inv_tf_det_empresa_producto_bodega'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'bodega_id', 'lote_id'],
                'idx_inv_tf_det_empresa_producto_bodega_lote'
            );

            $table->index(
                ['empresa_id', 'lote_id'],
                'idx_inv_tf_det_empresa_lote'
            );

            $table->index(
                ['empresa_id', 'movimiento_ajuste_id'],
                'idx_inv_tf_det_empresa_movimiento'
            );

            $table->index(
                ['empresa_id', 'contado_por'],
                'idx_inv_tf_det_empresa_contador'
            );

            $table->index(
                ['empresa_id', 'fecha_conteo'],
                'idx_inv_tf_det_empresa_fecha_conteo'
            );

            /*
            |--------------------------------------------------------------------------
            | Unicidad lógica
            |--------------------------------------------------------------------------
            |
            | En MySQL/MariaDB, UNIQUE con lote_id nullable permite duplicados
            | cuando lote_id es NULL. Por eso esta restricción ayuda para lotes,
            | pero la prevención completa de duplicados debe reforzarse en Service.
            |
            */
            $table->unique(
                ['toma_fisica_id', 'producto_id', 'bodega_id', 'lote_id'],
                'inv_tf_det_toma_producto_bodega_lote_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_toma_fisica_detalles');
        Schema::dropIfExists('inventario_tomas_fisicas');
    }
};