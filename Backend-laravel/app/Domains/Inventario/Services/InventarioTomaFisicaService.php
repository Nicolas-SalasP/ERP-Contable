<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Events\TomaFisicaConfirmada;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\TomaFisicaDetalleInventario;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioTomaFisicaService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioMovimientoService $movimientoService,
        private readonly InventarioEventoIntegracionService $eventosIntegracion
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Consultas API
    |--------------------------------------------------------------------------
    */

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.tomas_fisicas.ver');

        $empresaId = (int) $usuario->empresa_id;
        $perPage = $this->normalizarPerPage($filtros['per_page'] ?? 15);

        return TomaFisicaInventario::query()
            ->with([
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->withCount([
                'detalles',
                'detalles as detalles_contados_count' => function ($query) {
                    $query->whereNotNull('stock_contado');
                },
                'detalles as detalles_con_diferencia_count' => function ($query) {
                    $query->where('diferencia', '!=', 0);
                },
                'detalles as detalles_ajustados_count' => function ($query) {
                    $query->whereNotNull('movimiento_ajuste_id');
                },
            ])
            ->where('empresa_id', $empresaId)
            ->when(!empty($filtros['estado']), function ($query) use ($filtros) {
                $query->where('estado', (string) $filtros['estado']);
            })
            ->when(!empty($filtros['tipo']), function ($query) use ($filtros) {
                $query->where('tipo', (string) $filtros['tipo']);
            })
            ->when(!empty($filtros['bodega_id']), function ($query) use ($filtros) {
                $query->where('bodega_id', (int) $filtros['bodega_id']);
            })
            ->when(!empty($filtros['referencia']), function ($query) use ($filtros) {
                $query->where('referencia', 'like', '%' . trim((string) $filtros['referencia']) . '%');
            })
            ->when(!empty($filtros['desde']), function ($query) use ($filtros) {
                $query->whereDate('created_at', '>=', (string) $filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($query) use ($filtros) {
                $query->whereDate('created_at', '<=', (string) $filtros['hasta']);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function obtener(User $usuario, int $tomaFisicaId): TomaFisicaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.tomas_fisicas.ver');

        return $this->obtenerTomaFisicaEmpresa(
            tomaFisicaId: $tomaFisicaId,
            empresaId: (int) $usuario->empresa_id,
            conRelaciones: true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Escritura API
    |--------------------------------------------------------------------------
    */

    public function crear(User $usuario, array $datos): TomaFisicaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.tomas_fisicas.crear');

        $empresaId = (int) $usuario->empresa_id;
        $tipo = $this->normalizarTipo($datos['tipo'] ?? null);
        $bodegaId = $this->resolverBodegaCabecera($tipo, $datos['bodega_id'] ?? null, $empresaId);

        return DB::transaction(function () use ($usuario, $datos, $empresaId, $tipo, $bodegaId) {
            $toma = TomaFisicaInventario::create([
                'empresa_id' => $empresaId,
                'codigo_toma' => $this->generarCodigoToma($empresaId),
                'estado' => TomaFisicaInventario::ESTADO_BORRADOR,
                'tipo' => $tipo,
                'bodega_id' => $bodegaId,
                'referencia' => $this->normalizarTextoNullable($datos['referencia'] ?? null),
                'motivo' => $this->normalizarTextoNullable($datos['motivo'] ?? null),
                'observacion' => $this->normalizarTextoNullable($datos['observacion'] ?? null),
                'origen_modulo' => $this->normalizarTextoNullable($datos['origen_modulo'] ?? null),
                'origen_id' => !empty($datos['origen_id']) ? (int) $datos['origen_id'] : null,
                'creado_por' => $usuario->id,
                'fecha_inicio' => null,
                'fecha_cierre' => null,
                'fecha_ajuste' => null,
                'fecha_cancelacion' => null,
            ]);

            $this->prepararDetalles($toma);

            if (!$toma->detalles()->exists()) {
                throw ValidationException::withMessages([
                    'toma_fisica' => 'No existen productos con stock físico preparado para esta toma física.',
                ]);
            }

            return $this->cargarRelacionesToma($toma->fresh());
        });
    }

    public function iniciar(User $usuario, int $tomaFisicaId): TomaFisicaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.tomas_fisicas.contar');

        return DB::transaction(function () use ($usuario, $tomaFisicaId) {
            $toma = $this->obtenerTomaFisicaEmpresaBloqueada(
                tomaFisicaId: $tomaFisicaId,
                empresaId: (int) $usuario->empresa_id
            );

            if (!$toma->puedeIniciarse()) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo una toma física en BORRADOR puede iniciarse.',
                ]);
            }

            if (!$toma->detalles()->exists()) {
                throw ValidationException::withMessages([
                    'detalles' => 'La toma física no tiene detalles preparados para iniciar conteo.',
                ]);
            }

            $toma->update([
                'estado' => TomaFisicaInventario::ESTADO_EN_CONTEO,
                'fecha_inicio' => now(),
            ]);

            return $this->cargarRelacionesToma($toma->fresh());
        });
    }

    public function registrarConteos(User $usuario, int $tomaFisicaId, array $datos): TomaFisicaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.tomas_fisicas.contar');

        $detallesPayload = $datos['detalles'] ?? null;

        if (!is_array($detallesPayload) || count($detallesPayload) === 0) {
            throw ValidationException::withMessages([
                'detalles' => 'Debe informar al menos un detalle de conteo.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $tomaFisicaId, $detallesPayload) {
            $empresaId = (int) $usuario->empresa_id;

            $toma = $this->obtenerTomaFisicaEmpresaBloqueada(
                tomaFisicaId: $tomaFisicaId,
                empresaId: $empresaId
            );

            if (!$toma->puedeContarse()) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo una toma física EN_CONTEO permite registrar conteos.',
                ]);
            }

            foreach ($detallesPayload as $index => $detallePayload) {
                $detalleId = $detallePayload['detalle_id'] ?? null;

                if (empty($detalleId)) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.detalle_id" => 'Debe informar el detalle_id.',
                    ]);
                }

                $stockContado = $this->normalizarCantidadConteo(
                    valor: $detallePayload['stock_contado'] ?? null,
                    campo: "detalles.{$index}.stock_contado"
                );

                $detalle = TomaFisicaDetalleInventario::query()
                    ->where('empresa_id', $empresaId)
                    ->where('toma_fisica_id', $toma->id)
                    ->where('id', (int) $detalleId)
                    ->lockForUpdate()
                    ->first();

                if (!$detalle) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.detalle_id" => 'El detalle informado no existe o no pertenece a la toma física.',
                    ]);
                }

                $detalle->update([
                    'stock_contado' => $stockContado,
                    'diferencia' => $detalle->calcularDiferencia($stockContado),
                    'observacion' => $this->normalizarTextoNullable($detallePayload['observacion'] ?? $detalle->observacion),
                    'contado_por' => $usuario->id,
                    'fecha_conteo' => now(),
                ]);
            }

            return $this->cargarRelacionesToma($toma->fresh());
        });
    }

    public function cerrar(User $usuario, int $tomaFisicaId, array $datos = []): TomaFisicaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.tomas_fisicas.cerrar');

        return DB::transaction(function () use ($usuario, $tomaFisicaId, $datos) {
            $toma = $this->obtenerTomaFisicaEmpresaBloqueada(
                tomaFisicaId: $tomaFisicaId,
                empresaId: (int) $usuario->empresa_id
            );

            if (!$toma->puedeCerrarse()) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo una toma física EN_CONTEO puede cerrarse.',
                ]);
            }

            if ($toma->tieneDetallesPendientesDeConteo()) {
                throw ValidationException::withMessages([
                    'detalles' => 'No se puede cerrar la toma física porque existen detalles pendientes de conteo.',
                ]);
            }

            $toma->update([
                'estado' => TomaFisicaInventario::ESTADO_CERRADA,
                'cerrado_por' => $usuario->id,
                'fecha_cierre' => now(),
                'observacion' => $this->normalizarTextoNullable($datos['observacion'] ?? $toma->observacion),
            ]);

            return $this->cargarRelacionesToma($toma->fresh());
        });
    }

    public function ajustar(User $usuario, int $tomaFisicaId, array $datos = []): TomaFisicaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.tomas_fisicas.ajustar');

        return DB::transaction(function () use ($usuario, $tomaFisicaId, $datos) {
            $empresaId = (int) $usuario->empresa_id;

            $toma = $this->obtenerTomaFisicaEmpresaBloqueada(
                tomaFisicaId: $tomaFisicaId,
                empresaId: $empresaId
            );

            if (!$toma->puedeAjustarse()) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo una toma física CERRADA puede ajustarse.',
                ]);
            }

            if ($toma->tieneDetallesPendientesDeConteo()) {
                throw ValidationException::withMessages([
                    'detalles' => 'No se puede ajustar la toma física porque existen detalles pendientes de conteo.',
                ]);
            }

            if ($toma->tieneDetallesAjustados()) {
                throw ValidationException::withMessages([
                    'estado' => 'La toma física ya tiene movimientos de ajuste asociados.',
                ]);
            }

            $detalles = TomaFisicaDetalleInventario::query()
                ->with([
                    'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento,costo_promedio',
                    'bodega:id,empresa_id,codigo,nombre,estado',
                    'lote:id,empresa_id,producto_id,codigo_lote,activo',
                ])
                ->where('empresa_id', $empresaId)
                ->where('toma_fisica_id', $toma->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $movimientosGenerados = 0;

            foreach ($detalles as $detalle) {
                if (!$detalle->requiereMovimientoAjuste()) {
                    continue;
                }

                $movimiento = $this->aplicarAjusteDetalle(
                    detalle: $detalle,
                    toma: $toma,
                    datos: $datos,
                    usuario: $usuario
                );

                $detalle->update([
                    'movimiento_ajuste_id' => $movimiento->id,
                ]);

                $movimientosGenerados++;
            }

            $toma->update([
                'estado' => TomaFisicaInventario::ESTADO_AJUSTADA,
                'ajustado_por' => $usuario->id,
                'fecha_ajuste' => now(),
                'observacion' => $this->normalizarTextoNullable($datos['observacion'] ?? $toma->observacion),
            ]);

            DB::afterCommit(function () use ($empresaId, $toma, $usuario, $movimientosGenerados) {
                event(new TomaFisicaConfirmada(
                    empresaId: $empresaId,
                    tomaFisicaId: (int) $toma->id,
                    usuarioId: (int) $usuario->id,
                    movimientosGenerados: $movimientosGenerados
                ));
            });

            $toma = $this->cargarRelacionesToma($toma->fresh());
            $this->eventosIntegracion->publicarDesdeOperacion($usuario, InventarioEventoIntegracion::EVENTO_TOMA_FISICA_AJUSTADA, [
                'empresa_id' => $empresaId,
                'entidad_tipo' => TomaFisicaInventario::class,
                'entidad_id' => (int) $toma->id,
                'prioridad' => $movimientosGenerados > 0
                    ? InventarioEventoIntegracion::PRIORIDAD_ALTA
                    : InventarioEventoIntegracion::PRIORIDAD_NORMAL,
                'payload_json' => [
                    'codigo_toma' => $toma->codigo_toma,
                    'estado' => $toma->estado,
                    'tipo' => $toma->tipo,
                    'bodega_id' => $toma->bodega_id,
                    'movimientos_generados' => $movimientosGenerados,
                ],
                'metadata_json' => [
                    'referencia' => $toma->referencia,
                    'observacion' => $toma->observacion,
                ],
                'origen_modulo' => 'inventario_toma_fisica',
                'origen_id' => (int) $toma->id,
            ], true);

            return $toma;
        });
    }

    public function cancelar(User $usuario, int $tomaFisicaId, array $datos = []): TomaFisicaInventario
    {
        $this->permisos->exigir($usuario, 'inventario.tomas_fisicas.cancelar');

        return DB::transaction(function () use ($usuario, $tomaFisicaId, $datos) {
            $toma = $this->obtenerTomaFisicaEmpresaBloqueada(
                tomaFisicaId: $tomaFisicaId,
                empresaId: (int) $usuario->empresa_id
            );

            if (!$toma->puedeCancelarse()) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo una toma física en BORRADOR o EN_CONTEO puede cancelarse.',
                ]);
            }

            $toma->update([
                'estado' => TomaFisicaInventario::ESTADO_CANCELADA,
                'cancelado_por' => $usuario->id,
                'fecha_cancelacion' => now(),
                'observacion' => $this->normalizarTextoNullable($datos['observacion'] ?? $toma->observacion),
            ]);

            return $this->cargarRelacionesToma($toma->fresh());
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Preparación de snapshot
    |--------------------------------------------------------------------------
    */

    private function prepararDetalles(TomaFisicaInventario $toma): void
    {
        if ($toma->esGeneral()) {
            $this->prepararDetallesGeneral($toma);
            return;
        }

        if ($toma->esPorBodega() || $toma->esCiclica()) {
            $this->prepararDetallesPorBodega($toma);
            return;
        }

        throw ValidationException::withMessages([
            'tipo' => 'El tipo de toma física no es válido.',
        ]);
    }

    private function prepararDetallesGeneral(TomaFisicaInventario $toma): void
    {
        $this->prepararDetallesSinLote($toma, null);
        $this->prepararDetallesConLote($toma, null);
    }

    private function prepararDetallesPorBodega(TomaFisicaInventario $toma): void
    {
        if (empty($toma->bodega_id)) {
            throw ValidationException::withMessages([
                'bodega_id' => 'La toma física por bodega o cíclica requiere bodega_id.',
            ]);
        }

        $this->prepararDetallesSinLote($toma, (int) $toma->bodega_id);
        $this->prepararDetallesConLote($toma, (int) $toma->bodega_id);
    }

    private function prepararDetallesSinLote(TomaFisicaInventario $toma, ?int $bodegaId): void
    {
        StockProducto::query()
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->where('empresa_id', $toma->empresa_id)
            ->when($bodegaId !== null, function ($query) use ($bodegaId) {
                $query->where('bodega_id', $bodegaId);
            })
            ->whereHas('producto', function ($query) {
                $query
                    ->where('activo', true)
                    ->where('maneja_lotes', false);
            })
            ->whereHas('bodega', function ($query) {
                $query->where('estado', 'ACTIVA');
            })
            ->orderBy('producto_id')
            ->orderBy('bodega_id')
            ->chunkById(100, function ($stocks) use ($toma) {
                foreach ($stocks as $stock) {
                    $this->crearDetalleSnapshot(
                        toma: $toma,
                        productoId: (int) $stock->producto_id,
                        bodegaId: (int) $stock->bodega_id,
                        loteId: null,
                        stockSistema: (float) $stock->stock_actual
                    );
                }
            });
    }

    private function prepararDetallesConLote(TomaFisicaInventario $toma, ?int $bodegaId): void
    {
        StockLoteInventario::query()
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,activo',
            ])
            ->where('empresa_id', $toma->empresa_id)
            ->when($bodegaId !== null, function ($query) use ($bodegaId) {
                $query->where('bodega_id', $bodegaId);
            })
            ->whereHas('producto', function ($query) {
                $query
                    ->where('activo', true)
                    ->where('maneja_lotes', true);
            })
            ->whereHas('bodega', function ($query) {
                $query->where('estado', 'ACTIVA');
            })
            ->whereHas('lote', function ($query) {
                $query->where('activo', true);
            })
            ->orderBy('producto_id')
            ->orderBy('bodega_id')
            ->orderBy('lote_id')
            ->chunkById(100, function ($stocks) use ($toma) {
                foreach ($stocks as $stock) {
                    $this->crearDetalleSnapshot(
                        toma: $toma,
                        productoId: (int) $stock->producto_id,
                        bodegaId: (int) $stock->bodega_id,
                        loteId: (int) $stock->lote_id,
                        stockSistema: (float) $stock->stock_actual
                    );
                }
            });
    }

    private function crearDetalleSnapshot(
        TomaFisicaInventario $toma,
        int $productoId,
        int $bodegaId,
        ?int $loteId,
        float $stockSistema
    ): void {
        $existe = TomaFisicaDetalleInventario::query()
            ->where('toma_fisica_id', $toma->id)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->when($loteId === null, function ($query) {
                $query->whereNull('lote_id');
            }, function ($query) use ($loteId) {
                $query->where('lote_id', $loteId);
            })
            ->exists();

        if ($existe) {
            return;
        }

        TomaFisicaDetalleInventario::create([
            'empresa_id' => $toma->empresa_id,
            'toma_fisica_id' => $toma->id,
            'producto_id' => $productoId,
            'bodega_id' => $bodegaId,
            'lote_id' => $loteId,
            'stock_sistema' => $this->redondearCantidad($stockSistema),
            'stock_contado' => null,
            'diferencia' => 0,
            'movimiento_ajuste_id' => null,
            'observacion' => null,
            'contado_por' => null,
            'fecha_conteo' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Aplicación de ajustes
    |--------------------------------------------------------------------------
    */

    private function aplicarAjusteDetalle(
        TomaFisicaDetalleInventario $detalle,
        TomaFisicaInventario $toma,
        array $datos,
        User $usuario
    ): MovimientoInventario {
        $producto = $this->obtenerProductoActivoEmpresa(
            productoId: (int) $detalle->producto_id,
            empresaId: (int) $usuario->empresa_id
        );

        $this->validarBodegaActivaEmpresa(
            bodegaId: (int) $detalle->bodega_id,
            empresaId: (int) $usuario->empresa_id
        );

        if ($detalle->tieneDiferenciaPositiva()) {
            return $this->movimientoService->registrarMovimiento(
                data: $this->payloadAjustePositivo($detalle, $toma, $datos, $producto),
                empresaId: (int) $usuario->empresa_id,
                userId: (int) $usuario->id
            );
        }

        if ($detalle->tieneDiferenciaNegativa()) {
            return $this->movimientoService->registrarMovimiento(
                data: $this->payloadAjusteNegativo($detalle, $toma, $datos),
                empresaId: (int) $usuario->empresa_id,
                userId: (int) $usuario->id
            );
        }

        throw ValidationException::withMessages([
            'diferencia' => 'El detalle no requiere movimiento de ajuste.',
        ]);
    }

    private function payloadAjustePositivo(
        TomaFisicaDetalleInventario $detalle,
        TomaFisicaInventario $toma,
        array $datos,
        Producto $producto
    ): array {
        $payload = [
            'tipo' => MovimientoInventario::TIPO_AJUSTE_POSITIVO,
            'producto_id' => (int) $detalle->producto_id,
            'bodega_destino_id' => (int) $detalle->bodega_id,
            'cantidad' => $detalle->cantidadAbsolutaDiferencia(),
            'referencia' => $this->referenciaAjuste($toma, $datos),
            'motivo' => $this->motivoAjuste($datos),
            'observacion' => $this->observacionAjuste($detalle, $toma, $datos),
            'fecha_movimiento' => now(),
            'costo_unitario' => $this->resolverCostoUnitarioAjustePositivo($detalle, $producto, $datos),
        ];

        if ($detalle->lote_id !== null) {
            $payload['lote_id'] = (int) $detalle->lote_id;
        }

        return $payload;
    }

    private function payloadAjusteNegativo(
        TomaFisicaDetalleInventario $detalle,
        TomaFisicaInventario $toma,
        array $datos
    ): array {
        $payload = [
            'tipo' => MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            'producto_id' => (int) $detalle->producto_id,
            'bodega_origen_id' => (int) $detalle->bodega_id,
            'cantidad' => $detalle->cantidadAbsolutaDiferencia(),
            'referencia' => $this->referenciaAjuste($toma, $datos),
            'motivo' => $this->motivoAjuste($datos),
            'observacion' => $this->observacionAjuste($detalle, $toma, $datos),
            'fecha_movimiento' => now(),
        ];

        if ($detalle->lote_id !== null) {
            $payload['lote_id'] = (int) $detalle->lote_id;
        }

        return $payload;
    }

    private function resolverCostoUnitarioAjustePositivo(
        TomaFisicaDetalleInventario $detalle,
        Producto $producto,
        array $datos
    ): float {
        $costoDesdeDetalle = $this->resolverCostoUnitarioDesdePayloadPorDetalle($detalle, $datos);

        if ($costoDesdeDetalle !== null) {
            return $costoDesdeDetalle;
        }

        if (array_key_exists('costo_unitario', $datos) && $datos['costo_unitario'] !== null && $datos['costo_unitario'] !== '') {
            return $this->normalizarCostoUnitarioAjuste($datos['costo_unitario']);
        }

        $costoPromedio = $this->redondearCantidad((float) $producto->costo_promedio);

        if ($costoPromedio <= 0) {
            throw ValidationException::withMessages([
                'costo_unitario' => 'El ajuste positivo requiere costo_unitario porque el producto no tiene costo promedio válido.',
            ]);
        }

        return $costoPromedio;
    }

    private function resolverCostoUnitarioDesdePayloadPorDetalle(
        TomaFisicaDetalleInventario $detalle,
        array $datos
    ): ?float {
        if (empty($datos['costos_unitarios']) || !is_array($datos['costos_unitarios'])) {
            return null;
        }

        $costos = $datos['costos_unitarios'];

        if (array_key_exists($detalle->id, $costos)) {
            return $this->normalizarCostoUnitarioAjuste($costos[$detalle->id]);
        }

        $detalleIdTexto = (string) $detalle->id;

        if (array_key_exists($detalleIdTexto, $costos)) {
            return $this->normalizarCostoUnitarioAjuste($costos[$detalleIdTexto]);
        }

        return null;
    }

    private function referenciaAjuste(TomaFisicaInventario $toma, array $datos): string
    {
        $referencia = $this->normalizarTextoNullable($datos['referencia'] ?? null);

        if ($referencia !== null) {
            return $referencia;
        }

        return 'AJ-' . $toma->codigo_toma;
    }

    private function motivoAjuste(array $datos): string
    {
        $motivo = $this->normalizarTextoNullable($datos['motivo'] ?? null);

        return $motivo ?: MovimientoInventario::MOTIVO_CORRECCION_STOCK;
    }

    private function observacionAjuste(
        TomaFisicaDetalleInventario $detalle,
        TomaFisicaInventario $toma,
        array $datos
    ): string {
        $observacionBase = $this->normalizarTextoNullable($datos['observacion'] ?? null)
            ?: 'Ajuste generado desde toma física.';

        return trim(sprintf(
            '%s Toma física: %s. Detalle: %s. Stock sistema: %s. Stock contado: %s. Diferencia: %s.',
            $observacionBase,
            $toma->codigo_toma,
            $detalle->id,
            $this->formatearDecimal($detalle->stock_sistema),
            $this->formatearDecimal($detalle->stock_contado),
            $this->formatearDecimal($detalle->diferencia)
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Obtención y validación de entidades
    |--------------------------------------------------------------------------
    */

    private function obtenerTomaFisicaEmpresa(
        int $tomaFisicaId,
        int $empresaId,
        bool $conRelaciones = false
    ): TomaFisicaInventario {
        $query = TomaFisicaInventario::query()
            ->where('empresa_id', $empresaId);

        if ($conRelaciones) {
            $query->with($this->relacionesToma());
        }

        $toma = $query->find($tomaFisicaId);

        if (!$toma) {
            throw ValidationException::withMessages([
                'toma_fisica_id' => 'La toma física no existe o no pertenece a la empresa.',
            ]);
        }

        return $toma;
    }

    private function obtenerTomaFisicaEmpresaBloqueada(
        int $tomaFisicaId,
        int $empresaId
    ): TomaFisicaInventario {
        $toma = TomaFisicaInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $tomaFisicaId)
            ->lockForUpdate()
            ->first();

        if (!$toma) {
            throw ValidationException::withMessages([
                'toma_fisica_id' => 'La toma física no existe o no pertenece a la empresa.',
            ]);
        }

        return $toma;
    }

    private function obtenerProductoActivoEmpresa(int $productoId, int $empresaId): Producto
    {
        $producto = Producto::query()
            ->where('empresa_id', $empresaId)
            ->find($productoId);

        if (!$producto) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto no existe o no pertenece a la empresa.',
            ]);
        }

        if (!$producto->activo) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto está inactivo.',
            ]);
        }

        return $producto;
    }

    private function validarBodegaActivaEmpresa(int $bodegaId, int $empresaId): Bodega
    {
        $bodega = Bodega::query()
            ->where('empresa_id', $empresaId)
            ->find($bodegaId);

        if (!$bodega) {
            throw ValidationException::withMessages([
                'bodega_id' => 'La bodega no existe o no pertenece a la empresa.',
            ]);
        }

        if ($bodega->estado !== 'ACTIVA') {
            throw ValidationException::withMessages([
                'bodega_id' => 'La bodega está inactiva.',
            ]);
        }

        return $bodega;
    }

    private function resolverBodegaCabecera(string $tipo, mixed $bodegaId, int $empresaId): ?int
    {
        if ($tipo === TomaFisicaInventario::TIPO_GENERAL) {
            if (!empty($bodegaId)) {
                throw ValidationException::withMessages([
                    'bodega_id' => 'Una toma física GENERAL no debe informar bodega_id.',
                ]);
            }

            return null;
        }

        if (empty($bodegaId)) {
            throw ValidationException::withMessages([
                'bodega_id' => 'La toma física por BODEGA o CICLICA requiere bodega_id.',
            ]);
        }

        $bodega = $this->validarBodegaActivaEmpresa((int) $bodegaId, $empresaId);

        return (int) $bodega->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Normalización
    |--------------------------------------------------------------------------
    */

    private function normalizarTipo(mixed $tipo): string
    {
        if (!is_string($tipo) || trim($tipo) === '') {
            throw ValidationException::withMessages([
                'tipo' => 'Debe informar el tipo de toma física.',
            ]);
        }

        $tipo = strtoupper(trim($tipo));

        if (!in_array($tipo, TomaFisicaInventario::tiposPermitidos(), true)) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de toma física no es válido.',
            ]);
        }

        return $tipo;
    }

    private function normalizarCantidadConteo(mixed $valor, string $campo): float
    {
        if ($valor === null || $valor === '') {
            throw ValidationException::withMessages([
                $campo => 'Debe informar el stock contado.',
            ]);
        }

        if (!is_numeric($valor)) {
            throw ValidationException::withMessages([
                $campo => 'El stock contado debe ser numérico.',
            ]);
        }

        $valor = $this->redondearCantidad((float) $valor);

        if ($valor < 0) {
            throw ValidationException::withMessages([
                $campo => 'El stock contado no puede ser negativo.',
            ]);
        }

        return $valor;
    }

    private function normalizarCostoUnitarioAjuste(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            throw ValidationException::withMessages([
                'costo_unitario' => 'Debe informar costo_unitario.',
            ]);
        }

        if (!is_numeric($valor)) {
            throw ValidationException::withMessages([
                'costo_unitario' => 'El costo unitario debe ser numérico.',
            ]);
        }

        $valor = $this->redondearCantidad((float) $valor);

        if ($valor <= 0) {
            throw ValidationException::withMessages([
                'costo_unitario' => 'El costo unitario debe ser mayor a cero.',
            ]);
        }

        return $valor;
    }

    private function normalizarTextoNullable(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 100);
    }

    private function redondearCantidad(float $valor): float
    {
        return round($valor, 4);
    }

    private function formatearDecimal(mixed $valor): string
    {
        return number_format((float) $valor, 4, '.', '');
    }

    /*
    |--------------------------------------------------------------------------
    | Utilidades de respuesta
    |--------------------------------------------------------------------------
    */

    private function cargarRelacionesToma(TomaFisicaInventario $toma): TomaFisicaInventario
    {
        return $toma->load($this->relacionesToma());
    }

    private function relacionesToma(): array
    {
        return [
            'bodega:id,empresa_id,codigo,nombre,estado',
            'detalles' => function ($query) {
                $query
                    ->with([
                        'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento,costo_promedio',
                        'bodega:id,empresa_id,codigo,nombre,estado',
                        'lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
                        'movimientoAjuste:id,empresa_id,producto_id,tipo,bodega_origen_id,bodega_destino_id,cantidad,costo_unitario,costo_total,referencia,motivo,fecha_movimiento',
                    ])
                    ->orderBy('producto_id')
                    ->orderBy('bodega_id')
                    ->orderBy('lote_id')
                    ->orderBy('id');
            },
        ];
    }

    private function generarCodigoToma(int $empresaId): string
    {
        for ($intento = 1; $intento <= 5; $intento++) {
            $codigo = 'TF-' . now()->format('YmdHis') . '-' . random_int(100000, 999999);

            $existe = TomaFisicaInventario::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo_toma', $codigo)
                ->exists();

            if (!$existe) {
                return $codigo;
            }
        }

        throw ValidationException::withMessages([
            'codigo_toma' => 'No fue posible generar un código único para la toma física.',
        ]);
    }
}