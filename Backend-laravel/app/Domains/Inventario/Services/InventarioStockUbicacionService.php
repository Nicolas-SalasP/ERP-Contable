<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioStockUbicacionService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos
    ) {
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigirAlguno($usuario, [
            'inventario.stock_ubicaciones.ver',
            'inventario.disponibilidad.ver',
            'inventario.ubicaciones.ver',
        ]);

        return StockUbicacionInventario::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'ubicacion:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo,estado_operativo',
            ])
            ->when(!empty($filtros['producto_id']), fn (Builder $query) => $query->where('producto_id', (int) $filtros['producto_id']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['ubicacion_id']), fn (Builder $query) => $query->where('ubicacion_id', (int) $filtros['ubicacion_id']))
            ->when(!empty($filtros['lote_id']), fn (Builder $query) => $query->where('lote_id', (int) $filtros['lote_id']))
            ->orderBy('bodega_id')
            ->orderBy('ubicacion_id')
            ->orderBy('producto_id')
            ->paginate($this->normalizarPerPage($filtros['per_page'] ?? 15));
    }

    public function moverStock(User $usuario, array $datos): array
    {
        $this->permisos->exigirAlguno($usuario, [
            'inventario.putaway.ejecutar',
            'inventario.stock_ubicaciones.mover',
        ]);

        return DB::transaction(function () use ($usuario, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $producto = $this->obtenerProductoActivoEmpresa((int) $datos['producto_id'], $empresaId, 'producto_id');
            $bodegaOrigen = $this->obtenerBodegaActivaEmpresa((int) $datos['bodega_origen_id'], $empresaId, 'bodega_origen_id');
            $bodegaDestino = $this->obtenerBodegaActivaEmpresa((int) $datos['bodega_destino_id'], $empresaId, 'bodega_destino_id');
            $ubicacionOrigen = $this->obtenerUbicacionActivaEmpresaBodega((int) $datos['ubicacion_origen_id'], $empresaId, (int) $bodegaOrigen->id, 'ubicacion_origen_id');
            $ubicacionDestino = $this->obtenerUbicacionActivaEmpresaBodega((int) $datos['ubicacion_destino_id'], $empresaId, (int) $bodegaDestino->id, 'ubicacion_destino_id');
            $cantidad = $this->validarCantidadPositiva($datos['cantidad'] ?? null, 'cantidad');
            $loteId = $this->normalizarLoteId($producto, $datos['lote_id'] ?? null, $empresaId, 'lote_id');

            if ((int) $ubicacionOrigen->id === (int) $ubicacionDestino->id) {
                throw ValidationException::withMessages([
                    'ubicacion_destino_id' => 'La ubicación destino debe ser distinta a la ubicación origen.',
                ]);
            }

            $origen = $this->aplicarSalidaDesdeEstado(
                empresaId: $empresaId,
                productoId: (int) $producto->id,
                bodegaId: (int) $bodegaOrigen->id,
                ubicacionId: (int) $ubicacionOrigen->id,
                loteId: $loteId,
                cantidad: $cantidad,
                estadoOrigen: $datos['estado_stock_origen'] ?? StockUbicacionInventario::ESTADO_DISPONIBLE,
                campo: 'cantidad'
            );

            $destino = $this->aplicarEntrada(
                empresaId: $empresaId,
                productoId: (int) $producto->id,
                bodegaId: (int) $bodegaDestino->id,
                ubicacionId: (int) $ubicacionDestino->id,
                loteId: $loteId,
                cantidad: $cantidad,
                estadoDestino: $datos['estado_stock_destino'] ?? StockUbicacionInventario::ESTADO_DISPONIBLE
            );

            return [
                'origen' => $origen->fresh(['producto', 'bodega', 'ubicacion', 'lote']),
                'destino' => $destino->fresh(['producto', 'bodega', 'ubicacion', 'lote']),
            ];
        });
    }

    public function aplicarEntrada(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        int $ubicacionId,
        ?int $loteId,
        float $cantidad,
        ?string $estadoDestino = null
    ): StockUbicacionInventario {
        $cantidad = $this->validarCantidadPositiva($cantidad, 'cantidad');
        $estadoDestino = $this->normalizarEstado($estadoDestino);
        $this->validarUbicacionActivaEmpresaBodega($ubicacionId, $empresaId, $bodegaId, 'ubicacion_destino_id');

        $stock = $this->obtenerOCrearStockBloqueado($empresaId, $productoId, $bodegaId, $ubicacionId, $loteId);

        $stock->stock_actual = $this->redondearCantidad((float) $stock->stock_actual + $cantidad);

        match ($estadoDestino) {
            StockUbicacionInventario::ESTADO_CUARENTENA => $stock->stock_cuarentena = $this->redondearCantidad((float) $stock->stock_cuarentena + $cantidad),
            StockUbicacionInventario::ESTADO_BLOQUEADO => $stock->stock_bloqueado = $this->redondearCantidad((float) $stock->stock_bloqueado + $cantidad),
            StockUbicacionInventario::ESTADO_EN_RECEPCION,
            StockUbicacionInventario::ESTADO_EN_PUTAWAY,
            StockUbicacionInventario::ESTADO_EN_TRANSITO_INTERNO => $stock->stock_en_transito = $this->redondearCantidad((float) $stock->stock_en_transito + $cantidad),
            default => null,
        };

        $this->validarBucketsNoSuperenFisico($stock);
        $stock->save();

        return $stock;
    }

    public function aplicarSalida(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        int $ubicacionId,
        ?int $loteId,
        float $cantidad,
        string $campo = 'cantidad'
    ): StockUbicacionInventario {
        $cantidad = $this->validarCantidadPositiva($cantidad, $campo);
        $this->validarUbicacionActivaEmpresaBodega($ubicacionId, $empresaId, $bodegaId, 'ubicacion_origen_id');

        $stock = $this->obtenerOCrearStockBloqueado($empresaId, $productoId, $bodegaId, $ubicacionId, $loteId);

        if (!$stock->tieneDisponible($cantidad)) {
            throw ValidationException::withMessages([
                $campo => 'Stock disponible insuficiente en la ubicación seleccionada.',
            ]);
        }

        $stock->stock_actual = $this->redondearCantidad((float) $stock->stock_actual - $cantidad);
        $this->validarNoNegativo($stock->stock_actual, $campo, 'La operación dejaría stock físico negativo en la ubicación.');
        $this->validarBucketsNoSuperenFisico($stock);
        $stock->save();

        return $stock;
    }

    public function aplicarSalidaDesdeEstado(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        int $ubicacionId,
        ?int $loteId,
        float $cantidad,
        ?string $estadoOrigen = null,
        string $campo = 'cantidad'
    ): StockUbicacionInventario {
        $cantidad = $this->validarCantidadPositiva($cantidad, $campo);
        $estadoOrigen = $this->normalizarEstado($estadoOrigen);
        $this->validarUbicacionActivaEmpresaBodega($ubicacionId, $empresaId, $bodegaId, 'ubicacion_origen_id');

        $stock = $this->obtenerOCrearStockBloqueado($empresaId, $productoId, $bodegaId, $ubicacionId, $loteId);

        if ((float) $stock->stock_actual < $cantidad) {
            throw ValidationException::withMessages([
                $campo => 'La operación dejaría stock físico negativo en la ubicación origen.',
            ]);
        }

        match ($estadoOrigen) {
            StockUbicacionInventario::ESTADO_DISPONIBLE => $this->validarStockDisponibleParaSalida($stock, $cantidad, $campo),
            StockUbicacionInventario::ESTADO_CUARENTENA => $this->descontarBucket($stock, 'stock_cuarentena', $cantidad, $campo, 'Stock en cuarentena insuficiente en la ubicación origen.'),
            StockUbicacionInventario::ESTADO_BLOQUEADO => $this->descontarBucket($stock, 'stock_bloqueado', $cantidad, $campo, 'Stock bloqueado insuficiente en la ubicación origen.'),
            StockUbicacionInventario::ESTADO_EN_RECEPCION,
            StockUbicacionInventario::ESTADO_EN_PUTAWAY,
            StockUbicacionInventario::ESTADO_EN_TRANSITO_INTERNO => $this->descontarBucket($stock, 'stock_en_transito', $cantidad, $campo, 'Stock en tránsito/recepción insuficiente en la ubicación origen.'),
            default => null,
        };

        $stock->stock_actual = $this->redondearCantidad((float) $stock->stock_actual - $cantidad);
        $this->validarBucketsNoSuperenFisico($stock);
        $stock->save();

        return $stock;
    }

    public function reservar(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        int $ubicacionId,
        ?int $loteId,
        float $cantidad,
        string $campo = 'cantidad'
    ): StockUbicacionInventario {
        $cantidad = $this->validarCantidadPositiva($cantidad, $campo);
        $this->validarUbicacionActivaEmpresaBodega($ubicacionId, $empresaId, $bodegaId, 'ubicacion_id');

        $stock = $this->obtenerOCrearStockBloqueado($empresaId, $productoId, $bodegaId, $ubicacionId, $loteId);

        if (!$stock->tieneDisponible($cantidad)) {
            throw ValidationException::withMessages([
                $campo => 'Stock disponible insuficiente en la ubicación para crear la reserva.',
            ]);
        }

        $stock->stock_reservado = $this->redondearCantidad((float) $stock->stock_reservado + $cantidad);
        $this->validarBucketsNoSuperenFisico($stock);
        $stock->save();

        return $stock;
    }

    public function liberarReserva(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        int $ubicacionId,
        ?int $loteId,
        float $cantidad,
        string $campo = 'cantidad'
    ): StockUbicacionInventario {
        $cantidad = $this->validarCantidadPositiva($cantidad, $campo);
        $stock = $this->obtenerOCrearStockBloqueado($empresaId, $productoId, $bodegaId, $ubicacionId, $loteId);

        if ((float) $stock->stock_reservado < $cantidad) {
            throw ValidationException::withMessages([
                $campo => 'No se puede liberar más stock reservado que el existente en la ubicación.',
            ]);
        }

        $stock->stock_reservado = $this->redondearCantidad((float) $stock->stock_reservado - $cantidad);
        $this->validarBucketsNoSuperenFisico($stock);
        $stock->save();

        return $stock;
    }

    public function consumirReserva(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        int $ubicacionId,
        ?int $loteId,
        float $cantidad,
        string $campo = 'cantidad'
    ): StockUbicacionInventario {
        $cantidad = $this->validarCantidadPositiva($cantidad, $campo);
        $stock = $this->obtenerOCrearStockBloqueado($empresaId, $productoId, $bodegaId, $ubicacionId, $loteId);

        if ((float) $stock->stock_reservado < $cantidad) {
            throw ValidationException::withMessages([
                $campo => 'No se puede consumir más stock reservado que el existente en la ubicación.',
            ]);
        }

        if ((float) $stock->stock_actual < $cantidad) {
            throw ValidationException::withMessages([
                $campo => 'La operación dejaría stock físico negativo en la ubicación.',
            ]);
        }

        $stock->stock_reservado = $this->redondearCantidad((float) $stock->stock_reservado - $cantidad);
        $stock->stock_actual = $this->redondearCantidad((float) $stock->stock_actual - $cantidad);
        $this->validarBucketsNoSuperenFisico($stock);
        $stock->save();

        return $stock;
    }

    public function obtenerOCrearStockBloqueado(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        int $ubicacionId,
        ?int $loteId = null
    ): StockUbicacionInventario {
        $loteKey = $loteId ?? 0;

        $stock = StockUbicacionInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('ubicacion_id', $ubicacionId)
            ->where('lote_key', $loteKey)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        try {
            StockUbicacionInventario::create([
                'empresa_id' => $empresaId,
                'producto_id' => $productoId,
                'bodega_id' => $bodegaId,
                'ubicacion_id' => $ubicacionId,
                'lote_id' => $loteId,
                'lote_key' => $loteKey,
                'stock_actual' => 0,
                'stock_reservado' => 0,
                'stock_bloqueado' => 0,
                'stock_cuarentena' => 0,
                'stock_en_transito' => 0,
            ]);
        } catch (QueryException) {
            // Otro proceso puede haber creado la fila entre SELECT e INSERT.
        }

        return StockUbicacionInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('ubicacion_id', $ubicacionId)
            ->where('lote_key', $loteKey)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function validarUbicacionActivaEmpresaBodega(int $ubicacionId, int $empresaId, int $bodegaId, string $campo): InventarioUbicacion
    {
        return $this->obtenerUbicacionActivaEmpresaBodega($ubicacionId, $empresaId, $bodegaId, $campo);
    }

    private function validarStockDisponibleParaSalida(StockUbicacionInventario $stock, float $cantidad, string $campo): void
    {
        if (!$stock->tieneDisponible($cantidad)) {
            throw ValidationException::withMessages([
                $campo => 'Stock disponible insuficiente en la ubicación origen.',
            ]);
        }
    }

    private function descontarBucket(
        StockUbicacionInventario $stock,
        string $bucket,
        float $cantidad,
        string $campo,
        string $mensaje
    ): void {
        if ((float) $stock->{$bucket} < $cantidad) {
            throw ValidationException::withMessages([
                $campo => $mensaje,
            ]);
        }

        $stock->{$bucket} = $this->redondearCantidad((float) $stock->{$bucket} - $cantidad);
    }

    private function obtenerProductoActivoEmpresa(int $productoId, int $empresaId, string $campo): Producto
    {
        $producto = Producto::query()->where('empresa_id', $empresaId)->find($productoId);

        if (!$producto) {
            throw ValidationException::withMessages([$campo => 'El producto informado no existe o no pertenece a la empresa.']);
        }

        if (!$producto->activo) {
            throw ValidationException::withMessages([$campo => 'El producto informado está inactivo.']);
        }

        return $producto;
    }

    private function obtenerBodegaActivaEmpresa(int $bodegaId, int $empresaId, string $campo): Bodega
    {
        $bodega = Bodega::query()->where('empresa_id', $empresaId)->find($bodegaId);

        if (!$bodega) {
            throw ValidationException::withMessages([$campo => 'La bodega informada no existe o no pertenece a la empresa.']);
        }

        if ($bodega->estado !== 'ACTIVA') {
            throw ValidationException::withMessages([$campo => 'La bodega informada está inactiva.']);
        }

        return $bodega;
    }

    private function obtenerUbicacionActivaEmpresaBodega(int $ubicacionId, int $empresaId, int $bodegaId, string $campo): InventarioUbicacion
    {
        $ubicacion = InventarioUbicacion::query()
            ->where('empresa_id', $empresaId)
            ->where('bodega_id', $bodegaId)
            ->find($ubicacionId);

        if (!$ubicacion) {
            throw ValidationException::withMessages([$campo => 'La ubicación no existe, no pertenece a la empresa o no pertenece a la bodega.']);
        }

        if (!$ubicacion->activo) {
            throw ValidationException::withMessages([$campo => 'La ubicación informada está inactiva.']);
        }

        return $ubicacion;
    }

    private function normalizarLoteId(Producto $producto, mixed $loteId, int $empresaId, string $campo): ?int
    {
        if (!$producto->maneja_lotes && empty($loteId)) {
            return null;
        }

        if (!$producto->maneja_lotes && !empty($loteId)) {
            throw ValidationException::withMessages([$campo => 'El producto no maneja lotes, por lo tanto no debe informar lote_id.']);
        }

        if ($producto->maneja_lotes && empty($loteId)) {
            throw ValidationException::withMessages([$campo => 'El producto maneja lotes, debe informar lote_id.']);
        }

        $lote = LoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $producto->id)
            ->find((int) $loteId);

        if (!$lote || !$lote->activo) {
            throw ValidationException::withMessages([$campo => 'El lote informado no existe, está inactivo o no pertenece al producto/empresa.']);
        }

        if ($lote->estaVencido() || $lote->estaBloqueadoOperativamente()) {
            throw ValidationException::withMessages([$campo => 'No se permite operar stock desde un lote vencido, en cuarentena o bloqueado.']);
        }

        return (int) $lote->id;
    }

    private function normalizarEstado(?string $estado): string
    {
        $estado = $estado ?: StockUbicacionInventario::ESTADO_DISPONIBLE;

        if (!in_array($estado, StockUbicacionInventario::estadosPermitidos(), true)) {
            throw ValidationException::withMessages([
                'estado_stock' => 'El estado de stock informado no es válido.',
            ]);
        }

        return $estado;
    }

    private function validarBucketsNoSuperenFisico(StockUbicacionInventario $stock): void
    {
        $comprometido = (float) $stock->stock_reservado
            + (float) $stock->stock_bloqueado
            + (float) $stock->stock_cuarentena
            + (float) $stock->stock_en_transito;

        if ($comprometido - (float) $stock->stock_actual > 0.0001) {
            throw ValidationException::withMessages([
                'stock' => 'La suma de stock reservado, bloqueado, cuarentena y tránsito supera el stock físico de la ubicación.',
            ]);
        }
    }

    private function validarCantidadPositiva(mixed $cantidad, string $campo): float
    {
        if (!is_numeric($cantidad)) {
            throw ValidationException::withMessages([$campo => 'La cantidad debe ser numérica.']);
        }

        $cantidad = $this->redondearCantidad((float) $cantidad);

        if ($cantidad <= 0) {
            throw ValidationException::withMessages([$campo => 'La cantidad debe ser mayor a cero.']);
        }

        return $cantidad;
    }

    private function validarNoNegativo(mixed $valor, string $campo, string $mensaje): void
    {
        if ((float) $valor < -0.0001) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        return $perPage <= 0 ? 15 : min($perPage, 100);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}
