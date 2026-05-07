<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaConsumoInventario;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioReservaService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioMovimientoService $movimientoService,
        private readonly InventarioDisponibilidadService $disponibilidadService
    ) {
    }

    public function listarReservas(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.reservas.ver');
        $this->marcarReservasExpiradas((int) $usuario->empresa_id);

        return ReservaInventario::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'reservadoPor:id,nombre,email',
                'detalles.producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'detalles.bodega:id,empresa_id,codigo,nombre,estado',
                'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
            ])
            ->when(!empty($filtros['estado']), function (Builder $query) use ($filtros) {
                $query->where('estado', $filtros['estado']);
            })
            ->when(!empty($filtros['referencia']), function (Builder $query) use ($filtros) {
                $query->where('referencia', 'like', '%' . trim((string) $filtros['referencia']) . '%');
            })
            ->when(!empty($filtros['origen_modulo']), function (Builder $query) use ($filtros) {
                $query->where('origen_modulo', $filtros['origen_modulo']);
            })
            ->when(!empty($filtros['origen_id']), function (Builder $query) use ($filtros) {
                $query->where('origen_id', (int) $filtros['origen_id']);
            })
            ->when(!empty($filtros['producto_id']), function (Builder $query) use ($filtros) {
                $query->whereHas('detalles', function (Builder $subQuery) use ($filtros) {
                    $subQuery->where('producto_id', (int) $filtros['producto_id']);
                });
            })
            ->when(!empty($filtros['bodega_id']), function (Builder $query) use ($filtros) {
                $query->whereHas('detalles', function (Builder $subQuery) use ($filtros) {
                    $subQuery->where('bodega_id', (int) $filtros['bodega_id']);
                });
            })
            ->when(!empty($filtros['lote_id']), function (Builder $query) use ($filtros) {
                $query->whereHas('detalles', function (Builder $subQuery) use ($filtros) {
                    $subQuery->where('lote_id', (int) $filtros['lote_id']);
                });
            })
            ->when(!empty($filtros['desde']), function (Builder $query) use ($filtros) {
                $query->whereDate('fecha_reserva', '>=', $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function (Builder $query) use ($filtros) {
                $query->whereDate('fecha_reserva', '<=', $filtros['hasta']);
            })
            ->orderByDesc('fecha_reserva')
            ->orderByDesc('id')
            ->paginate($this->normalizarPerPage($filtros['per_page'] ?? 15));
    }

    public function obtenerReserva(User $usuario, int $reservaId): ReservaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.reservas.ver');
        $this->marcarReservasExpiradas((int) $usuario->empresa_id);

        $reserva = ReservaInventario::where('empresa_id', $usuario->empresa_id)
            ->find($reservaId);

        if (!$reserva) {
            throw new Exception('La reserva solicitada no existe o no pertenece a la empresa.');
        }

        return $this->cargarRelacionesReserva($reserva);
    }

    public function crearReserva(User $usuario, array $datos): ReservaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.reservas.crear');

        return DB::transaction(function () use ($usuario, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $detallesNormalizados = $this->normalizarDetallesReserva($datos['detalles'] ?? [], $empresaId);

            $this->validarDisponibilidadDetallesAgrupados($detallesNormalizados, $empresaId);

            $reserva = ReservaInventario::create([
                'empresa_id' => $empresaId,
                'codigo_reserva' => $datos['codigo_reserva'] ?? $this->generarCodigoReserva($empresaId),
                'estado' => ReservaInventario::ESTADO_ACTIVA,
                'referencia' => $this->normalizarTextoOpcional($datos['referencia'] ?? null, 120),
                'motivo' => $this->normalizarTextoOpcional($datos['motivo'] ?? null, 120),
                'observacion' => $this->normalizarTextoOpcional($datos['observacion'] ?? null, 2000),
                'origen_modulo' => $this->normalizarTextoOpcional($datos['origen_modulo'] ?? null, 80),
                'origen_id' => $datos['origen_id'] ?? null,
                'reservado_por' => $usuario->id,
                'fecha_reserva' => $datos['fecha_reserva'] ?? now(),
                'fecha_expiracion' => $datos['fecha_expiracion'] ?? null,
            ]);

            foreach ($detallesNormalizados as $detalle) {
                ReservaDetalleInventario::create([
                    'empresa_id' => $empresaId,
                    'reserva_id' => $reserva->id,
                    'producto_id' => $detalle['producto']->id,
                    'bodega_id' => $detalle['bodega']->id,
                    'lote_id' => $detalle['lote']?->id,
                    'cantidad_reservada' => $detalle['cantidad'],
                    'cantidad_consumida' => 0,
                    'cantidad_liberada' => 0,
                ]);
            }

            return $this->cargarRelacionesReserva($reserva->refresh());
        });
    }

    public function cancelarReserva(User $usuario, int $reservaId, array $datos = []): ReservaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.reservas.cancelar');

        return DB::transaction(function () use ($usuario, $reservaId, $datos) {
            $reserva = $this->obtenerReservaBloqueada($reservaId, (int) $usuario->empresa_id);
            $this->validarReservaOperable($reserva, 'cancelar');

            $reserva->update([
                'estado' => ReservaInventario::ESTADO_CANCELADA,
                'observacion' => array_key_exists('observacion', $datos)
                    ? $this->normalizarTextoOpcional($datos['observacion'], 2000)
                    : $reserva->observacion,
            ]);

            return $this->cargarRelacionesReserva($reserva->refresh());
        });
    }

    public function liberarReserva(User $usuario, int $reservaId, array $datos): ReservaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.reservas.liberar');

        return DB::transaction(function () use ($usuario, $reservaId, $datos) {
            $reserva = $this->obtenerReservaBloqueada($reservaId, (int) $usuario->empresa_id);
            $this->validarReservaOperable($reserva, 'liberar');

            $liberaciones = $this->normalizarDetallesOperacion(
                reserva: $reserva,
                detallesPayload: $datos['detalles'] ?? [],
                campoCantidad: 'cantidad',
                mensajeVacio: 'Debe informar al menos un detalle para liberar.'
            );

            foreach ($liberaciones as $operacion) {
                /** @var ReservaDetalleInventario $detalle */
                $detalle = $operacion['detalle'];
                $cantidad = $operacion['cantidad'];

                if (!$detalle->puedeLiberar($cantidad)) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'No se puede liberar más que la cantidad pendiente de la reserva.',
                    ]);
                }

                $detalle->update([
                    'cantidad_liberada' => $this->redondearCantidad((float) $detalle->cantidad_liberada + $cantidad),
                ]);
            }

            if (array_key_exists('observacion', $datos)) {
                $reserva->update([
                    'observacion' => $this->normalizarTextoOpcional($datos['observacion'], 2000),
                ]);
            }

            $this->actualizarEstadoReserva($reserva->refresh());

            return $this->cargarRelacionesReserva($reserva->refresh());
        });
    }

    public function consumirReserva(User $usuario, int $reservaId, array $datos): ReservaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.reservas.consumir');

        return DB::transaction(function () use ($usuario, $reservaId, $datos) {
            $reserva = $this->obtenerReservaBloqueada($reservaId, (int) $usuario->empresa_id);
            $this->validarReservaOperable($reserva, 'consumir');

            $consumos = $this->normalizarConsumosSolicitados($reserva, $datos['detalles'] ?? null);

            foreach ($consumos as $operacion) {
                /** @var ReservaDetalleInventario $detalle */
                $detalle = $operacion['detalle'];
                $cantidad = $operacion['cantidad'];

                if (!$detalle->puedeConsumir($cantidad)) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'No se puede consumir más que la cantidad pendiente de la reserva.',
                    ]);
                }

                $movimiento = $this->movimientoService->registrarMovimiento(
                    $this->payloadSalidaDesdeReserva($reserva, $detalle, $cantidad, $datos),
                    (int) $usuario->empresa_id,
                    (int) $usuario->id
                );

                $detalle->update([
                    'cantidad_consumida' => $this->redondearCantidad((float) $detalle->cantidad_consumida + $cantidad),
                ]);

                ReservaConsumoInventario::create([
                    'empresa_id' => (int) $usuario->empresa_id,
                    'reserva_id' => $reserva->id,
                    'reserva_detalle_id' => $detalle->id,
                    'movimiento_inventario_id' => $movimiento->id,
                    'producto_id' => $detalle->producto_id,
                    'bodega_id' => $detalle->bodega_id,
                    'lote_id' => $detalle->lote_id,
                    'cantidad_consumida' => $cantidad,
                    'consumido_por' => $usuario->id,
                    'fecha_consumo' => $datos['fecha_movimiento'] ?? now(),
                ]);
            }

            if (array_key_exists('observacion', $datos)) {
                $reserva->update([
                    'observacion' => $this->normalizarTextoOpcional($datos['observacion'], 2000),
                ]);
            }

            $this->actualizarEstadoReserva($reserva->refresh());

            return $this->cargarRelacionesReserva($reserva->refresh());
        });
    }

    public function marcarReservasExpiradas(int $empresaId): int
    {
        return ReservaInventario::where('empresa_id', $empresaId)
            ->whereIn('estado', ReservaInventario::estadosQueComprometenDisponibilidad())
            ->whereNotNull('fecha_expiracion')
            ->whereDate('fecha_expiracion', '<', now()->toDateString())
            ->update(['estado' => ReservaInventario::ESTADO_EXPIRADA]);
    }

    private function normalizarDetallesReserva(array $detalles, int $empresaId): array
    {
        if (empty($detalles)) {
            throw ValidationException::withMessages([
                'detalles' => 'Debe informar al menos un detalle para la reserva.',
            ]);
        }

        $normalizados = [];

        foreach ($detalles as $indice => $detalle) {
            $producto = $this->obtenerProductoActivoEmpresa((int) ($detalle['producto_id'] ?? 0), $empresaId, "detalles.{$indice}.producto_id");
            $bodega = $this->obtenerBodegaActivaEmpresa((int) ($detalle['bodega_id'] ?? 0), $empresaId, "detalles.{$indice}.bodega_id");
            $cantidad = $this->validarCantidadPositiva($detalle['cantidad'] ?? null, "detalles.{$indice}.cantidad");
            $lote = null;

            if ($producto->maneja_lotes) {
                if (empty($detalle['lote_id'])) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.lote_id" => 'El producto maneja lotes, debe informar lote_id.',
                    ]);
                }

                $lote = $this->obtenerLoteActivoProductoEmpresa(
                    loteId: (int) $detalle['lote_id'],
                    productoId: (int) $producto->id,
                    empresaId: $empresaId,
                    campo: "detalles.{$indice}.lote_id"
                );
            } elseif (!empty($detalle['lote_id'])) {
                throw ValidationException::withMessages([
                    "detalles.{$indice}.lote_id" => 'El producto no maneja lotes, por lo tanto no debe informar lote_id.',
                ]);
            }

            $normalizados[] = [
                'producto' => $producto,
                'bodega' => $bodega,
                'lote' => $lote,
                'cantidad' => $cantidad,
            ];
        }

        return $normalizados;
    }

    private function validarDisponibilidadDetallesAgrupados(array $detallesNormalizados, int $empresaId): void
    {
        $grupos = [];

        foreach ($detallesNormalizados as $detalle) {
            $key = implode(':', [
                $detalle['producto']->id,
                $detalle['bodega']->id,
                $detalle['lote']?->id ?? 'sin_lote',
            ]);

            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'producto' => $detalle['producto'],
                    'bodega' => $detalle['bodega'],
                    'lote' => $detalle['lote'],
                    'cantidad' => 0.0,
                ];
            }

            $grupos[$key]['cantidad'] = $this->redondearCantidad($grupos[$key]['cantidad'] + $detalle['cantidad']);
        }

        foreach ($grupos as $grupo) {
            $this->bloquearStockParaValidacion(
                empresaId: $empresaId,
                productoId: (int) $grupo['producto']->id,
                bodegaId: (int) $grupo['bodega']->id,
                loteId: $grupo['lote']?->id
            );

            $this->disponibilidadService->validarDisponibleParaReserva(
                producto: $grupo['producto'],
                bodega: $grupo['bodega'],
                lote: $grupo['lote'],
                cantidad: (float) $grupo['cantidad'],
                empresaId: $empresaId
            );
        }
    }

    private function bloquearStockParaValidacion(int $empresaId, int $productoId, int $bodegaId, ?int $loteId): void
    {
        StockProducto::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->lockForUpdate()
            ->first();

        if ($loteId !== null) {
            StockLoteInventario::where('empresa_id', $empresaId)
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->where('lote_id', $loteId)
                ->lockForUpdate()
                ->first();
        }
    }

    private function normalizarDetallesOperacion(
        ReservaInventario $reserva,
        array $detallesPayload,
        string $campoCantidad,
        string $mensajeVacio
    ): array {
        if (empty($detallesPayload)) {
            throw ValidationException::withMessages([
                'detalles' => $mensajeVacio,
            ]);
        }

        $agrupados = [];

        foreach ($detallesPayload as $indice => $detallePayload) {
            $detalleId = (int) ($detallePayload['detalle_id'] ?? 0);
            $cantidad = $this->validarCantidadPositiva(
                $detallePayload[$campoCantidad] ?? null,
                "detalles.{$indice}.{$campoCantidad}"
            );

            if ($detalleId <= 0) {
                throw ValidationException::withMessages([
                    "detalles.{$indice}.detalle_id" => 'Debe informar un detalle_id válido.',
                ]);
            }

            if (!isset($agrupados[$detalleId])) {
                $agrupados[$detalleId] = 0.0;
            }

            $agrupados[$detalleId] = $this->redondearCantidad($agrupados[$detalleId] + $cantidad);
        }

        $operaciones = [];

        foreach ($agrupados as $detalleId => $cantidad) {
            $detalle = ReservaDetalleInventario::where('empresa_id', $reserva->empresa_id)
                ->where('reserva_id', $reserva->id)
                ->where('id', $detalleId)
                ->lockForUpdate()
                ->first();

            if (!$detalle) {
                throw ValidationException::withMessages([
                    'detalle_id' => 'El detalle informado no pertenece a la reserva.',
                ]);
            }

            $operaciones[] = [
                'detalle' => $detalle,
                'cantidad' => $cantidad,
            ];
        }

        return $operaciones;
    }

    private function normalizarConsumosSolicitados(ReservaInventario $reserva, ?array $detallesPayload): array
    {
        if ($detallesPayload !== null) {
            return $this->normalizarDetallesOperacion(
                reserva: $reserva,
                detallesPayload: $detallesPayload,
                campoCantidad: 'cantidad',
                mensajeVacio: 'Debe informar al menos un detalle para consumir.'
            );
        }

        $detalles = ReservaDetalleInventario::where('empresa_id', $reserva->empresa_id)
            ->where('reserva_id', $reserva->id)
            ->lockForUpdate()
            ->get();

        $operaciones = [];

        foreach ($detalles as $detalle) {
            $pendiente = $detalle->cantidadPendiente();

            if ($pendiente > 0) {
                $operaciones[] = [
                    'detalle' => $detalle,
                    'cantidad' => $pendiente,
                ];
            }
        }

        if (empty($operaciones)) {
            throw ValidationException::withMessages([
                'reserva' => 'La reserva no tiene cantidades pendientes para consumir.',
            ]);
        }

        return $operaciones;
    }

    private function payloadSalidaDesdeReserva(
        ReservaInventario $reserva,
        ReservaDetalleInventario $detalle,
        float $cantidad,
        array $datos
    ): array {
        $payload = [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => (int) $detalle->producto_id,
            'bodega_origen_id' => (int) $detalle->bodega_id,
            'cantidad' => $cantidad,
            'referencia' => $datos['referencia'] ?? $reserva->referencia ?? $reserva->codigo_reserva,
            'motivo' => $datos['motivo'] ?? 'consumo_reserva',
            'observacion' => $datos['observacion'] ?? 'Salida generada desde reserva ' . $reserva->codigo_reserva,
            'fecha_movimiento' => $datos['fecha_movimiento'] ?? now(),
        ];

        if ($detalle->lote_id !== null) {
            $payload['lote_id'] = (int) $detalle->lote_id;
        }

        return $payload;
    }

    private function actualizarEstadoReserva(ReservaInventario $reserva): void
    {
        $reserva->load('detalles');

        $totalReservado = $this->redondearCantidad((float) $reserva->detalles->sum('cantidad_reservada'));
        $totalConsumido = $this->redondearCantidad((float) $reserva->detalles->sum('cantidad_consumida'));
        $totalLiberado = $this->redondearCantidad((float) $reserva->detalles->sum('cantidad_liberada'));
        $totalPendiente = $this->redondearCantidad($totalReservado - $totalConsumido - $totalLiberado);

        $nuevoEstado = ReservaInventario::ESTADO_ACTIVA;

        if ($totalPendiente <= 0) {
            $nuevoEstado = $totalConsumido > 0
                ? ReservaInventario::ESTADO_CONSUMIDA
                : ReservaInventario::ESTADO_CANCELADA;
        } elseif ($totalConsumido > 0) {
            $nuevoEstado = ReservaInventario::ESTADO_PARCIALMENTE_CONSUMIDA;
        } elseif ($totalLiberado > 0) {
            $nuevoEstado = ReservaInventario::ESTADO_PARCIALMENTE_LIBERADA;
        }

        if ($reserva->estado !== $nuevoEstado) {
            $reserva->update(['estado' => $nuevoEstado]);
        }
    }

    private function validarReservaOperable(ReservaInventario $reserva, string $accion): void
    {
        if ($reserva->fechaExpiracionCumplida()) {
            $reserva->update(['estado' => ReservaInventario::ESTADO_EXPIRADA]);

            throw ValidationException::withMessages([
                'reserva' => 'No se puede ' . $accion . ' una reserva expirada.',
            ]);
        }

        if (!$reserva->comprometeDisponibilidad()) {
            throw ValidationException::withMessages([
                'reserva' => 'No se puede ' . $accion . ' una reserva en estado ' . $reserva->estado . '.',
            ]);
        }
    }

    private function obtenerReservaBloqueada(int $reservaId, int $empresaId): ReservaInventario
    {
        $reserva = ReservaInventario::where('empresa_id', $empresaId)
            ->where('id', $reservaId)
            ->lockForUpdate()
            ->first();

        if (!$reserva) {
            throw new Exception('La reserva solicitada no existe o no pertenece a la empresa.');
        }

        return $reserva;
    }

    private function obtenerProductoActivoEmpresa(int $productoId, int $empresaId, string $campo): Producto
    {
        $producto = Producto::where('empresa_id', $empresaId)->find($productoId);

        if (!$producto) {
            throw ValidationException::withMessages([
                $campo => 'El producto informado no existe o no pertenece a la empresa.',
            ]);
        }

        if (!$producto->estaActivo()) {
            throw ValidationException::withMessages([
                $campo => 'El producto informado no está activo.',
            ]);
        }

        return $producto;
    }

    private function obtenerBodegaActivaEmpresa(int $bodegaId, int $empresaId, string $campo): Bodega
    {
        $bodega = Bodega::where('empresa_id', $empresaId)->find($bodegaId);

        if (!$bodega) {
            throw ValidationException::withMessages([
                $campo => 'La bodega informada no existe o no pertenece a la empresa.',
            ]);
        }

        if (!$bodega->estaActiva()) {
            throw ValidationException::withMessages([
                $campo => 'La bodega informada no está activa.',
            ]);
        }

        return $bodega;
    }

    private function obtenerLoteActivoProductoEmpresa(int $loteId, int $productoId, int $empresaId, string $campo): LoteInventario
    {
        $lote = LoteInventario::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->find($loteId);

        if (!$lote) {
            throw ValidationException::withMessages([
                $campo => 'El lote informado no existe, no pertenece al producto o no pertenece a la empresa.',
            ]);
        }

        if (!$lote->activo) {
            throw ValidationException::withMessages([
                $campo => 'El lote informado no está activo.',
            ]);
        }

        return $lote;
    }

    private function validarCantidadPositiva(mixed $cantidad, string $campo): float
    {
        if (!is_numeric($cantidad)) {
            throw ValidationException::withMessages([
                $campo => 'La cantidad debe ser numérica.',
            ]);
        }

        $cantidad = $this->redondearCantidad((float) $cantidad);

        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                $campo => 'La cantidad debe ser mayor a cero.',
            ]);
        }

        return $cantidad;
    }

    private function generarCodigoReserva(int $empresaId): string
    {
        do {
            $codigo = 'RES-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (
            ReservaInventario::where('empresa_id', $empresaId)
                ->where('codigo_reserva', $codigo)
                ->exists()
        );

        return $codigo;
    }

    private function cargarRelacionesReserva(ReservaInventario $reserva): ReservaInventario
    {
        return $reserva->load([
            'reservadoPor:id,nombre,email',
            'detalles.producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
            'detalles.bodega:id,empresa_id,codigo,nombre,estado',
            'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
            'consumos.movimiento:id,empresa_id,producto_id,tipo,bodega_origen_id,bodega_destino_id,cantidad,referencia,motivo,fecha_movimiento',
        ]);
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

        return mb_substr($valor, 0, $max);
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 100);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}