<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Catálogo global de tipos de ajuste crítico
        |--------------------------------------------------------------------------
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | No se usan codigo_dte, codigo_sii, folio_dte, xml_dte ni lógica SII.
        |
        | Este catálogo es global porque los tipos operacionales de merma,
        | deterioro, pérdida y vencimiento aplican transversalmente a todas
        | las empresas.
        |
        */
        Schema::create('inventario_tipos_ajuste_critico', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 60)->unique();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Tipo de movimiento asociado
            |--------------------------------------------------------------------------
            |
            | Se usan los tipos existentes de inventario_movimientos.
            | No se crean nuevos tipos en la tabla de movimientos para no romper
            | Kardex, valorización ni lógica de Fase 2/Fase 3.
            |
            */
            $table->enum('tipo_movimiento', [
                'ajuste_positivo',
                'ajuste_negativo',
            ])->default('ajuste_negativo');

            /*
            |--------------------------------------------------------------------------
            | Requiere stock
            |--------------------------------------------------------------------------
            |
            | true: exige stock suficiente. Aplica normalmente a ajustes negativos.
            | false: no exige stock previo. Aplica a ajustes positivos críticos.
            |
            */
            $table->boolean('requiere_stock')->default(true);

            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(
                ['tipo_movimiento', 'activo'],
                'idx_inv_tipo_ajuste_tipo_activo'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Registro especializado de ajustes críticos
        |--------------------------------------------------------------------------
        |
        | Esta tabla guarda la trazabilidad profesional del evento crítico.
        | El movimiento real de stock sigue quedando en inventario_movimientos.
        |
        */
        Schema::create('inventario_ajustes_criticos', function (Blueprint $table) {
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
            | Relación con movimiento real de inventario
            |--------------------------------------------------------------------------
            |
            | Cada ajuste crítico debe tener un movimiento asociado en Kardex.
            |
            */
            $table->foreignId('movimiento_inventario_id')
                ->constrained('inventario_movimientos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Tipo crítico
            |--------------------------------------------------------------------------
            */
            $table->foreignId('tipo_ajuste_critico_id')
                ->constrained('inventario_tipos_ajuste_critico')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Producto y bodega afectados
            |--------------------------------------------------------------------------
            */
            $table->foreignId('producto_id')
                ->constrained('inventario_productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('bodega_id')
                ->constrained('inventario_bodegas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Cantidad y valorización snapshot
            |--------------------------------------------------------------------------
            |
            | costo_unitario y costo_total se guardan como snapshot del momento
            | del ajuste crítico.
            |
            */
            $table->decimal('cantidad', 15, 4);
            $table->decimal('costo_unitario', 15, 4)->default(0);
            $table->decimal('costo_total', 15, 4)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Trazabilidad obligatoria
            |--------------------------------------------------------------------------
            |
            | motivo y observacion son obligatorios para profesionalizar mermas,
            | deterioros, pérdidas y vencimientos.
            |
            */
            $table->string('motivo', 180);
            $table->text('observacion');

            /*
            |--------------------------------------------------------------------------
            | Referencias genéricas NO tributarias
            |--------------------------------------------------------------------------
            |
            | No usar codigo_dte, codigo_sii, folio_dte, xml_dte ni lógica SII.
            |
            */
            $table->string('referencia', 120)->nullable();
            $table->string('origen_modulo', 80)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Auditoría
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('registrado_por')->nullable()->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices para reportes
            |--------------------------------------------------------------------------
            */
            $table->index(
                ['empresa_id', 'created_at'],
                'idx_inv_ajuste_empresa_fecha'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'created_at'],
                'idx_inv_ajuste_empresa_producto_fecha'
            );

            $table->index(
                ['empresa_id', 'bodega_id', 'created_at'],
                'idx_inv_ajuste_empresa_bodega_fecha'
            );

            $table->index(
                ['empresa_id', 'tipo_ajuste_critico_id', 'created_at'],
                'idx_inv_ajuste_empresa_tipo_fecha'
            );

            $table->index(
                ['empresa_id', 'origen_modulo', 'origen_id'],
                'idx_inv_ajuste_empresa_origen'
            );
        });

        $this->insertarTiposBase();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_ajustes_criticos');
        Schema::dropIfExists('inventario_tipos_ajuste_critico');
    }

    private function insertarTiposBase(): void
    {
        $ahora = now();

        DB::table('inventario_tipos_ajuste_critico')->insert([
            [
                'codigo' => 'MERMA_OPERACIONAL',
                'nombre' => 'Merma operacional',
                'descripcion' => 'Disminución de inventario por procesos internos, manipulación o pérdida operacional normal.',
                'tipo_movimiento' => 'ajuste_negativo',
                'requiere_stock' => true,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'DETERIORO',
                'nombre' => 'Deterioro',
                'descripcion' => 'Producto deteriorado, dañado o no apto para operación normal.',
                'tipo_movimiento' => 'ajuste_negativo',
                'requiere_stock' => true,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'PERDIDA',
                'nombre' => 'Pérdida',
                'descripcion' => 'Pérdida física o administrativa de unidades de inventario.',
                'tipo_movimiento' => 'ajuste_negativo',
                'requiere_stock' => true,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'VENCIMIENTO',
                'nombre' => 'Vencimiento',
                'descripcion' => 'Producto vencido o fuera de condición comercial/operacional.',
                'tipo_movimiento' => 'ajuste_negativo',
                'requiere_stock' => true,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'AJUSTE_CRITICO_NEGATIVO',
                'nombre' => 'Ajuste crítico negativo',
                'descripcion' => 'Corrección sensible que disminuye stock y requiere trazabilidad reforzada.',
                'tipo_movimiento' => 'ajuste_negativo',
                'requiere_stock' => true,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'AJUSTE_CRITICO_POSITIVO',
                'nombre' => 'Ajuste crítico positivo',
                'descripcion' => 'Corrección sensible que aumenta stock y requiere trazabilidad reforzada.',
                'tipo_movimiento' => 'ajuste_positivo',
                'requiere_stock' => false,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);
    }
};