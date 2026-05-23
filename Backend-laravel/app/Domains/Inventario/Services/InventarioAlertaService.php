<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Events\AlertasInventarioActualizadas;
use App\Domains\Inventario\Events\LoteVencidoDetectado;
use App\Domains\Inventario\Models\AjusteCriticoInventario;
use App\Domains\Inventario\Models\InventarioAlertaEstado;
use App\Domains\Inventario\Models\ReglaReposicion;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\TomaFisicaDetalleInventario;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventarioAlertaService
{
    private const DEFAULT_DIAS_VENCIMIENTO = 30;
    private const DIAS_RESERVA_CRITICA = 3;
    private const DIAS_TOMA_PENDIENTE = 7;
    private const DIAS_AJUSTE_RECIENTE = 7;

    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioReposicionService $reposicionService,
        private readonly InventarioDisponibilidadService $disponibilidadService
    ) {
    }

    public function listar(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.alertas.ver');

        $empresaId = (int) $usuario->empresa_id;
        $alertas = $this->listarPersistidasParaEmpresa($empresaId, $filtros);

        return [
            'data' => array_values($alertas),
            'resumen' => $this->resumen($alertas),
            'metadata' => [
                'generado_en' => now()->toISOString(),
                'fuente' => 'inventario_alertas_estado',
                'criterios' => [
                    'dias_default_vencimiento' => self::DEFAULT_DIAS_VENCIMIENTO,
                    'dias_reserva_critica' => self::DIAS_RESERVA_CRITICA,
                    'dias_toma_pendiente' => self::DIAS_TOMA_PENDIENTE,
                    'dias_ajuste_reciente' => self::DIAS_AJUSTE_RECIENTE,
                ],
            ],
        ];
    }

    public function recalcularPersistidasParaEmpresa(int $empresaId, array $filtros = []): array
    {
        $alertas = $this->ordenarAlertas($this->aplicarFiltros(
            $this->calcularDinamicasParaEmpresa($empresaId, $filtros),
            $filtros
        ));

        $calculadoEn = now();

        $referenciasExistentes = InventarioAlertaEstado::query()
            ->where('empresa_id', $empresaId)
            ->get(['tipo', 'referencia'])
            ->mapWithKeys(fn (InventarioAlertaEstado $alerta) => [$alerta->tipo . '|' . $alerta->referencia => true]);

        DB::transaction(function () use ($empresaId, $alertas, $calculadoEn) {
            InventarioAlertaEstado::query()
                ->where('empresa_id', $empresaId)
                ->delete();

            foreach ($alertas as $alerta) {
                InventarioAlertaEstado::create($this->normalizarAlertaPersistida($empresaId, $alerta, $calculadoEn));
            }
        });

        $resumen = $this->resumen($alertas);

        DB::afterCommit(function () use ($empresaId, $alertas, $referenciasExistentes, $resumen, $calculadoEn) {
            event(new AlertasInventarioActualizadas(
                empresaId: $empresaId,
                total: (int) $resumen['total'],
                criticas: (int) $resumen['criticas'],
                calculadoEn: $calculadoEn->toISOString()
            ));

            foreach ($alertas as $alerta) {
                $referencia = (string) ($alerta['referencia'] ?? '');
                $key = ($alerta['tipo'] ?? '') . '|' . $referencia;

                if (($alerta['tipo'] ?? null) !== 'LOTE_VENCIDO' || $referenciasExistentes->has($key)) {
                    continue;
                }

                if (empty($alerta['producto_id']) || empty($alerta['bodega_id']) || empty($alerta['lote_id']) || empty($alerta['fecha_referencia'])) {
                    continue;
                }

                event(new LoteVencidoDetectado(
                    empresaId: $empresaId,
                    productoId: (int) $alerta['producto_id'],
                    bodegaId: (int) $alerta['bodega_id'],
                    loteId: (int) $alerta['lote_id'],
                    stockActual: (float) ($alerta['cantidad_actual'] ?? 0),
                    fechaVencimiento: (string) $alerta['fecha_referencia'],
                    referencia: $referencia
                ));
            }
        });

        return [
            'data' => array_values($alertas),
            'resumen' => $resumen,
            'metadata' => [
                'calculado_en' => $calculadoEn->toISOString(),
                'persistidas' => true,
            ],
        ];
    }

    public function calcularDinamicasParaEmpresa(int $empresaId, array $filtros = []): array
    {
        return array_merge(
            $this->alertasStockYReposicion($empresaId, $filtros),
            $this->alertasVencimientos($empresaId, $filtros),
            $this->alertasReservasCriticas($empresaId, $filtros),
            $this->alertasTomasFisicasPendientes($empresaId, $filtros),
            $this->alertasAjustesCriticosRecientes($empresaId, $filtros)
        );
    }

    public function listarPersistidasParaEmpresa(int $empresaId, array $filtros = []): array
    {
        $limit = $this->normalizarLimit($filtros['limit'] ?? 100);

        return InventarioAlertaEstado::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo,estado_operativo',
            ])
            ->when(!empty($filtros['tipo']), fn (Builder $query) => $query->where('tipo', (string) $filtros['tipo']))
            ->when(!empty($filtros['severidad']), fn (Builder $query) => $query->where('severidad', (string) $filtros['severidad']))
            ->when(!empty($filtros['producto_id']), fn (Builder $query) => $query->where('producto_id', (int) $filtros['producto_id']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where(function (Builder $subQuery) use ($filtros) {
                $subQuery->where('bodega_id', (int) $filtros['bodega_id'])->orWhereNull('bodega_id');
            }))
            ->orderByRaw("CASE severidad WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 WHEN 'baja' THEN 4 ELSE 5 END")
            ->orderBy('fecha_referencia')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (InventarioAlertaEstado $alerta) => $this->mapearAlertaPersistida($alerta))
            ->values()
            ->all();
    }

    private function alertasStockYReposicion(int $empresaId, array $filtros): array
    {
        $alertas = [];
        $evaluaciones = $this->reposicionService->evaluacionesParaEmpresa($empresaId, $filtros);

        foreach ($evaluaciones as $evaluacion) {
            $stockActual = (float) $evaluacion['stock_actual'];
            $stockMinimo = (float) $evaluacion['stock_minimo'];
            $stockObjetivo = (float) $evaluacion['stock_objetivo'];
            $cantidadSugerida = (float) $evaluacion['cantidad_sugerida'];

            if ($stockMinimo > 0 && $stockActual <= $stockMinimo) {
                $alertas[] = $this->crearAlerta([
                    'tipo' => 'STOCK_BAJO',
                    'severidad' => $evaluacion['severidad'],
                    'titulo' => 'Stock bajo detectado',
                    'descripcion' => sprintf(
                        'El producto %s tiene stock actual %s, igual o inferior al mínimo definido %s.',
                        $evaluacion['producto_nombre'] ?? ('#' . $evaluacion['producto_id']),
                        $this->formatoCantidad($stockActual),
                        $this->formatoCantidad($stockMinimo)
                    ),
                    'producto_id' => $evaluacion['producto_id'],
                    'producto_nombre' => $evaluacion['producto_nombre'],
                    'bodega_id' => $evaluacion['bodega_id'],
                    'bodega_nombre' => $evaluacion['bodega_nombre'],
                    'cantidad_actual' => $stockActual,
                    'stock_minimo' => $stockMinimo,
                    'stock_objetivo' => $stockObjetivo,
                    'cantidad_sugerida' => $cantidadSugerida,
                    'referencia' => 'REG-REP-' . $evaluacion['regla_id'],
                    'metadata' => [
                        'regla_id' => $evaluacion['regla_id'],
                        'alcance' => $evaluacion['alcance'],
                    ],
                ]);
            }

            if ($cantidadSugerida > 0) {
                $alertas[] = $this->crearAlerta([
                    'tipo' => 'REPOSICION_SUGERIDA',
                    'severidad' => $stockActual <= $stockMinimo && $stockMinimo > 0 ? 'alta' : 'media',
                    'titulo' => 'Reposición sugerida',
                    'descripcion' => sprintf(
                        'Se sugiere reponer %s unidades de %s para alcanzar el objetivo operativo.',
                        $this->formatoCantidad($cantidadSugerida),
                        $evaluacion['producto_nombre'] ?? ('#' . $evaluacion['producto_id'])
                    ),
                    'producto_id' => $evaluacion['producto_id'],
                    'producto_nombre' => $evaluacion['producto_nombre'],
                    'bodega_id' => $evaluacion['bodega_id'],
                    'bodega_nombre' => $evaluacion['bodega_nombre'],
                    'cantidad_actual' => $stockActual,
                    'stock_minimo' => $stockMinimo,
                    'stock_objetivo' => $stockObjetivo,
                    'cantidad_sugerida' => $cantidadSugerida,
                    'referencia' => 'REG-REP-' . $evaluacion['regla_id'],
                    'metadata' => [
                        'regla_id' => $evaluacion['regla_id'],
                        'punto_reorden' => $evaluacion['punto_reorden'],
                        'alcance' => $evaluacion['alcance'],
                    ],
                ]);
            }
        }

        return $alertas;
    }

    private function alertasVencimientos(int $empresaId, array $filtros): array
    {
        $hoy = CarbonImmutable::today();
        $alertas = [];

        $stockLotes = StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('stock_actual', '>', 0)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo',
            ])
            ->whereHas('lote', function (Builder $query) {
                $query
                    ->where('activo', true)
                    ->whereNotNull('fecha_vencimiento');
            })
            ->when(!empty($filtros['producto_id']), function (Builder $query) use ($filtros) {
                $query->where('producto_id', (int) $filtros['producto_id']);
            })
            ->when(!empty($filtros['bodega_id']), function (Builder $query) use ($filtros) {
                $query->where('bodega_id', (int) $filtros['bodega_id']);
            })
            ->orderBy('lote_id')
            ->limit(150)
            ->get();

        foreach ($stockLotes as $stockLote) {
            $lote = $stockLote->lote;

            if (!$lote?->fecha_vencimiento) {
                continue;
            }

            $fechaVencimiento = CarbonImmutable::parse($lote->fecha_vencimiento->toDateString());
            $diasAlerta = $this->diasAlertaVencimiento($empresaId, (int) $stockLote->producto_id, (int) $stockLote->bodega_id);

            if ($fechaVencimiento->lt($hoy)) {
                $alertas[] = $this->crearAlerta([
                    'tipo' => 'LOTE_VENCIDO',
                    'severidad' => 'critica',
                    'titulo' => 'Lote vencido con stock disponible',
                    'descripcion' => sprintf(
                        'El lote %s del producto %s está vencido y aún registra stock.',
                        $lote->codigo_lote,
                        $stockLote->producto?->nombre ?? ('#' . $stockLote->producto_id)
                    ),
                    'producto_id' => (int) $stockLote->producto_id,
                    'producto_nombre' => $stockLote->producto?->nombre,
                    'bodega_id' => (int) $stockLote->bodega_id,
                    'bodega_nombre' => $stockLote->bodega?->nombre,
                    'lote_id' => (int) $stockLote->lote_id,
                    'lote_codigo' => $lote->codigo_lote,
                    'cantidad_actual' => (float) $stockLote->stock_actual,
                    'fecha_referencia' => $fechaVencimiento->toDateString(),
                    'referencia' => 'LOTE-' . $lote->id,
                    'metadata' => [
                        'dias_vencido' => $fechaVencimiento->diffInDays($hoy),
                    ],
                ]);

                continue;
            }

            if ($fechaVencimiento->lte($hoy->addDays($diasAlerta))) {
                $diasRestantes = $hoy->diffInDays($fechaVencimiento);

                $alertas[] = $this->crearAlerta([
                    'tipo' => 'LOTE_POR_VENCER',
                    'severidad' => $diasRestantes <= 7 ? 'alta' : 'media',
                    'titulo' => 'Lote próximo a vencer',
                    'descripcion' => sprintf(
                        'El lote %s vence en %s días y mantiene stock operativo.',
                        $lote->codigo_lote,
                        $diasRestantes
                    ),
                    'producto_id' => (int) $stockLote->producto_id,
                    'producto_nombre' => $stockLote->producto?->nombre,
                    'bodega_id' => (int) $stockLote->bodega_id,
                    'bodega_nombre' => $stockLote->bodega?->nombre,
                    'lote_id' => (int) $stockLote->lote_id,
                    'lote_codigo' => $lote->codigo_lote,
                    'cantidad_actual' => (float) $stockLote->stock_actual,
                    'fecha_referencia' => $fechaVencimiento->toDateString(),
                    'referencia' => 'LOTE-' . $lote->id,
                    'metadata' => [
                        'dias_restantes' => $diasRestantes,
                        'dias_alerta_configurados' => $diasAlerta,
                    ],
                ]);
            }
        }

        return $alertas;
    }

    private function alertasReservasCriticas(int $empresaId, array $filtros): array
    {
        $hoy = CarbonImmutable::today();
        $alertas = [];

        $detalles = ReservaDetalleInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'reserva:id,empresa_id,codigo_reserva,estado,referencia,fecha_reserva,fecha_expiracion',
                'producto:id,empresa_id,sku,nombre,stock_minimo,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo',
            ])
            ->whereHas('reserva', function (Builder $query) use ($empresaId) {
                $query
                    ->where('empresa_id', $empresaId)
                    ->whereIn('estado', ReservaInventario::estadosQueComprometenDisponibilidad());
            })
            ->when(!empty($filtros['producto_id']), function (Builder $query) use ($filtros) {
                $query->where('producto_id', (int) $filtros['producto_id']);
            })
            ->when(!empty($filtros['bodega_id']), function (Builder $query) use ($filtros) {
                $query->where('bodega_id', (int) $filtros['bodega_id']);
            })
            ->limit(150)
            ->get();

        foreach ($detalles as $detalle) {
            $pendiente = $this->cantidadPendienteReserva($detalle);

            if ($pendiente <= 0) {
                continue;
            }

            $reserva = $detalle->reserva;
            $expiraPronto = false;
            $diasExpiracion = null;

            if ($reserva?->fecha_expiracion) {
                $fechaExpiracion = CarbonImmutable::parse($reserva->fecha_expiracion->toDateString());
                $diasExpiracion = $hoy->diffInDays($fechaExpiracion, false);
                $expiraPronto = $diasExpiracion <= self::DIAS_RESERVA_CRITICA;
            }

            $disponibilidad = $detalle->lote_id
                ? $this->disponibilidadService->calcularDisponibilidadLote($empresaId, (int) $detalle->producto_id, (int) $detalle->bodega_id, (int) $detalle->lote_id)
                : $this->disponibilidadService->calcularDisponibilidad($empresaId, (int) $detalle->producto_id, (int) $detalle->bodega_id);

            $stockDisponible = (float) $disponibilidad['stock_disponible'];
            $stockMinimo = $this->reposicionService->umbralMinimoPara($empresaId, (int) $detalle->producto_id, (int) $detalle->bodega_id);
            $stockBajo = $stockMinimo > 0 && $stockDisponible <= $stockMinimo;

            if (!$expiraPronto && !$stockBajo) {
                continue;
            }

            $alertas[] = $this->crearAlerta([
                'tipo' => 'RESERVA_CRITICA',
                'severidad' => $diasExpiracion !== null && $diasExpiracion < 0 ? 'critica' : ($stockBajo ? 'alta' : 'media'),
                'titulo' => 'Reserva crítica',
                'descripcion' => sprintf(
                    'La reserva %s mantiene %s unidades pendientes%s.',
                    $reserva?->codigo_reserva ?? ('#' . $detalle->reserva_id),
                    $this->formatoCantidad($pendiente),
                    $stockBajo ? ' y el stock disponible está bajo el umbral operativo' : ''
                ),
                'producto_id' => (int) $detalle->producto_id,
                'producto_nombre' => $detalle->producto?->nombre,
                'bodega_id' => (int) $detalle->bodega_id,
                'bodega_nombre' => $detalle->bodega?->nombre,
                'lote_id' => $detalle->lote_id !== null ? (int) $detalle->lote_id : null,
                'lote_codigo' => $detalle->lote?->codigo_lote,
                'cantidad_actual' => $stockDisponible,
                'stock_minimo' => $stockMinimo,
                'fecha_referencia' => $reserva?->fecha_expiracion?->toDateString(),
                'referencia' => $reserva?->referencia ?: $reserva?->codigo_reserva,
                'metadata' => [
                    'reserva_id' => (int) $detalle->reserva_id,
                    'codigo_reserva' => $reserva?->codigo_reserva,
                    'cantidad_pendiente' => $pendiente,
                    'dias_expiracion' => $diasExpiracion,
                ],
            ]);
        }

        return $alertas;
    }

    private function alertasTomasFisicasPendientes(int $empresaId, array $filtros): array
    {
        $hoy = CarbonImmutable::today();
        $alertas = [];

        $tomas = TomaFisicaInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [
                TomaFisicaInventario::ESTADO_BORRADOR,
                TomaFisicaInventario::ESTADO_EN_CONTEO,
                TomaFisicaInventario::ESTADO_CERRADA,
            ])
            ->with(['bodega:id,empresa_id,codigo,nombre,estado'])
            ->when(!empty($filtros['bodega_id']), function (Builder $query) use ($filtros) {
                $query->where('bodega_id', (int) $filtros['bodega_id']);
            })
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        foreach ($tomas as $toma) {
            $fechaBase = $toma->fecha_inicio ?: $toma->created_at;
            $diasPendiente = $fechaBase ? CarbonImmutable::parse($fechaBase)->diffInDays($hoy) : 0;
            $pendienteAjuste = false;

            if ($toma->estado === TomaFisicaInventario::ESTADO_CERRADA) {
                $pendienteAjuste = TomaFisicaDetalleInventario::query()
                    ->where('empresa_id', $empresaId)
                    ->where('toma_fisica_id', $toma->id)
                    ->where('diferencia', '!=', 0)
                    ->whereNull('movimiento_ajuste_id')
                    ->exists();
            }

            if ($diasPendiente < self::DIAS_TOMA_PENDIENTE && !$pendienteAjuste) {
                continue;
            }

            $alertas[] = $this->crearAlerta([
                'tipo' => 'TOMA_FISICA_PENDIENTE',
                'severidad' => $pendienteAjuste ? 'alta' : 'media',
                'titulo' => 'Toma física pendiente',
                'descripcion' => sprintf(
                    'La toma física %s está en estado %s%s.',
                    $toma->codigo_toma,
                    $toma->estado,
                    $pendienteAjuste ? ' y mantiene diferencias pendientes de ajuste' : ''
                ),
                'bodega_id' => $toma->bodega_id !== null ? (int) $toma->bodega_id : null,
                'bodega_nombre' => $toma->bodega?->nombre,
                'fecha_referencia' => $fechaBase?->toDateString(),
                'referencia' => $toma->referencia ?: $toma->codigo_toma,
                'metadata' => [
                    'toma_fisica_id' => (int) $toma->id,
                    'codigo_toma' => $toma->codigo_toma,
                    'estado' => $toma->estado,
                    'dias_pendiente' => $diasPendiente,
                    'pendiente_ajuste' => $pendienteAjuste,
                ],
            ]);
        }

        return $alertas;
    }

    private function alertasAjustesCriticosRecientes(int $empresaId, array $filtros): array
    {
        $desde = CarbonImmutable::today()->subDays(self::DIAS_AJUSTE_RECIENTE);

        return AjusteCriticoInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('created_at', '>=', $desde->toDateString())
            ->with([
                'tipo:id,codigo,nombre,tipo_movimiento',
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo',
            ])
            ->when(!empty($filtros['producto_id']), function (Builder $query) use ($filtros) {
                $query->where('producto_id', (int) $filtros['producto_id']);
            })
            ->when(!empty($filtros['bodega_id']), function (Builder $query) use ($filtros) {
                $query->where('bodega_id', (int) $filtros['bodega_id']);
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (AjusteCriticoInventario $ajuste) {
                $esNegativo = $ajuste->tipo?->esAjusteNegativo() === true;

                return $this->crearAlerta([
                    'tipo' => 'AJUSTE_CRITICO_RECIENTE',
                    'severidad' => $esNegativo ? 'alta' : 'media',
                    'titulo' => 'Ajuste crítico reciente',
                    'descripcion' => sprintf(
                        'Se registró un ajuste crítico %s por %s unidades.',
                        $ajuste->tipo?->nombre ?? 'operativo',
                        $this->formatoCantidad((float) $ajuste->cantidad)
                    ),
                    'producto_id' => (int) $ajuste->producto_id,
                    'producto_nombre' => $ajuste->producto?->nombre,
                    'bodega_id' => (int) $ajuste->bodega_id,
                    'bodega_nombre' => $ajuste->bodega?->nombre,
                    'lote_id' => $ajuste->lote_id !== null ? (int) $ajuste->lote_id : null,
                    'lote_codigo' => $ajuste->lote?->codigo_lote,
                    'cantidad_actual' => (float) $ajuste->cantidad,
                    'fecha_referencia' => $ajuste->created_at?->toDateString(),
                    'referencia' => $ajuste->referencia,
                    'metadata' => [
                        'ajuste_critico_id' => (int) $ajuste->id,
                        'tipo_ajuste' => $ajuste->tipo?->codigo,
                        'tipo_movimiento' => $ajuste->tipo?->tipo_movimiento,
                        'costo_total' => (float) $ajuste->costo_total,
                    ],
                ]);
            })
            ->values()
            ->all();
    }

    private function normalizarAlertaPersistida(int $empresaId, array $alerta, $calculadoEn): array
    {
        $referencia = $alerta['referencia'] ?? null;

        if (!$referencia) {
            $referencia = sha1(json_encode([
                $alerta['tipo'] ?? null,
                $alerta['producto_id'] ?? null,
                $alerta['bodega_id'] ?? null,
                $alerta['lote_id'] ?? null,
                $alerta['fecha_referencia'] ?? null,
            ]));
        }

        return [
            'empresa_id' => $empresaId,
            'tipo' => (string) ($alerta['tipo'] ?? 'GENERAL'),
            'severidad' => (string) ($alerta['severidad'] ?? 'media'),
            'titulo' => (string) ($alerta['titulo'] ?? 'Alerta de inventario'),
            'descripcion' => $alerta['descripcion'] ?? null,
            'producto_id' => $alerta['producto_id'] ?? null,
            'bodega_id' => $alerta['bodega_id'] ?? null,
            'lote_id' => $alerta['lote_id'] ?? null,
            'cantidad_actual' => $alerta['cantidad_actual'] ?? null,
            'stock_minimo' => $alerta['stock_minimo'] ?? null,
            'stock_objetivo' => $alerta['stock_objetivo'] ?? null,
            'cantidad_sugerida' => $alerta['cantidad_sugerida'] ?? null,
            'fecha_referencia' => $alerta['fecha_referencia'] ?? null,
            'referencia' => (string) $referencia,
            'metadata' => $alerta['metadata'] ?? [],
            'calculado_en' => $calculadoEn,
        ];
    }

    private function mapearAlertaPersistida(InventarioAlertaEstado $alerta): array
    {
        return [
            'id' => (int) $alerta->id,
            'tipo' => $alerta->tipo,
            'severidad' => $alerta->severidad,
            'titulo' => $alerta->titulo,
            'descripcion' => $alerta->descripcion,
            'producto_id' => $alerta->producto_id,
            'producto_nombre' => $alerta->producto?->nombre,
            'producto_sku' => $alerta->producto?->sku,
            'bodega_id' => $alerta->bodega_id,
            'bodega_nombre' => $alerta->bodega?->nombre,
            'lote_id' => $alerta->lote_id,
            'lote_codigo' => $alerta->lote?->codigo_lote,
            'cantidad_actual' => $alerta->cantidad_actual !== null ? (float) $alerta->cantidad_actual : null,
            'stock_minimo' => $alerta->stock_minimo !== null ? (float) $alerta->stock_minimo : null,
            'stock_objetivo' => $alerta->stock_objetivo !== null ? (float) $alerta->stock_objetivo : null,
            'cantidad_sugerida' => $alerta->cantidad_sugerida !== null ? (float) $alerta->cantidad_sugerida : null,
            'fecha_referencia' => $alerta->fecha_referencia?->toDateString(),
            'referencia' => $alerta->referencia,
            'metadata' => $alerta->metadata ?? [],
            'calculado_en' => $alerta->calculado_en?->toISOString(),
        ];
    }


    private function diasAlertaVencimiento(int $empresaId, int $productoId, int $bodegaId): int
    {
        $regla = $this->reposicionService->resolverReglaActiva($empresaId, $productoId, $bodegaId);

        return max((int) ($regla?->dias_alerta_vencimiento ?? self::DEFAULT_DIAS_VENCIMIENTO), 0);
    }

    private function crearAlerta(array $datos): array
    {
        return array_merge([
            'tipo' => null,
            'severidad' => 'media',
            'titulo' => null,
            'descripcion' => null,
            'producto_id' => null,
            'producto_nombre' => null,
            'bodega_id' => null,
            'bodega_nombre' => null,
            'lote_id' => null,
            'lote_codigo' => null,
            'cantidad_actual' => null,
            'stock_minimo' => null,
            'stock_objetivo' => null,
            'cantidad_sugerida' => null,
            'fecha_referencia' => null,
            'referencia' => null,
            'metadata' => [],
        ], $datos);
    }

    private function aplicarFiltros(array $alertas, array $filtros): array
    {
        return collect($alertas)
            ->when(!empty($filtros['tipo']), function ($collection) use ($filtros) {
                return $collection->where('tipo', $filtros['tipo']);
            })
            ->when(!empty($filtros['severidad']), function ($collection) use ($filtros) {
                return $collection->where('severidad', $filtros['severidad']);
            })
            ->when(!empty($filtros['producto_id']), function ($collection) use ($filtros) {
                return $collection->where('producto_id', (int) $filtros['producto_id']);
            })
            ->when(!empty($filtros['bodega_id']), function ($collection) use ($filtros) {
                return $collection->filter(function (array $alerta) use ($filtros) {
                    return $alerta['bodega_id'] === null || (int) $alerta['bodega_id'] === (int) $filtros['bodega_id'];
                });
            })
            ->values()
            ->all();
    }

    private function ordenarAlertas(array $alertas): array
    {
        $pesoSeveridad = [
            'critica' => 4,
            'alta' => 3,
            'media' => 2,
            'baja' => 1,
        ];

        usort($alertas, function (array $a, array $b) use ($pesoSeveridad) {
            $pesoA = $pesoSeveridad[$a['severidad']] ?? 0;
            $pesoB = $pesoSeveridad[$b['severidad']] ?? 0;

            if ($pesoA === $pesoB) {
                return strcmp((string) ($a['fecha_referencia'] ?? ''), (string) ($b['fecha_referencia'] ?? ''));
            }

            return $pesoB <=> $pesoA;
        });

        return $alertas;
    }

    private function resumen(array $alertas): array
    {
        $collection = collect($alertas);

        return [
            'total' => $collection->count(),
            'criticas' => $collection->where('severidad', 'critica')->count(),
            'altas' => $collection->where('severidad', 'alta')->count(),
            'medias' => $collection->where('severidad', 'media')->count(),
            'bajas' => $collection->where('severidad', 'baja')->count(),
            'por_tipo' => $collection->groupBy('tipo')->map->count()->all(),
            'por_severidad' => $collection->groupBy('severidad')->map->count()->all(),
        ];
    }

    private function cantidadPendienteReserva(ReservaDetalleInventario $detalle): float
    {
        return round(
            (float) $detalle->cantidad_reservada
            - (float) $detalle->cantidad_consumida
            - (float) $detalle->cantidad_liberada,
            4
        );
    }

    private function formatoCantidad(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 4, '.', ''), '0'), '.');
    }

    private function normalizarLimit(mixed $limit): int
    {
        $limit = (int) $limit;

        if ($limit <= 0) {
            return 100;
        }

        return min($limit, 200);
    }
}
