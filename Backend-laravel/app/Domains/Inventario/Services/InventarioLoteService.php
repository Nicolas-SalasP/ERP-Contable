<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\MovimientoLoteInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockLoteInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioLoteService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Consultas API
    |--------------------------------------------------------------------------
    */

    public function listarLotes(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.lotes.ver');

        $empresaId = (int) $usuario->empresa_id;
        $perPage = $this->normalizarPerPage($filtros['per_page'] ?? 15);

        return LoteInventario::query()
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
            ])
            ->where('empresa_id', $empresaId)
            ->when(!empty($filtros['producto_id']), function ($query) use ($filtros) {
                $query->where('producto_id', (int) $filtros['producto_id']);
            })
            ->when(array_key_exists('activo', $filtros) && $filtros['activo'] !== null && $filtros['activo'] !== '', function ($query) use ($filtros) {
                $query->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(!empty($filtros['search']), function ($query) use ($filtros) {
                $search = trim((string) $filtros['search']);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('codigo_lote', 'like', "%{$search}%")
                        ->orWhereHas('producto', function ($productoQuery) use ($search) {
                            $productoQuery
                                ->where('sku', 'like', "%{$search}%")
                                ->orWhere('nombre', 'like', "%{$search}%");
                        });
                });
            })
            ->when(!empty($filtros['vencidos']), function ($query) {
                $query
                    ->whereNotNull('fecha_vencimiento')
                    ->whereDate('fecha_vencimiento', '<', now()->toDateString());
            })
            ->when(!empty($filtros['por_vencer_hasta']), function ($query) use ($filtros) {
                $query
                    ->whereNotNull('fecha_vencimiento')
                    ->whereDate('fecha_vencimiento', '<=', (string) $filtros['por_vencer_hasta']);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function obtenerLote(User $usuario, int $loteId): LoteInventario
    {
        $this->permisos->exigir($usuario, 'inventario.lotes.ver');

        $lote = LoteInventario::query()
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'stocks.bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->where('empresa_id', (int) $usuario->empresa_id)
            ->find($loteId);

        if (!$lote) {
            throw ValidationException::withMessages([
                'lote_id' => 'El lote solicitado no existe o no pertenece a la empresa.',
            ]);
        }

        return $lote;
    }

    public function listarLotesProducto(User $usuario, int $productoId, array $filtros = [])
    {
        $this->permisos->exigir($usuario, 'inventario.lotes.ver');

        $producto = $this->obtenerProductoEmpresa($productoId, (int) $usuario->empresa_id);

        return LoteInventario::query()
            ->with([
                'stocks.bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->where('empresa_id', (int) $usuario->empresa_id)
            ->where('producto_id', $producto->id)
            ->when(array_key_exists('activo', $filtros) && $filtros['activo'] !== null && $filtros['activo'] !== '', function ($query) use ($filtros) {
                $query->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(!empty($filtros['con_stock']), function ($query) {
                $query->whereHas('stocks', function ($stockQuery) {
                    $stockQuery->where('stock_actual', '>', 0);
                });
            })
            ->orderByRaw('fecha_vencimiento IS NULL')
            ->orderBy('fecha_vencimiento')
            ->orderBy('codigo_lote')
            ->get();
    }

    public function consultarStockPorLote(User $usuario, int $loteId): array
    {
        $this->permisos->exigir($usuario, 'inventario.lotes.ver');

        $lote = $this->obtenerLote($usuario, $loteId);

        $stocks = StockLoteInventario::query()
            ->with([
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->where('empresa_id', (int) $usuario->empresa_id)
            ->where('lote_id', $lote->id)
            ->orderBy('bodega_id')
            ->get();

        return [
            'lote' => $lote,
            'stock_total' => $this->redondearCantidad((float) $stocks->sum('stock_actual')),
            'stock_por_bodega' => $stocks,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Escritura API
    |--------------------------------------------------------------------------
    */

    public function crearLote(User $usuario, array $datos): LoteInventario
    {
        $this->permisos->exigir($usuario, 'inventario.lotes.crear');

        $empresaId = (int) $usuario->empresa_id;

        $producto = $this->obtenerProductoActivoEmpresa(
            (int) ($datos['producto_id'] ?? 0),
            $empresaId
        );

        $datosNormalizados = $this->normalizarDatosLote($producto, $datos);

        $this->validarCodigoLoteUnico(
            empresaId: $empresaId,
            productoId: (int) $producto->id,
            codigoLote: $datosNormalizados['codigo_lote']
        );

        return DB::transaction(function () use ($empresaId, $producto, $datosNormalizados) {
            return LoteInventario::create([
                'empresa_id' => $empresaId,
                'producto_id' => $producto->id,
                'codigo_lote' => $datosNormalizados['codigo_lote'],
                'fecha_fabricacion' => $datosNormalizados['fecha_fabricacion'],
                'fecha_vencimiento' => $datosNormalizados['fecha_vencimiento'],
                'observacion' => $datosNormalizados['observacion'],
                'activo' => $datosNormalizados['activo'],
            ])->load([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
            ]);
        });
    }

    public function actualizarLote(User $usuario, int $loteId, array $datos): LoteInventario
    {
        $this->permisos->exigir($usuario, 'inventario.lotes.editar');

        $empresaId = (int) $usuario->empresa_id;

        $lote = LoteInventario::query()
            ->with('producto')
            ->where('empresa_id', $empresaId)
            ->find($loteId);

        if (!$lote) {
            throw ValidationException::withMessages([
                'lote_id' => 'El lote solicitado no existe o no pertenece a la empresa.',
            ]);
        }

        $producto = $this->obtenerProductoActivoEmpresa((int) $lote->producto_id, $empresaId);

        $datosParaNormalizar = array_merge([
            'codigo_lote' => $lote->codigo_lote,
            'fecha_fabricacion' => optional($lote->fecha_fabricacion)->toDateString(),
            'fecha_vencimiento' => optional($lote->fecha_vencimiento)->toDateString(),
            'observacion' => $lote->observacion,
            'activo' => $lote->activo,
        ], $datos);

        $datosNormalizados = $this->normalizarDatosLote($producto, $datosParaNormalizar);

        $this->validarCodigoLoteUnico(
            empresaId: $empresaId,
            productoId: (int) $producto->id,
            codigoLote: $datosNormalizados['codigo_lote'],
            ignorarLoteId: (int) $lote->id
        );

        return DB::transaction(function () use ($lote, $datosNormalizados) {
            $lote->update([
                'codigo_lote' => $datosNormalizados['codigo_lote'],
                'fecha_fabricacion' => $datosNormalizados['fecha_fabricacion'],
                'fecha_vencimiento' => $datosNormalizados['fecha_vencimiento'],
                'observacion' => $datosNormalizados['observacion'],
                'activo' => $datosNormalizados['activo'],
            ]);

            return $lote->fresh([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'stocks.bodega:id,empresa_id,codigo,nombre,estado',
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Resolución de lotes para movimientos
    |--------------------------------------------------------------------------
    |
    | Estos métodos serán usados por InventarioMovimientoService en el Bloque 5.
    | No crean movimientos ni valorizan stock consolidado.
    |
    */

    public function resolverLoteParaEntrada(Producto $producto, array $datos, int $empresaId): ?LoteInventario
    {
        if (!$producto->maneja_lotes) {
            $this->rechazarPayloadLoteSiProductoNoManejaLotes($datos);

            return null;
        }

        if (!empty($datos['lote_id'])) {
            return $this->obtenerLoteActivoProductoEmpresa(
                loteId: (int) $datos['lote_id'],
                productoId: (int) $producto->id,
                empresaId: $empresaId
            );
        }

        if (!empty($datos['lote']) && is_array($datos['lote'])) {
            return $this->obtenerOCrearLoteDesdePayload($producto, $datos['lote'], $empresaId);
        }

        throw ValidationException::withMessages([
            'lote_id' => 'El producto maneja lotes, por lo tanto debe informar lote_id o lote.',
        ]);
    }

    public function resolverLoteParaSalida(Producto $producto, array $datos, int $empresaId): ?LoteInventario
    {
        if (!$producto->maneja_lotes) {
            $this->rechazarPayloadLoteSiProductoNoManejaLotes($datos);

            return null;
        }

        if (empty($datos['lote_id'])) {
            throw ValidationException::withMessages([
                'lote_id' => 'El producto maneja lotes, por lo tanto debe informar lote_id.',
            ]);
        }

        return $this->obtenerLoteActivoProductoEmpresa(
            loteId: (int) $datos['lote_id'],
            productoId: (int) $producto->id,
            empresaId: $empresaId
        );
    }

    public function resolverLoteParaTraspaso(Producto $producto, array $datos, int $empresaId): ?LoteInventario
    {
        return $this->resolverLoteParaSalida($producto, $datos, $empresaId);
    }

    /*
    |--------------------------------------------------------------------------
    | Aplicación de stock por lote
    |--------------------------------------------------------------------------
    |
    | Estos métodos actualizan inventario_stock_lotes y crean detalle en
    | inventario_movimiento_lotes. Deben ejecutarse dentro de la transacción del
    | movimiento consolidado.
    |
    */

    public function aplicarEntradaLote(
        MovimientoInventario $movimiento,
        Producto $producto,
        int $bodegaDestinoId,
        float $cantidad,
        ?LoteInventario $lote,
        int $empresaId
    ): void {
        if (!$lote) {
            return;
        }

        $stockDestino = $this->obtenerOCrearStockLoteBloqueado(
            productoId: (int) $producto->id,
            bodegaId: $bodegaDestinoId,
            loteId: (int) $lote->id,
            empresaId: $empresaId
        );

        $stockDestinoAntes = $this->toFloat($stockDestino->stock_actual);
        $stockDestinoDespues = $this->redondearCantidad($stockDestinoAntes + $cantidad);

        $stockDestino->update([
            'stock_actual' => $stockDestinoDespues,
        ]);

        $this->registrarDetalleMovimientoLote([
            'empresa_id' => $empresaId,
            'movimiento_inventario_id' => $movimiento->id,
            'producto_id' => $producto->id,
            'lote_id' => $lote->id,
            'bodega_origen_id' => null,
            'bodega_destino_id' => $bodegaDestinoId,
            'cantidad' => $cantidad,
            'stock_lote_origen_antes' => null,
            'stock_lote_origen_despues' => null,
            'stock_lote_destino_antes' => $stockDestinoAntes,
            'stock_lote_destino_despues' => $stockDestinoDespues,
            'costo_unitario' => $this->toFloat($movimiento->costo_unitario),
            'costo_total' => $this->toFloat($movimiento->costo_total),
        ]);
    }

    public function aplicarSalidaLote(
        MovimientoInventario $movimiento,
        Producto $producto,
        int $bodegaOrigenId,
        float $cantidad,
        ?LoteInventario $lote,
        int $empresaId
    ): void {
        if (!$lote) {
            return;
        }

        $stockOrigen = $this->obtenerOCrearStockLoteBloqueado(
            productoId: (int) $producto->id,
            bodegaId: $bodegaOrigenId,
            loteId: (int) $lote->id,
            empresaId: $empresaId
        );

        $stockOrigenAntes = $this->toFloat($stockOrigen->stock_actual);

        if ($stockOrigenAntes < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente en el lote seleccionado.',
            ]);
        }

        $stockOrigenDespues = $this->redondearCantidad($stockOrigenAntes - $cantidad);

        $stockOrigen->update([
            'stock_actual' => $stockOrigenDespues,
        ]);

        $this->registrarDetalleMovimientoLote([
            'empresa_id' => $empresaId,
            'movimiento_inventario_id' => $movimiento->id,
            'producto_id' => $producto->id,
            'lote_id' => $lote->id,
            'bodega_origen_id' => $bodegaOrigenId,
            'bodega_destino_id' => null,
            'cantidad' => $cantidad,
            'stock_lote_origen_antes' => $stockOrigenAntes,
            'stock_lote_origen_despues' => $stockOrigenDespues,
            'stock_lote_destino_antes' => null,
            'stock_lote_destino_despues' => null,
            'costo_unitario' => $this->toFloat($movimiento->costo_unitario),
            'costo_total' => $this->toFloat($movimiento->costo_total),
        ]);
    }

    public function aplicarTraspasoLote(
        MovimientoInventario $movimiento,
        Producto $producto,
        int $bodegaOrigenId,
        int $bodegaDestinoId,
        float $cantidad,
        ?LoteInventario $lote,
        int $empresaId
    ): void {
        if (!$lote) {
            return;
        }

        $stocks = $this->obtenerStocksLoteTraspasoBloqueados(
            productoId: (int) $producto->id,
            bodegaOrigenId: $bodegaOrigenId,
            bodegaDestinoId: $bodegaDestinoId,
            loteId: (int) $lote->id,
            empresaId: $empresaId
        );

        $stockOrigen = $stocks['origen'];
        $stockDestino = $stocks['destino'];

        $stockOrigenAntes = $this->toFloat($stockOrigen->stock_actual);
        $stockDestinoAntes = $this->toFloat($stockDestino->stock_actual);

        if ($stockOrigenAntes < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente en el lote seleccionado para realizar el traspaso.',
            ]);
        }

        $stockOrigenDespues = $this->redondearCantidad($stockOrigenAntes - $cantidad);
        $stockDestinoDespues = $this->redondearCantidad($stockDestinoAntes + $cantidad);

        $stockOrigen->update([
            'stock_actual' => $stockOrigenDespues,
        ]);

        $stockDestino->update([
            'stock_actual' => $stockDestinoDespues,
        ]);

        $this->registrarDetalleMovimientoLote([
            'empresa_id' => $empresaId,
            'movimiento_inventario_id' => $movimiento->id,
            'producto_id' => $producto->id,
            'lote_id' => $lote->id,
            'bodega_origen_id' => $bodegaOrigenId,
            'bodega_destino_id' => $bodegaDestinoId,
            'cantidad' => $cantidad,
            'stock_lote_origen_antes' => $stockOrigenAntes,
            'stock_lote_origen_despues' => $stockOrigenDespues,
            'stock_lote_destino_antes' => $stockDestinoAntes,
            'stock_lote_destino_despues' => $stockDestinoDespues,
            'costo_unitario' => $this->toFloat($movimiento->costo_unitario),
            'costo_total' => $this->toFloat($movimiento->costo_total),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Utilidades públicas para otros services/tests
    |--------------------------------------------------------------------------
    */

    public function obtenerLoteActivoProductoEmpresa(
        int $loteId,
        int $productoId,
        int $empresaId
    ): LoteInventario {
        $lote = LoteInventario::query()
            ->where('id', $loteId)
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->first();

        if (!$lote) {
            throw ValidationException::withMessages([
                'lote_id' => 'El lote no existe, no pertenece a la empresa o no corresponde al producto.',
            ]);
        }

        if (!$lote->activo) {
            throw ValidationException::withMessages([
                'lote_id' => 'El lote seleccionado está inactivo.',
            ]);
        }

        return $lote;
    }

    public function obtenerOCrearStockLoteBloqueado(
        int $productoId,
        int $bodegaId,
        int $loteId,
        int $empresaId
    ): StockLoteInventario {
        $stock = StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('lote_id', $loteId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        try {
            StockLoteInventario::create([
                'empresa_id' => $empresaId,
                'producto_id' => $productoId,
                'bodega_id' => $bodegaId,
                'lote_id' => $loteId,
                'stock_actual' => 0,
            ]);
        } catch (QueryException) {
            // Si otro proceso creó el stock entre SELECT e INSERT,
            // se vuelve a consultar bloqueado dentro de la misma transacción.
        }

        return StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('lote_id', $loteId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers internos
    |--------------------------------------------------------------------------
    */

    private function obtenerOCrearLoteDesdePayload(
        Producto $producto,
        array $payloadLote,
        int $empresaId
    ): LoteInventario {
        $datosNormalizados = $this->normalizarDatosLote($producto, $payloadLote);

        $lote = LoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $producto->id)
            ->where('codigo_lote', $datosNormalizados['codigo_lote'])
            ->lockForUpdate()
            ->first();

        if ($lote) {
            if (!$lote->activo) {
                throw ValidationException::withMessages([
                    'lote.codigo_lote' => 'El lote informado existe, pero está inactivo.',
                ]);
            }

            $this->validarFechaVencimientoRequerida($producto, $lote->fecha_vencimiento?->toDateString());

            return $lote;
        }

        return LoteInventario::create([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'codigo_lote' => $datosNormalizados['codigo_lote'],
            'fecha_fabricacion' => $datosNormalizados['fecha_fabricacion'],
            'fecha_vencimiento' => $datosNormalizados['fecha_vencimiento'],
            'observacion' => $datosNormalizados['observacion'],
            'activo' => true,
        ]);
    }

    private function obtenerProductoEmpresa(int $productoId, int $empresaId): Producto
    {
        $producto = Producto::query()
            ->where('id', $productoId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$producto) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto no existe o no pertenece a la empresa.',
            ]);
        }

        return $producto;
    }

    private function obtenerProductoActivoEmpresa(int $productoId, int $empresaId): Producto
    {
        $producto = $this->obtenerProductoEmpresa($productoId, $empresaId);

        if (!$producto->activo) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto está inactivo.',
            ]);
        }

        return $producto;
    }

    private function obtenerStocksLoteTraspasoBloqueados(
        int $productoId,
        int $bodegaOrigenId,
        int $bodegaDestinoId,
        int $loteId,
        int $empresaId
    ): array {
        collect([$bodegaOrigenId, $bodegaDestinoId])
            ->sort()
            ->values()
            ->each(function ($bodegaId) use ($productoId, $loteId, $empresaId) {
                $this->obtenerOCrearStockLoteBloqueado(
                    productoId: $productoId,
                    bodegaId: (int) $bodegaId,
                    loteId: $loteId,
                    empresaId: $empresaId
                );
            });

        $stockOrigen = StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaOrigenId)
            ->where('lote_id', $loteId)
            ->lockForUpdate()
            ->firstOrFail();

        $stockDestino = StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaDestinoId)
            ->where('lote_id', $loteId)
            ->lockForUpdate()
            ->firstOrFail();

        return [
            'origen' => $stockOrigen,
            'destino' => $stockDestino,
        ];
    }

    private function registrarDetalleMovimientoLote(array $datos): MovimientoLoteInventario
    {
        return MovimientoLoteInventario::create([
            'empresa_id' => $datos['empresa_id'],
            'movimiento_inventario_id' => $datos['movimiento_inventario_id'],
            'producto_id' => $datos['producto_id'],
            'lote_id' => $datos['lote_id'],
            'bodega_origen_id' => $datos['bodega_origen_id'],
            'bodega_destino_id' => $datos['bodega_destino_id'],
            'cantidad' => $this->redondearCantidad((float) $datos['cantidad']),
            'stock_lote_origen_antes' => $datos['stock_lote_origen_antes'],
            'stock_lote_origen_despues' => $datos['stock_lote_origen_despues'],
            'stock_lote_destino_antes' => $datos['stock_lote_destino_antes'],
            'stock_lote_destino_despues' => $datos['stock_lote_destino_despues'],
            'costo_unitario' => $datos['costo_unitario'],
            'costo_total' => $datos['costo_total'],
        ]);
    }

    private function normalizarDatosLote(Producto $producto, array $datos): array
    {
        $codigoLote = strtoupper(trim((string) ($datos['codigo_lote'] ?? '')));

        if ($codigoLote === '') {
            throw ValidationException::withMessages([
                'codigo_lote' => 'El código de lote es obligatorio.',
            ]);
        }

        if (mb_strlen($codigoLote) > 80) {
            throw ValidationException::withMessages([
                'codigo_lote' => 'El código de lote no puede superar los 80 caracteres.',
            ]);
        }

        if (!preg_match('/^[A-Z0-9._-]+$/', $codigoLote)) {
            throw ValidationException::withMessages([
                'codigo_lote' => 'El código de lote solo puede incluir letras, números, punto, guion o guion bajo.',
            ]);
        }

        $fechaFabricacion = $this->normalizarFechaNullable($datos['fecha_fabricacion'] ?? null, 'fecha_fabricacion');
        $fechaVencimiento = $this->normalizarFechaNullable($datos['fecha_vencimiento'] ?? null, 'fecha_vencimiento');

        $this->validarFechaVencimientoRequerida($producto, $fechaVencimiento);
        $this->validarOrdenFechas($fechaFabricacion, $fechaVencimiento);

        return [
            'codigo_lote' => $codigoLote,
            'fecha_fabricacion' => $fechaFabricacion,
            'fecha_vencimiento' => $fechaVencimiento,
            'observacion' => $this->normalizarTextoOpcional($datos['observacion'] ?? null, 2000),
            'activo' => array_key_exists('activo', $datos)
                ? filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN)
                : true,
        ];
    }

    private function validarCodigoLoteUnico(
        int $empresaId,
        int $productoId,
        string $codigoLote,
        ?int $ignorarLoteId = null
    ): void {
        $query = LoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('codigo_lote', $codigoLote);

        if ($ignorarLoteId !== null) {
            $query->where('id', '<>', $ignorarLoteId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'codigo_lote' => 'Ya existe un lote con ese código para el producto seleccionado.',
            ]);
        }
    }

    private function validarFechaVencimientoRequerida(Producto $producto, ?string $fechaVencimiento): void
    {
        if ($producto->requiere_fecha_vencimiento && empty($fechaVencimiento)) {
            throw ValidationException::withMessages([
                'fecha_vencimiento' => 'El producto requiere fecha de vencimiento para sus lotes.',
            ]);
        }
    }

    private function validarOrdenFechas(?string $fechaFabricacion, ?string $fechaVencimiento): void
    {
        if ($fechaFabricacion !== null && $fechaVencimiento !== null && $fechaFabricacion > $fechaVencimiento) {
            throw ValidationException::withMessages([
                'fecha_fabricacion' => 'La fecha de fabricación no puede ser posterior a la fecha de vencimiento.',
            ]);
        }
    }

    private function normalizarFechaNullable(mixed $valor, string $campo): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_string($valor)) {
            throw ValidationException::withMessages([
                $campo => 'La fecha informada no es válida.',
            ]);
        }

        $valor = trim($valor);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            throw ValidationException::withMessages([
                $campo => 'La fecha debe tener formato YYYY-MM-DD.',
            ]);
        }

        return $valor;
    }

    private function normalizarTextoOpcional(mixed $valor, int $max): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        if (mb_strlen($valor) > $max) {
            throw ValidationException::withMessages([
                'observacion' => "El texto no puede superar los {$max} caracteres.",
            ]);
        }

        return $valor;
    }

    private function rechazarPayloadLoteSiProductoNoManejaLotes(array $datos): void
    {
        if (!empty($datos['lote_id']) || !empty($datos['lote'])) {
            throw ValidationException::withMessages([
                'lote_id' => 'El producto no maneja lotes, por lo tanto no debe informar lote.',
            ]);
        }
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 100);
    }

    private function toFloat(mixed $value): float
    {
        return $this->redondearCantidad((float) $value);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}