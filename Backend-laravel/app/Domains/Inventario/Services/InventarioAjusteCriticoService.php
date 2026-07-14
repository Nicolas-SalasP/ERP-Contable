<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Exceptions\InventarioException;
use App\Domains\Inventario\Models\AjusteCriticoInventario;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioAuditoriaEvento;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use App\Domains\Inventario\Models\InventarioValorizacionCapa;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\TipoAjusteCritico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioAjusteCriticoService
{
    public function __construct(
        private readonly InventarioMovimientoService $movimientoService,
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioAuditoriaService $auditoria,
        private readonly InventarioEventoIntegracionService $eventosIntegracion
    ) {}

    public function listarTiposAjusteCritico(User $usuario): Collection
    {
        $this->permisos->exigir($usuario, 'inventario.ajustes_criticos.ver');

        return TipoAjusteCritico::query()
            ->activo()
            ->ordenado()
            ->get();
    }

    public function listarAjustesCriticos(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.ajustes_criticos.ver');

        $empresaId = (int) $usuario->empresa_activa_id;
        $perPage = $this->normalizarPerPage($filtros['per_page'] ?? 15);

        $fechaDesde = $filtros['fecha_desde'] ?? $filtros['desde'] ?? null;
        $fechaHasta = $filtros['fecha_hasta'] ?? $filtros['hasta'] ?? null;

        return AjusteCriticoInventario::query()
            ->with([
                'tipo:id,codigo,nombre,descripcion,tipo_movimiento,requiere_stock,activo',
                'producto:id,empresa_id,sku,nombre,activo,permite_merma',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'movimiento:id,empresa_id,producto_id,tipo,bodega_origen_id,bodega_destino_id,cantidad,costo_unitario,costo_total,referencia,motivo,observacion,fecha_movimiento',
                'registradoPor:id,nombre,email',
            ])
            ->empresa($empresaId)
            ->when(! empty($filtros['producto_id']), function ($query) use ($filtros) {
                $query->producto((int) $filtros['producto_id']);
            })
            ->when(! empty($filtros['bodega_id']), function ($query) use ($filtros) {
                $query->bodega((int) $filtros['bodega_id']);
            })
            ->when(! empty($filtros['tipo_ajuste_critico_id']), function ($query) use ($filtros) {
                $query->tipoAjusteCritico((int) $filtros['tipo_ajuste_critico_id']);
            })
            ->when(! empty($fechaDesde), function ($query) use ($fechaDesde) {
                $query->desde((string) $fechaDesde);
            })
            ->when(! empty($fechaHasta), function ($query) use ($fechaHasta) {
                $query->hasta((string) $fechaHasta);
            })
            ->masRecientes()
            ->paginate($perPage);
    }

    public function obtenerAjusteCritico(User $usuario, int $ajusteCriticoId): AjusteCriticoInventario
    {
        $this->permisos->exigir($usuario, 'inventario.ajustes_criticos.ver');

        $ajuste = AjusteCriticoInventario::query()
            ->with([
                'tipo:id,codigo,nombre,descripcion,tipo_movimiento,requiere_stock,activo',
                'producto:id,empresa_id,sku,nombre,activo,permite_merma',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'movimiento:id,empresa_id,producto_id,tipo,bodega_origen_id,bodega_destino_id,cantidad,stock_origen_antes,stock_origen_despues,stock_destino_antes,stock_destino_despues,costo_unitario,costo_total,referencia,motivo,observacion,created_by,fecha_movimiento',
                'registradoPor:id,nombre,email',
            ])
            ->empresa((int) $usuario->empresa_activa_id)
            ->find($ajusteCriticoId);

        if (! $ajuste) {
            throw InventarioException::noEncontrado('El ajuste crítico solicitado no existe o no pertenece a la empresa.');
        }

        return $ajuste;
    }

    public function registrarAjusteCritico(User $usuario, array $datos): AjusteCriticoInventario
    {
        $this->permisos->exigir($usuario, 'inventario.ajustes_criticos.crear');

        $empresaId = (int) $usuario->empresa_activa_id;

        $tipo = $this->obtenerTipoAjusteCriticoActivo(
            (int) ($datos['tipo_ajuste_critico_id'] ?? 0)
        );

        $producto = $this->obtenerProductoActivoEmpresa(
            (int) ($datos['producto_id'] ?? 0),
            $empresaId
        );

        $bodega = $this->obtenerBodegaActivaEmpresa(
            (int) ($datos['bodega_id'] ?? 0),
            $empresaId
        );

        $cantidad = $this->normalizarCantidad($datos['cantidad'] ?? null);
        $motivo = $this->normalizarTextoObligatorio(
            $datos['motivo'] ?? null,
            'motivo',
            'El motivo es obligatorio para registrar un ajuste crítico.',
            180
        );

        $observacion = $this->normalizarTextoObligatorio(
            $datos['observacion'] ?? null,
            'observacion',
            'La observación es obligatoria para registrar un ajuste crítico.',
            2000
        );

        $referencia = $this->normalizarTextoOpcional($datos['referencia'] ?? null, 'referencia', 120);
        $origenModulo = $this->normalizarTextoOpcional($datos['origen_modulo'] ?? null, 'origen_modulo', 80);
        $origenId = $this->normalizarEnteroPositivoNullable($datos['origen_id'] ?? null, 'origen_id');
        $costoUnitario = $this->normalizarDecimalNullable($datos['costo_unitario'] ?? null, 'costo_unitario');
        $loteId = $this->normalizarEnteroPositivoNullable($datos['lote_id'] ?? null, 'lote_id');

        $this->validarProductoPermiteTipoCritico($producto, $tipo);

        return DB::transaction(function () use (
            $usuario,
            $empresaId,
            $tipo,
            $producto,
            $bodega,
            $cantidad,
            $motivo,
            $observacion,
            $referencia,
            $origenModulo,
            $origenId,
            $costoUnitario,
            $loteId,
            $datos
        ) {
            $datosMovimiento = $this->prepararDatosMovimiento(
                tipo: $tipo,
                producto: $producto,
                bodega: $bodega,
                cantidad: $cantidad,
                motivo: $motivo,
                observacion: $observacion,
                referencia: $referencia,
                costoUnitario: $costoUnitario,
                fechaMovimiento: $datos['fecha_movimiento'] ?? null,
                loteId: $loteId
            );

            if (array_key_exists('costo_cero_confirmado', $datos)) {
                $datosMovimiento['costo_cero_confirmado'] = (bool) $datos['costo_cero_confirmado'];
            }

            $movimiento = $this->movimientoService->registrarMovimiento(
                $datosMovimiento,
                $empresaId,
                (int) $usuario->id
            );

            // Para poder anular limpiamente este ajuste más adelante, se rastrea la capa FIFO
            // exacta que la entrada creó (ver vincularCapaCreadaPorMovimiento()). En PMP no hay
            // capas individuales, así que esto solo aplica a productos FIFO positivos.
            $capaCreada = $tipo->esAjustePositivo() && $this->productoUsaFifo($producto)
                ? $this->vincularCapaCreadaPorMovimiento($empresaId, $producto, $bodega, $loteId, $movimiento)
                : null;

            $ajuste = AjusteCriticoInventario::create([
                'empresa_id' => $empresaId,
                'movimiento_inventario_id' => $movimiento->id,
                'tipo_ajuste_critico_id' => $tipo->id,
                'producto_id' => $producto->id,
                'bodega_id' => $bodega->id,
                'cantidad' => $cantidad,
                'costo_unitario' => (float) $movimiento->costo_unitario,
                'costo_total' => (float) $movimiento->costo_total,
                'motivo' => $motivo,
                'observacion' => $observacion,
                'referencia' => $referencia,
                'origen_modulo' => $origenModulo,
                'origen_id' => $origenId,
                'registrado_por' => (int) $usuario->id,
                'valorizacion_capa_id' => $capaCreada?->id,
            ]);

            $accionAuditoria = $this->accionAuditoriaPorTipo($tipo);
            $metadataEvento = [
                'tipo_ajuste_critico_id' => $tipo->id,
                'tipo_ajuste_codigo' => $tipo->codigo,
                'tipo_movimiento' => $tipo->tipo_movimiento,
                'movimiento_inventario_id' => $movimiento->id,
                'producto_id' => $producto->id,
                'bodega_id' => $bodega->id,
                'cantidad' => $cantidad,
            ];

            $this->auditoria->registrarEvento($usuario, [
                'empresa_id' => $empresaId,
                'accion' => $accionAuditoria,
                'entidad_tipo' => AjusteCriticoInventario::class,
                'entidad_id' => (int) $ajuste->id,
                'severidad' => InventarioAuditoriaEvento::SEVERIDAD_CRITICAL,
                'descripcion' => 'Ajuste crítico de inventario registrado con impacto operativo.',
                'referencia' => $referencia,
                'motivo' => $motivo,
                'observacion' => $observacion,
                'origen_modulo' => $origenModulo,
                'origen_id' => $origenId,
                'metadata_json' => $metadataEvento,
            ]);

            $this->eventosIntegracion->publicarDesdeOperacion(
                $usuario,
                $accionAuditoria === InventarioAuditoriaEvento::ACCION_MERMA_REGISTRADA
                    ? InventarioEventoIntegracion::EVENTO_MERMA_REGISTRADA
                    : InventarioEventoIntegracion::EVENTO_AJUSTE_CRITICO_CREADO,
                [
                    'empresa_id' => $empresaId,
                    'entidad_tipo' => AjusteCriticoInventario::class,
                    'entidad_id' => (int) $ajuste->id,
                    'prioridad' => InventarioEventoIntegracion::PRIORIDAD_CRITICA,
                    'payload_json' => $metadataEvento,
                    'metadata_json' => [
                        'referencia' => $referencia,
                        'motivo' => $motivo,
                        'observacion' => $observacion,
                    ],
                    'origen_modulo' => $origenModulo,
                    'origen_id' => $origenId,
                ],
                true
            );

            return $ajuste->load([
                'tipo:id,codigo,nombre,descripcion,tipo_movimiento,requiere_stock,activo',
                'producto:id,empresa_id,sku,nombre,activo,permite_merma',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'movimiento:id,empresa_id,producto_id,tipo,bodega_origen_id,bodega_destino_id,cantidad,stock_origen_antes,stock_origen_despues,stock_destino_antes,stock_destino_despues,costo_unitario,costo_total,referencia,motivo,observacion,created_by,fecha_movimiento',
                'registradoPor:id,nombre,email',
            ]);
        });
    }

    /**
     * Revierte con un movimiento compensatorio (no edita/borra el original) y reusa
     * registrarMovimiento() para que valorización y capas FIFO/lote usen la misma lógica
     * probada, no aritmética manual sobre el stock. Para un ajuste positivo, la reversa apunta
     * a la capa FIFO exacta (o al costo original en PMP) que ese ajuste creó, no a una salida
     * genérica por costo actual/cronológico (ver HALLAZGOS-COLATERALES.md).
     */
    public function anularAjusteCritico(User $usuario, int $ajusteCriticoId, string $motivoAnulacion): AjusteCriticoInventario
    {
        $this->permisos->exigir($usuario, 'inventario.ajustes_criticos.crear');

        $empresaId = (int) $usuario->empresa_activa_id;

        $motivoAnulacion = $this->normalizarTextoObligatorio(
            $motivoAnulacion,
            'motivo_anulacion',
            'El motivo de anulación es obligatorio.',
            500
        );

        return DB::transaction(function () use ($usuario, $empresaId, $ajusteCriticoId, $motivoAnulacion) {
            $ajuste = AjusteCriticoInventario::query()
                ->with(['tipo', 'movimiento', 'producto'])
                ->empresa($empresaId)
                ->lockForUpdate()
                ->find($ajusteCriticoId);

            if (! $ajuste) {
                throw InventarioException::noEncontrado('El ajuste crítico solicitado no existe o no pertenece a la empresa.');
            }

            if ($ajuste->estaAnulado()) {
                throw InventarioException::regla('Este ajuste crítico ya fue anulado anteriormente.');
            }

            $tipo = $ajuste->tipo;
            $movimientoOriginal = $ajuste->movimiento;

            $datosReversa = [
                'producto_id' => $ajuste->producto_id,
                'cantidad' => (float) $ajuste->cantidad,
                'referencia' => $ajuste->referencia,
                'motivo' => MovimientoInventario::MOTIVO_CORRECCION_STOCK,
                'observacion' => "Reversa de ajuste crítico #{$ajuste->id} ({$tipo->codigo}). Motivo: {$motivoAnulacion}",
                'fecha_movimiento' => now(),
                '_origen_operativo' => 'inventario_ajuste_critico_reversa',
            ];

            $loteId = $movimientoOriginal?->lotes()->value('lote_id');
            if ($loteId) {
                $datosReversa['lote_id'] = $loteId;
            }

            if ($tipo->esAjustePositivo()) {
                // Original sumó stock en bodega_destino -> reversa resta en la misma bodega.
                $datosReversa['tipo'] = MovimientoInventario::TIPO_AJUSTE_NEGATIVO;
                $datosReversa['bodega_origen_id'] = $ajuste->bodega_id;

                if ($this->productoUsaFifo($ajuste->producto)) {
                    // La reversa debe anular exactamente la capa que este ajuste creó, no una
                    // salida FIFO cronológica genérica (ver InventarioValorizacionCapa y el
                    // hallazgo documentado en HALLAZGOS-COLATERALES.md).
                    if ($ajuste->valorizacion_capa_id === null) {
                        throw InventarioException::regla(
                            'No es posible anular limpiamente este ajuste crítico: no quedó registrada la '
                            .'capa de valorización FIFO que generó (fue registrado antes de esta validación). '
                            .'Corrija el stock y el costeo manualmente en vez de anular este ajuste.'
                        );
                    }

                    $datosReversa['_capa_objetivo_id'] = $ajuste->valorizacion_capa_id;
                } else {
                    // PMP es un promedio ponderado, no capas individuales: una vez que el costo del
                    // ajuste se mezcla en el promedio, ya no puede aislarse si algo más consumió o
                    // modificó el stock después. Exigir que nada más haya tocado este producto/bodega
                    // desde el movimiento del ajuste es la única forma segura de revertir sin
                    // distorsionar el costeo.
                    $huboMovimientoPosterior = MovimientoInventario::query()
                        ->where('empresa_id', $empresaId)
                        ->where('producto_id', $ajuste->producto_id)
                        ->where(function ($query) use ($ajuste) {
                            $query->where('bodega_origen_id', $ajuste->bodega_id)
                                ->orWhere('bodega_destino_id', $ajuste->bodega_id);
                        })
                        ->where('id', '>', $ajuste->movimiento_inventario_id)
                        ->exists();

                    if ($huboMovimientoPosterior) {
                        throw InventarioException::regla(
                            'No es posible anular limpiamente este ajuste crítico: el costo promedio '
                            .'ponderado (PMP) de este producto/bodega ya fue afectado por movimientos '
                            .'posteriores. Revertir ahora distorsionaría el costeo. Corrija con un ajuste '
                            .'manual adicional en vez de anular este ajuste.'
                        );
                    }

                    $datosReversa['_costo_unitario_forzado'] = (float) $ajuste->costo_unitario;
                }
            } else {
                // Original restó stock en bodega_origen -> reversa suma en la misma bodega, preservando el costo unitario original para no distorsionar el promedio.
                $datosReversa['tipo'] = MovimientoInventario::TIPO_AJUSTE_POSITIVO;
                $datosReversa['bodega_destino_id'] = $ajuste->bodega_id;
                $datosReversa['costo_unitario'] = (float) $ajuste->costo_unitario;
                // El costo viene heredado del ajuste original (ya aceptado cuando se registro), no es
                // una decision nueva del usuario: la reversa nunca debe exigir reconfirmacion de costo cero.
                $datosReversa['costo_cero_confirmado'] = true;
            }

            $movimientoReversa = $this->movimientoService->registrarMovimiento(
                $datosReversa,
                $empresaId,
                (int) $usuario->id
            );

            $ajuste->update([
                'anulado_at' => now(),
                'anulado_por' => $usuario->id,
                'motivo_anulacion' => $motivoAnulacion,
                'movimiento_reversa_id' => $movimientoReversa->id,
            ]);

            $this->auditoria->registrarEvento($usuario, [
                'empresa_id' => $empresaId,
                'accion' => InventarioAuditoriaEvento::ACCION_AJUSTE_CRITICO_CREADO,
                'entidad_tipo' => AjusteCriticoInventario::class,
                'entidad_id' => (int) $ajuste->id,
                'severidad' => InventarioAuditoriaEvento::SEVERIDAD_CRITICAL,
                'descripcion' => 'Ajuste crítico de inventario anulado (movimiento compensatorio registrado).',
                'motivo' => $motivoAnulacion,
                'metadata_json' => [
                    'ajuste_critico_id' => $ajuste->id,
                    'movimiento_original_id' => $ajuste->movimiento_inventario_id,
                    'movimiento_reversa_id' => $movimientoReversa->id,
                ],
            ]);

            return $ajuste->fresh([
                'tipo:id,codigo,nombre,descripcion,tipo_movimiento,requiere_stock,activo',
                'producto:id,empresa_id,sku,nombre,activo,permite_merma',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'movimiento',
                'movimientoReversa',
                'registradoPor:id,nombre,email',
                'anuladoPor:id,nombre,email',
            ]);
        });
    }

    private function prepararDatosMovimiento(
        TipoAjusteCritico $tipo,
        Producto $producto,
        Bodega $bodega,
        float $cantidad,
        string $motivo,
        string $observacion,
        ?string $referencia,
        ?float $costoUnitario,
        mixed $fechaMovimiento,
        ?int $loteId = null
    ): array {
        $tipoMovimiento = $tipo->tipo_movimiento;

        if (! in_array($tipoMovimiento, TipoAjusteCritico::tiposMovimientoPermitidos(), true)) {
            throw ValidationException::withMessages([
                'tipo_ajuste_critico_id' => 'El tipo de ajuste crítico tiene un tipo de movimiento no válido.',
            ]);
        }

        $datosMovimiento = [
            'tipo' => $tipoMovimiento,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'referencia' => $referencia,
            'motivo' => $this->motivoMovimientoPorTipo($tipo),
            'observacion' => $this->observacionMovimiento($tipo, $motivo, $observacion),
            'fecha_movimiento' => $fechaMovimiento ?: now(),
            '_origen_operativo' => 'inventario_ajuste_critico',
        ];

        if ($loteId !== null) {
            $datosMovimiento['lote_id'] = $loteId;
        }

        if ($tipo->esAjustePositivo()) {
            $datosMovimiento['bodega_destino_id'] = $bodega->id;

            if ($costoUnitario !== null) {
                $datosMovimiento['costo_unitario'] = $costoUnitario;
            } elseif (strtoupper((string) $producto->metodo_valorizacion) === 'FIFO') {
                // Sin esta validacion explicita, la excepcion real la lanza FifoValorizacionStrategy
                // varios niveles mas abajo en la pila, con un mensaje generico que no menciona el
                // contexto de "ajuste critico".
                throw ValidationException::withMessages([
                    'costo_unitario' => 'El ajuste crítico positivo de un producto FIFO requiere informar costo_unitario.',
                ]);
            }

            return $datosMovimiento;
        }

        if ($tipo->esAjusteNegativo()) {
            $datosMovimiento['bodega_origen_id'] = $bodega->id;

            return $datosMovimiento;
        }

        throw ValidationException::withMessages([
            'tipo_ajuste_critico_id' => 'El tipo de ajuste crítico no puede generar movimiento de inventario.',
        ]);
    }

    private function accionAuditoriaPorTipo(TipoAjusteCritico $tipo): string
    {
        return match ($tipo->codigo) {
            TipoAjusteCritico::CODIGO_MERMA_OPERACIONAL,
            TipoAjusteCritico::CODIGO_DETERIORO,
            TipoAjusteCritico::CODIGO_VENCIMIENTO => InventarioAuditoriaEvento::ACCION_MERMA_REGISTRADA,
            default => InventarioAuditoriaEvento::ACCION_AJUSTE_CRITICO_CREADO,
        };
    }

    private function motivoMovimientoPorTipo(TipoAjusteCritico $tipo): string
    {
        return match ($tipo->codigo) {
            TipoAjusteCritico::CODIGO_MERMA_OPERACIONAL,
            TipoAjusteCritico::CODIGO_DETERIORO,
            TipoAjusteCritico::CODIGO_VENCIMIENTO => MovimientoInventario::MOTIVO_MERMA,

            TipoAjusteCritico::CODIGO_PERDIDA => MovimientoInventario::MOTIVO_PERDIDA,

            default => MovimientoInventario::MOTIVO_CORRECCION_STOCK,
        };
    }

    private function observacionMovimiento(
        TipoAjusteCritico $tipo,
        string $motivo,
        string $observacion
    ): string {
        return trim(sprintf(
            '[%s] %s | %s',
            $tipo->codigo,
            $motivo,
            $observacion
        ));
    }

    private function obtenerTipoAjusteCriticoActivo(int $tipoAjusteCriticoId): TipoAjusteCritico
    {
        $tipo = TipoAjusteCritico::query()
            ->where('id', $tipoAjusteCriticoId)
            ->first();

        if (! $tipo) {
            throw ValidationException::withMessages([
                'tipo_ajuste_critico_id' => 'El tipo de ajuste crítico no existe.',
            ]);
        }

        if (! $tipo->activo) {
            throw ValidationException::withMessages([
                'tipo_ajuste_critico_id' => 'El tipo de ajuste crítico está inactivo.',
            ]);
        }

        return $tipo;
    }

    private function obtenerProductoActivoEmpresa(int $productoId, int $empresaId): Producto
    {
        $producto = Producto::query()
            ->where('id', $productoId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $producto) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto no existe o no pertenece a la empresa.',
            ]);
        }

        if (! $producto->activo) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto está inactivo.',
            ]);
        }

        return $producto;
    }

    private function obtenerBodegaActivaEmpresa(int $bodegaId, int $empresaId): Bodega
    {
        $bodega = Bodega::query()
            ->where('id', $bodegaId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $bodega) {
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

    private function productoUsaFifo(Producto $producto): bool
    {
        return strtoupper((string) $producto->metodo_valorizacion) === 'FIFO';
    }

    /**
     * Ubica y vincula la capa de valorización FIFO que la entrada de este movimiento acaba de
     * crear. calcularEntrada() no recibe el id del movimiento (se crea después de calcular la
     * valorización), así que la capa se localiza dentro de la misma transacción bloqueada por
     * InventarioMovimientoService::obtenerOCrearStockBloqueado(), buscando la más reciente sin
     * movimiento_origen_id asignado que coincida en cantidad y costo. Solo aplica a productos
     * FIFO: PMP no crea capas individuales.
     */
    private function vincularCapaCreadaPorMovimiento(
        int $empresaId,
        Producto $producto,
        Bodega $bodega,
        ?int $loteId,
        MovimientoInventario $movimiento
    ): ?InventarioValorizacionCapa {
        $capa = InventarioValorizacionCapa::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->when($loteId !== null, fn ($query) => $query->where('lote_id', $loteId))
            ->whereNull('movimiento_origen_id')
            ->where('cantidad_inicial', (float) $movimiento->cantidad)
            ->where('costo_unitario', (float) $movimiento->costo_unitario)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($capa) {
            $capa->update(['movimiento_origen_id' => $movimiento->id]);
        }

        return $capa;
    }

    private function validarProductoPermiteTipoCritico(
        Producto $producto,
        TipoAjusteCritico $tipo
    ): void {
        if (
            $tipo->codigo === TipoAjusteCritico::CODIGO_MERMA_OPERACIONAL
            && ! $producto->permite_merma
        ) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto no permite registrar mermas.',
            ]);
        }
    }

    private function normalizarCantidad(mixed $cantidad): float
    {
        if (! is_numeric($cantidad)) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad debe ser numérica.',
            ]);
        }

        $cantidad = round((float) $cantidad, 4);

        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad debe ser mayor a cero.',
            ]);
        }

        return $cantidad;
    }

    private function normalizarDecimalNullable(mixed $valor, string $campo): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (! is_numeric($valor)) {
            throw ValidationException::withMessages([
                $campo => 'El valor debe ser numérico.',
            ]);
        }

        $valor = round((float) $valor, 4);

        if ($valor < 0) {
            throw ValidationException::withMessages([
                $campo => 'El valor no puede ser negativo.',
            ]);
        }

        return $valor;
    }

    private function normalizarEnteroPositivoNullable(mixed $valor, string $campo): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (! is_numeric($valor)) {
            throw ValidationException::withMessages([
                $campo => 'El valor debe ser numérico.',
            ]);
        }

        $valor = (int) $valor;

        if ($valor <= 0) {
            throw ValidationException::withMessages([
                $campo => 'El valor debe ser mayor a cero.',
            ]);
        }

        return $valor;
    }

    private function normalizarTextoObligatorio(
        mixed $valor,
        string $campo,
        string $mensajeObligatorio,
        int $maximo
    ): string {
        $valor = trim((string) $valor);

        if ($valor === '') {
            throw ValidationException::withMessages([
                $campo => $mensajeObligatorio,
            ]);
        }

        if (mb_strlen($valor) > $maximo) {
            throw ValidationException::withMessages([
                $campo => "El campo {$campo} no puede superar {$maximo} caracteres.",
            ]);
        }

        return $valor;
    }

    private function normalizarTextoOpcional(mixed $valor, string $campo, int $maximo): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        if (mb_strlen($valor) > $maximo) {
            throw ValidationException::withMessages([
                $campo => "El campo {$campo} no puede superar {$maximo} caracteres.",
            ]);
        }

        return $valor;
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 100);
    }
}
