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
        | Reservas de inventario
        |--------------------------------------------------------------------------
        |
        | Una reserva compromete stock disponible, pero NO descuenta stock físico.
        | El stock físico solo se descuenta al consumir la reserva mediante una
        | salida real delegada a InventarioMovimientoService.
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | No usar codigo_dte, codigo_sii, folio_dte, xml_dte ni lógica SII.
        |
        */
        Schema::create('inventario_reservas', function (Blueprint $table) {
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
            | Identificación de reserva
            |--------------------------------------------------------------------------
            |
            | codigo_reserva es único por empresa para permitir trazabilidad
            | operacional y consultas desde Postman/frontend/reportes.
            |
            */
            $table->string('codigo_reserva', 60);

            /*
            |--------------------------------------------------------------------------
            | Estado de la reserva
            |--------------------------------------------------------------------------
            |
            | Estados que comprometen disponibilidad:
            | - ACTIVA
            | - PARCIALMENTE_LIBERADA
            | - PARCIALMENTE_CONSUMIDA
            |
            | Estados que no comprometen disponibilidad:
            | - CONSUMIDA
            | - CANCELADA
            | - EXPIRADA
            |
            */
            $table->enum('estado', [
                'ACTIVA',
                'PARCIALMENTE_LIBERADA',
                'PARCIALMENTE_CONSUMIDA',
                'CONSUMIDA',
                'CANCELADA',
                'EXPIRADA',
            ])->default('ACTIVA');

            /*
            |--------------------------------------------------------------------------
            | Referencias genéricas NO tributarias
            |--------------------------------------------------------------------------
            |
            | Permiten relacionar la reserva con pedidos, módulos futuros o procesos
            | externos sin introducir lógica tributaria/SII en Inventario.
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
            | Se mantiene el patrón actual del dominio: guardar el ID del usuario
            | actor sin imponer FK dura contra usuarios.
            |
            */
            $table->unsignedBigInteger('reservado_por')->nullable()->index();

            $table->timestamp('fecha_reserva')->useCurrent();
            $table->timestamp('fecha_expiracion')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */
            $table->unique(
                ['empresa_id', 'codigo_reserva'],
                'inv_reservas_empresa_codigo_uq'
            );

            $table->index(
                ['empresa_id', 'estado'],
                'idx_inv_reservas_empresa_estado'
            );

            $table->index(
                ['empresa_id', 'fecha_reserva'],
                'idx_inv_reservas_empresa_fecha'
            );

            $table->index(
                ['empresa_id', 'fecha_expiracion'],
                'idx_inv_reservas_empresa_expiracion'
            );

            $table->index(
                ['empresa_id', 'origen_modulo', 'origen_id'],
                'idx_inv_reservas_empresa_origen'
            );

            $table->index(
                ['empresa_id', 'referencia'],
                'idx_inv_reservas_empresa_referencia'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Detalles de reserva
        |--------------------------------------------------------------------------
        |
        | Cada detalle compromete una cantidad específica de producto + bodega.
        | Si el producto maneja lotes, lote_id será obligatorio por regla de negocio
        | en el Service, no por la BD, porque depende de inventario_productos.
        |
        */
        Schema::create('inventario_reserva_detalles', function (Blueprint $table) {
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
            | Cabecera de reserva
            |--------------------------------------------------------------------------
            */
            $table->foreignId('reserva_id')
                ->constrained('inventario_reservas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Producto, bodega y lote opcional
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

            $table->foreignId('lote_id')
                ->nullable()
                ->constrained('inventario_lotes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Cantidades de control
            |--------------------------------------------------------------------------
            |
            | cantidad_pendiente lógica:
            | cantidad_reservada - cantidad_consumida - cantidad_liberada
            |
            | La BD guarda los acumulados. La validación de no superar pendientes
            | queda en InventarioReservaService.
            |
            */
            $table->decimal('cantidad_reservada', 15, 4);
            $table->decimal('cantidad_consumida', 15, 4)->default(0);
            $table->decimal('cantidad_liberada', 15, 4)->default(0);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices para disponibilidad y auditoría
            |--------------------------------------------------------------------------
            */
            $table->index(
                ['empresa_id', 'reserva_id'],
                'idx_inv_res_det_empresa_reserva'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'bodega_id'],
                'idx_inv_res_det_empresa_producto_bodega'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'bodega_id', 'lote_id'],
                'idx_inv_res_det_empresa_producto_bodega_lote'
            );

            $table->index(
                ['empresa_id', 'lote_id'],
                'idx_inv_res_det_empresa_lote'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Consumos de reserva
        |--------------------------------------------------------------------------
        |
        | Guarda la relación auditable entre un detalle de reserva y cada salida
        | real generada en inventario_movimientos.
        |
        | Esto permite consumos parciales sin perder trazabilidad histórica.
        |
        */
        Schema::create('inventario_reserva_consumos', function (Blueprint $table) {
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
            | Reserva y detalle consumido
            |--------------------------------------------------------------------------
            */
            $table->foreignId('reserva_id')
                ->constrained('inventario_reservas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('reserva_detalle_id')
                ->constrained('inventario_reserva_detalles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Movimiento real generado
            |--------------------------------------------------------------------------
            |
            | La salida real sigue viviendo en inventario_movimientos.
            | Esta tabla solo une reserva/detalle con movimiento generado.
            |
            */
            $table->foreignId('movimiento_inventario_id')
                ->constrained('inventario_movimientos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Snapshot operacional
            |--------------------------------------------------------------------------
            |
            | Se guardan producto, bodega y lote para consultas/reportes rápidos
            | y para auditoría, aunque también se puedan inferir desde el detalle.
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

            $table->decimal('cantidad_consumida', 15, 4);

            /*
            |--------------------------------------------------------------------------
            | Auditoría
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('consumido_por')->nullable()->index();
            $table->timestamp('fecha_consumo')->useCurrent();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */
            $table->index(
                ['empresa_id', 'reserva_id'],
                'idx_inv_res_consumos_empresa_reserva'
            );

            $table->index(
                ['empresa_id', 'reserva_detalle_id'],
                'idx_inv_res_consumos_empresa_detalle'
            );

            $table->index(
                ['empresa_id', 'movimiento_inventario_id'],
                'idx_inv_res_consumos_empresa_movimiento'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'bodega_id'],
                'idx_inv_res_consumos_empresa_producto_bodega'
            );

            $table->index(
                ['empresa_id', 'producto_id', 'bodega_id', 'lote_id'],
                'idx_inv_res_consumos_empresa_producto_bodega_lote'
            );

            $table->index(
                ['empresa_id', 'fecha_consumo'],
                'idx_inv_res_consumos_empresa_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_reserva_consumos');
        Schema::dropIfExists('inventario_reserva_detalles');
        Schema::dropIfExists('inventario_reservas');
    }
};