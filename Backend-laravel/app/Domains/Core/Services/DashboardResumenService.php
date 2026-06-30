<?php

namespace App\Domains\Core\Services;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\OrdenCompra;
use App\Domains\Contabilidad\Models\DjEnvio;
use App\Domains\Rrhh\Models\Liquidacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardResumenService
{
    /**
     * Retorna el resumen de KPIs, serie de ventas, top clientes, facturas urgentes
     * y secciones gateadas por permiso: compras_12m, inventario, rrhh, alertas_pendientes.
     *
     * @param  int    $empresaId  ID de la empresa activa del usuario
     * @param  string $periodo    'mes' | 'trimestre' | 'año'
     * @param  array  $permisos   Lista de permisos efectivos del usuario (ModuloPermisos::permisosUsuario)
     */
    public function obtener(int $empresaId, string $periodo = 'mes', array $permisos = []): array
    {
        [$inicio, $fin]            = $this->rangoPeriodo($periodo);
        [$inicioAnt, $finAnt]      = $this->rangoPeriodoAnterior($periodo);

        $kpis        = $this->calcularKpis($empresaId, $inicio, $fin, $inicioAnt, $finAnt);
        $serieVentas = $this->serieVentas12m($empresaId);
        $topClientes = $this->topClientes($empresaId);
        $urgentes    = $this->facturasUrgentes($empresaId);

        $resultado = [
            'kpis'              => $kpis,
            'serie_ventas_12m'  => $serieVentas,
            'top_clientes'      => $topClientes,
            'facturas_urgentes' => $urgentes,
            'compras_12m'       => $this->serieCompras12m($empresaId),
        ];

        if (in_array('inventario.productos.ver', $permisos)) {
            $resultado['inventario'] = $this->resumenInventario($empresaId);
        }

        if (in_array('rrhh.remuneraciones.ver', $permisos)) {
            $resultado['rrhh'] = $this->resumenRrhh($empresaId);
        }

        $resultado['alertas_pendientes']          = $this->alertasPendientes($empresaId, $permisos);
        $resultado['aging_ar']                    = $this->agingAR($empresaId);
        $resultado['aging_ap']                    = $this->agingAP($empresaId);
        $resultado['flujo_caja_30d']              = $this->flujoCaja30d($empresaId);
        $resultado['ordenes_compra_pendientes']   = $this->ordenesCompraPendientes($empresaId);

        return $resultado;
    }

    // -------------------------------------------------------------------------
    // KPIs
    // -------------------------------------------------------------------------

    private function calcularKpis(
        int $empresaId,
        Carbon $inicio,
        Carbon $fin,
        Carbon $inicioAnt,
        Carbon $finAnt
    ): array {
        // Ventas periodo actual (facturas tipo VENTA, excluidas ANULADAS)
        $ventasMes = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->where('estado', '!=', 'ANULADA')
            ->whereDate('fecha_emision', '>=', $inicio->toDateString())
            ->whereDate('fecha_emision', '<=', $fin->toDateString())
            ->sum('monto_bruto');

        // Ventas periodo anterior (para variacion)
        $ventasMesAnterior = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->where('estado', '!=', 'ANULADA')
            ->whereDate('fecha_emision', '>=', $inicioAnt->toDateString())
            ->whereDate('fecha_emision', '<=', $finAnt->toDateString())
            ->sum('monto_bruto');

        // Variacion porcentual
        $variacionPct = 0;
        if ($ventasMesAnterior > 0) {
            $variacionPct = round((($ventasMes - $ventasMesAnterior) / $ventasMesAnterior) * 100, 2);
        }

        // COUNT facturas emitidas (VENTA) en periodo
        $facturasEmitidasMes = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->where('estado', '!=', 'ANULADA')
            ->whereBetween('fecha_emision', [$inicio->toDateString(), $fin->toDateString()])
            ->count();

        // COUNT facturas pendientes de cobro (VENTA, no pagadas ni anuladas)
        $facturasPendientes = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->count();

        // COUNT clientes activos
        $clientesActivos = Cliente::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->count();

        // COUNT cotizaciones pendientes (estado 'Enviada' o 'Borrador' — no aceptadas/rechazadas/expiradas)
        $estadosPendientesIds = EstadoCotizacion::whereIn('nombre', ['Borrador', 'Enviada'])
            ->pluck('id');

        $cotizacionesPendientes = 0;
        if ($estadosPendientesIds->isNotEmpty()) {
            $cotizacionesPendientes = Cotizacion::where('empresa_id', $empresaId)
                ->whereIn('estado_id', $estadosPendientesIds)
                ->count();
        }

        return [
            'ventas_mes'               => (float) $ventasMes,
            'ventas_mes_anterior'      => (float) $ventasMesAnterior,
            'variacion_pct'            => $variacionPct,
            'facturas_emitidas_mes'    => $facturasEmitidasMes,
            'facturas_pendientes'      => $facturasPendientes,
            'clientes_activos'         => $clientesActivos,
            'cotizaciones_pendientes'  => $cotizacionesPendientes,
        ];
    }

    // -------------------------------------------------------------------------
    // Serie de ventas 12 meses (agrupado en PHP para compatibilidad SQLite/MySQL)
    // -------------------------------------------------------------------------

    private function serieVentas12m(int $empresaId): array
    {
        $desde = Carbon::now()->startOfMonth()->subMonths(11);

        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->where('estado', '!=', 'ANULADA')
            ->whereDate('fecha_emision', '>=', $desde->toDateString())
            ->get(['fecha_emision', 'monto_bruto']);

        // Inicializar los 12 meses con cero
        $meses = [];
        for ($i = 11; $i >= 0; $i--) {
            $clave = Carbon::now()->startOfMonth()->subMonths($i)->format('Y-m');
            $meses[$clave] = 0.0;
        }

        // Agrupar por mes en PHP (compatible SQLite y MySQL)
        foreach ($filas as $fila) {
            $clave = Carbon::parse($fila->fecha_emision)->format('Y-m');
            if (isset($meses[$clave])) {
                $meses[$clave] += (float) $fila->monto_bruto;
            }
        }

        return array_map(
            fn ($mes, $monto) => ['mes' => $mes, 'monto' => round($monto, 2)],
            array_keys($meses),
            array_values($meses)
        );
    }

    // -------------------------------------------------------------------------
    // Serie de compras 12 meses (agrupado en PHP para compatibilidad SQLite/MySQL)
    // -------------------------------------------------------------------------

    private function serieCompras12m(int $empresaId): array
    {
        $desde = Carbon::now()->startOfMonth()->subMonths(11);

        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->where('estado', '!=', 'ANULADA')
            ->whereDate('fecha_emision', '>=', $desde->toDateString())
            ->get(['fecha_emision', 'monto_bruto']);

        // Inicializar los 12 meses con cero
        $meses = [];
        for ($i = 11; $i >= 0; $i--) {
            $clave = Carbon::now()->startOfMonth()->subMonths($i)->format('Y-m');
            $meses[$clave] = 0.0;
        }

        // Agrupar por mes en PHP (compatible SQLite y MySQL)
        foreach ($filas as $fila) {
            $clave = Carbon::parse($fila->fecha_emision)->format('Y-m');
            if (isset($meses[$clave])) {
                $meses[$clave] += (float) $fila->monto_bruto;
            }
        }

        return array_map(
            fn ($mes, $monto) => ['mes' => $mes, 'monto' => round($monto, 2)],
            array_keys($meses),
            array_values($meses)
        );
    }

    // -------------------------------------------------------------------------
    // AR Aging: facturas VENTA pendientes por tramos de antigüedad
    // -------------------------------------------------------------------------

    private function agingAR(int $empresaId): array
    {
        $hoy = Carbon::now()->toDateString();
        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->whereNotNull('fecha_emision')
            ->get(['fecha_emision', 'monto_bruto']);

        $tramos = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '91+' => 0.0];
        foreach ($filas as $f) {
            $dias = (int) Carbon::parse($f->fecha_emision)->diffInDays($hoy);
            if ($dias <= 30) {
                $tramos['0-30'] += (float) $f->monto_bruto;
            } elseif ($dias <= 60) {
                $tramos['31-60'] += (float) $f->monto_bruto;
            } elseif ($dias <= 90) {
                $tramos['61-90'] += (float) $f->monto_bruto;
            } else {
                $tramos['91+'] += (float) $f->monto_bruto;
            }
        }

        return array_map(
            fn ($tramo, $monto) => ['tramo' => $tramo, 'monto' => round($monto, 2)],
            array_keys($tramos),
            array_values($tramos)
        );
    }

    // -------------------------------------------------------------------------
    // AP Aging: facturas COMPRA pendientes por tramos de antigüedad
    // -------------------------------------------------------------------------

    private function agingAP(int $empresaId): array
    {
        $hoy = Carbon::now()->toDateString();
        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->whereNotNull('fecha_emision')
            ->get(['fecha_emision', 'monto_bruto']);

        $tramos = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '91+' => 0.0];
        foreach ($filas as $f) {
            $dias = (int) Carbon::parse($f->fecha_emision)->diffInDays($hoy);
            if ($dias <= 30) {
                $tramos['0-30'] += (float) $f->monto_bruto;
            } elseif ($dias <= 60) {
                $tramos['31-60'] += (float) $f->monto_bruto;
            } elseif ($dias <= 90) {
                $tramos['61-90'] += (float) $f->monto_bruto;
            } else {
                $tramos['91+'] += (float) $f->monto_bruto;
            }
        }

        return array_map(
            fn ($tramo, $monto) => ['tramo' => $tramo, 'monto' => round($monto, 2)],
            array_keys($tramos),
            array_values($tramos)
        );
    }

    // -------------------------------------------------------------------------
    // Proyección flujo de caja próximos 30 días
    // -------------------------------------------------------------------------

    private function flujoCaja30d(int $empresaId): array
    {
        $hoy  = Carbon::now();
        $en30 = Carbon::now()->addDays(30);

        $entradas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '>=', $hoy->toDateString())
            ->whereDate('fecha_vencimiento', '<=', $en30->toDateString())
            ->sum('monto_bruto');

        $salidas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '>=', $hoy->toDateString())
            ->whereDate('fecha_vencimiento', '<=', $en30->toDateString())
            ->sum('monto_bruto');

        return [
            'entradas_30d' => round((float) $entradas, 2),
            'salidas_30d'  => round((float) $salidas, 2),
            'neto_30d'     => round((float) $entradas - (float) $salidas, 2),
        ];
    }

    // -------------------------------------------------------------------------
    // Órdenes de compra en estados no terminales
    // -------------------------------------------------------------------------

    private function ordenesCompraPendientes(int $empresaId): array
    {
        $estados = ['BORRADOR', 'ENVIADA', 'RECIBIDA_PARCIAL'];

        $total = OrdenCompra::where('empresa_id', $empresaId)
            ->whereIn('estado', $estados)
            ->count();

        $monto = OrdenCompra::where('empresa_id', $empresaId)
            ->whereIn('estado', $estados)
            ->sum('total');

        return [
            'cantidad'    => (int) $total,
            'monto_total' => round((float) $monto, 2),
        ];
    }

    // -------------------------------------------------------------------------
    // Resumen de inventario (solo si tiene permiso inventario.productos.ver)
    // -------------------------------------------------------------------------

    private function resumenInventario(int $empresaId): array
    {
        try {
            $totalProductos = DB::table('inventario_productos')
                ->where('empresa_id', $empresaId)
                ->where('activo', 1)
                ->count();

            // Productos con stock_actual < stock_minimo (join entre stock e inventario_productos)
            $bajoMinimo = DB::table('inventario_stock as s')
                ->join('inventario_productos as p', 'p.id', '=', 's.producto_id')
                ->where('s.empresa_id', $empresaId)
                ->where('p.empresa_id', $empresaId)
                ->where('p.activo', 1)
                ->whereColumn('s.stock_actual', '<', 'p.stock_minimo')
                ->distinct('s.producto_id')
                ->count('s.producto_id');

            // Valor total del stock (suma de valor_total por empresa en inventario_stock)
            $valorStock = DB::table('inventario_stock')
                ->where('empresa_id', $empresaId)
                ->sum('valor_total');

            return [
                'total_productos' => (int) $totalProductos,
                'bajo_minimo'     => (int) $bajoMinimo,
                'valor_stock'     => round((float) $valorStock, 2),
            ];
        } catch (\Throwable) {
            return [
                'total_productos' => 0,
                'bajo_minimo'     => 0,
                'valor_stock'     => 0.0,
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Resumen RRHH (solo si tiene permiso rrhh.remuneraciones.ver)
    // -------------------------------------------------------------------------

    private function resumenRrhh(int $empresaId): array
    {
        $anio = (int) Carbon::now()->format('Y');
        $mes  = (int) Carbon::now()->format('n');

        $pendientes = Liquidacion::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('estado', [Liquidacion::ESTADO_BORRADOR, Liquidacion::ESTADO_EMITIDA])
            ->get(['liquido_a_pagar']);

        return [
            'liquidaciones_pendientes' => $pendientes->count(),
            'total_liquido_pendiente'  => round((float) $pendientes->sum('liquido_a_pagar'), 2),
            'mes_referencia'           => Carbon::now()->format('Y-m'),
        ];
    }

    // -------------------------------------------------------------------------
    // Alertas pendientes (DJ, F29 calendario, RRHH)
    // -------------------------------------------------------------------------

    private function alertasPendientes(int $empresaId, array $permisos): array
    {
        $alertas = [];
        $hoy     = Carbon::now();
        $anio    = (int) $hoy->format('Y');
        $mes     = (int) $hoy->format('n');
        $dia     = (int) $hoy->format('j');

        // Alerta DJ: solo en temporada de declaración (enero-marzo o diciembre)
        if (in_array('contabilidad.dj.ver', $permisos)) {
            $enTemporadaDj = $mes <= 3 || $mes === 12;

            if ($enTemporadaDj) {
                $codigosDj = ['1887', '1879', '1947'];

                $presentadas = DjEnvio::where('empresa_id', $empresaId)
                    ->where('anio', $anio)
                    ->whereIn('codigo_dj', $codigosDj)
                    ->where('estado', DjEnvio::ESTADO_PRESENTADO)
                    ->pluck('codigo_dj')
                    ->toArray();

                foreach ($codigosDj as $codigo) {
                    if (!in_array($codigo, $presentadas)) {
                        $alertas[] = [
                            'tipo'        => 'dj',
                            'titulo'      => "DJ {$codigo} pendiente",
                            'descripcion' => "DJ {$codigo} no presentada para {$anio}",
                            'urgencia'    => 'alta',
                        ];
                    }
                }
            }
        }

        // Alerta F29: basada en calendario, sin consulta a BD (no existe modelo F29)
        // Ventana de pago: entre día 10 y día 20 de cualquier mes
        if ($dia >= 10 && $dia <= 20) {
            $alertas[] = [
                'tipo'        => 'f29',
                'titulo'      => 'Vencimiento F29',
                'descripcion' => 'F29 del mes anterior vence el día 20',
                'urgencia'    => 'media',
            ];
        }

        // Alerta RRHH: liquidaciones pendientes de pago en el mes actual
        if (in_array('rrhh.remuneraciones.ver', $permisos)) {
            $cantidad = Liquidacion::where('empresa_id', $empresaId)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->whereIn('estado', [Liquidacion::ESTADO_BORRADOR, Liquidacion::ESTADO_EMITIDA])
                ->count();

            if ($cantidad > 0) {
                $alertas[] = [
                    'tipo'        => 'rrhh',
                    'titulo'      => 'Liquidaciones pendientes',
                    'descripcion' => "{$cantidad} liquidaciones pendientes de pago este mes",
                    'urgencia'    => 'alta',
                ];
            }
        }

        return $alertas;
    }

    // -------------------------------------------------------------------------
    // Top 5 clientes por monto facturado en los últimos 12 meses
    // -------------------------------------------------------------------------

    private function topClientes(int $empresaId): array
    {
        $desde = Carbon::now()->subMonths(12)->toDateString();

        // Para facturas de VENTA, el nombre del cliente está en proveedor.razon_social
        // (el campo proveedor_id apunta al cliente cuando tipo=VENTA)
        $filas = DB::table('facturas')
            ->where('facturas.empresa_id', $empresaId)
            ->where('facturas.tipo', 'VENTA')
            ->where('facturas.estado', '!=', 'ANULADA')
            ->whereDate('facturas.fecha_emision', '>=', $desde)
            ->whereNull('facturas.deleted_at')
            ->join('proveedores', 'proveedores.id', '=', 'facturas.proveedor_id')
            ->groupBy('facturas.proveedor_id', 'proveedores.razon_social')
            ->selectRaw('proveedores.razon_social as nombre, SUM(facturas.monto_bruto) as monto')
            ->orderByRaw('SUM(facturas.monto_bruto) DESC')
            ->limit(5)
            ->get();

        return $filas->map(function (object $f) {
            /** @var object{nombre: string, monto: string|null} $f */
            return [
                'nombre' => $f->nombre,
                'monto'  => round((float) $f->monto, 2),
            ];
        })->all();
    }

    // -------------------------------------------------------------------------
    // Top 5 facturas urgentes (pendientes de cobro, las más antiguas)
    // -------------------------------------------------------------------------

    private function facturasUrgentes(int $empresaId): array
    {
        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->orderBy('fecha_emision', 'asc')
            ->limit(5)
            ->get(['id', 'numero_factura', 'fecha_emision', 'fecha_vencimiento', 'monto_bruto', 'estado']);

        return $filas->map(fn ($f) => [
            'id'               => $f->id,
            'numero_factura'   => $f->numero_factura,
            'fecha_emision'    => $f->fecha_emision->toDateString(),
            'fecha_vencimiento'=> $f->fecha_vencimiento?->toDateString(),
            'monto_bruto'      => (float) $f->monto_bruto,
            'estado'           => $f->estado,
        ])->all();
    }

    // -------------------------------------------------------------------------
    // Helpers de rango de fechas
    // -------------------------------------------------------------------------

    private function rangoPeriodo(string $periodo): array
    {
        return match ($periodo) {
            'trimestre' => [
                Carbon::now()->startOfQuarter(),
                Carbon::now()->endOfQuarter(),
            ],
            'año' => [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfYear(),
            ],
            default => [ // 'mes'
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ],
        };
    }

    private function rangoPeriodoAnterior(string $periodo): array
    {
        return match ($periodo) {
            'trimestre' => [
                Carbon::now()->subQuarter()->startOfQuarter(),
                Carbon::now()->subQuarter()->endOfQuarter(),
            ],
            'año' => [
                Carbon::now()->subYear()->startOfYear(),
                Carbon::now()->subYear()->endOfYear(),
            ],
            default => [ // 'mes'
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ],
        };
    }
}
