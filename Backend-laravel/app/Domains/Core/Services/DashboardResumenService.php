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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardResumenService
{
    // TTL corto en vez de invalidación activa: no hay un hook único de "algo cambió" que cubra facturas/pagos/inventario/rrhh a la vez.
    private const TTL_SEGUNDOS = 90;

    /**
     * Resumen de KPIs, serie de ventas, top clientes, facturas urgentes y secciones gateadas por permiso.
     *
     * @param  int  $empresaId  ID de la empresa activa del usuario
     * @param  string  $periodo  'mes' | 'trimestre' | 'año' (ignorado si vienen fecha_desde/fecha_hasta)
     * @param  array  $permisos  Lista de permisos efectivos del usuario (ModuloPermisos::permisosUsuario)
     * @param  string|null  $fechaDesde  Fecha explícita (Y-m-d), tiene prioridad sobre $periodo si viene junto a $fechaHasta
     * @param  string|null  $fechaHasta  Fecha explícita (Y-m-d)
     * @param  bool  $compararAnioAnterior  Si es true, agrega la clave 'comparacion_anio_anterior' con el periodo equivalente del año anterior
     */
    public function obtener(
        int $empresaId,
        string $periodo = 'mes',
        array $permisos = [],
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $compararAnioAnterior = false
    ): array {
        // La clave incluye empresa_id (aislamiento multitenant obligatorio) + periodo/fechas + comparación + los permisos que efectivamente cambian el resultado.
        $permisosRelevantes = array_values(array_intersect($permisos, [
            'inventario.productos.ver',
            'rrhh.remuneraciones.ver',
            'contabilidad.dj.ver',
        ]));
        sort($permisosRelevantes);
        $claveCache = sprintf(
            'dashboard_resumen:empresa_%d:periodo_%s:desde_%s:hasta_%s:cmp_%s:permisos_%s',
            $empresaId,
            $periodo,
            $fechaDesde ?? '-',
            $fechaHasta ?? '-',
            $compararAnioAnterior ? '1' : '0',
            md5(implode(',', $permisosRelevantes))
        );

        return Cache::remember(
            $claveCache,
            self::TTL_SEGUNDOS,
            function () use ($empresaId, $periodo, $permisos, $fechaDesde, $fechaHasta, $compararAnioAnterior) {
                return $this->calcular($empresaId, $periodo, $permisos, $fechaDesde, $fechaHasta, $compararAnioAnterior);
            }
        );
    }

    private function calcular(
        int $empresaId,
        string $periodo,
        array $permisos,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $compararAnioAnterior = false
    ): array {
        [$inicio, $fin] = $this->rangoPeriodo($periodo, $fechaDesde, $fechaHasta);
        [$inicioAnt, $finAnt] = $this->rangoPeriodoAnterior($periodo, $fechaDesde, $fechaHasta);

        $kpis = $this->calcularKpis($empresaId, $inicio, $fin, $inicioAnt, $finAnt);
        $serieVentas = $this->serieVentas12m($empresaId);
        $topClientes = $this->topClientes($empresaId);
        $urgentes = $this->facturasUrgentes($empresaId);

        $resultado = [
            'kpis' => $kpis,
            'serie_ventas_12m' => $serieVentas,
            'top_clientes' => $topClientes,
            'facturas_urgentes' => $urgentes,
            'compras_12m' => $this->serieCompras12m($empresaId),
        ];

        if ($compararAnioAnterior) {
            $resultado['comparacion_anio_anterior'] = $this->compararAnioAnterior($empresaId, $inicio, $fin, $kpis);
        }

        if (in_array('inventario.productos.ver', $permisos)) {
            $resultado['inventario'] = $this->resumenInventario($empresaId);
        }

        if (in_array('rrhh.remuneraciones.ver', $permisos)) {
            $resultado['rrhh'] = $this->resumenRrhh($empresaId);
        }

        $resultado['alertas_pendientes'] = $this->alertasPendientes($empresaId, $permisos);
        $resultado['aging_ar'] = $this->agingAR($empresaId);
        $resultado['aging_ap'] = $this->agingAP($empresaId);
        $resultado['flujo_caja_30d'] = $this->flujoCaja30d($empresaId);
        $resultado['ordenes_compra_pendientes'] = $this->ordenesCompraPendientes($empresaId);
        $resultado['distribucion_facturas'] = $this->distribucionFacturas($empresaId);
        $resultado['pipeline_cotizaciones'] = $this->pipelineCotizaciones($empresaId);
        $resultado['clientes_nuevos_6m'] = $this->clientesNuevos6m($empresaId);
        $resultado['dso'] = $this->dso($empresaId);
        $resultado['proximas_vencer_7d'] = $this->proximasVencer($empresaId, 7);

        return $resultado;
    }

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
            'ventas_mes' => (float) $ventasMes,
            'ventas_mes_anterior' => (float) $ventasMesAnterior,
            'variacion_pct' => $variacionPct,
            'facturas_emitidas_mes' => $facturasEmitidasMes,
            'facturas_pendientes' => $facturasPendientes,
            'clientes_activos' => $clientesActivos,
            'cotizaciones_pendientes' => $cotizacionesPendientes,
        ];
    }

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

    private function flujoCaja30d(int $empresaId): array
    {
        $hoy = Carbon::now();
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
            'salidas_30d' => round((float) $salidas, 2),
            'neto_30d' => round((float) $entradas - (float) $salidas, 2),
        ];
    }

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
            'cantidad' => (int) $total,
            'monto_total' => round((float) $monto, 2),
        ];
    }

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
                'bajo_minimo' => (int) $bajoMinimo,
                'valor_stock' => round((float) $valorStock, 2),
            ];
        } catch (\Throwable) {
            return [
                'total_productos' => 0,
                'bajo_minimo' => 0,
                'valor_stock' => 0.0,
            ];
        }
    }

    private function resumenRrhh(int $empresaId): array
    {
        $anio = (int) Carbon::now()->format('Y');
        $mes = (int) Carbon::now()->format('n');

        $pendientes = Liquidacion::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('estado', [Liquidacion::ESTADO_BORRADOR, Liquidacion::ESTADO_EMITIDA])
            ->get(['liquido_a_pagar']);

        return [
            'liquidaciones_pendientes' => $pendientes->count(),
            'total_liquido_pendiente' => round((float) $pendientes->sum('liquido_a_pagar'), 2),
            'mes_referencia' => Carbon::now()->format('Y-m'),
        ];
    }

    private function alertasPendientes(int $empresaId, array $permisos): array
    {
        $alertas = [];
        $hoy = Carbon::now();
        $anio = (int) $hoy->format('Y');
        $mes = (int) $hoy->format('n');
        $dia = (int) $hoy->format('j');

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
                    if (! in_array($codigo, $presentadas)) {
                        $alertas[] = [
                            'tipo' => 'dj',
                            'titulo' => "DJ {$codigo} pendiente",
                            'descripcion' => "DJ {$codigo} no presentada para {$anio}",
                            'urgencia' => 'alta',
                        ];
                    }
                }
            }
        }

        // Alerta F29 por calendario (no hay modelo F29): ventana de pago entre día 10 y 20
        if ($dia >= 10 && $dia <= 20) {
            $alertas[] = [
                'tipo' => 'f29',
                'titulo' => 'Vencimiento F29',
                'descripcion' => 'F29 del mes anterior vence el día 20',
                'urgencia' => 'media',
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
                    'tipo' => 'rrhh',
                    'titulo' => 'Liquidaciones pendientes',
                    'descripcion' => "{$cantidad} liquidaciones pendientes de pago este mes",
                    'urgencia' => 'alta',
                ];
            }
        }

        return $alertas;
    }

    private function topClientes(int $empresaId): array
    {
        $desde = Carbon::now()->subMonths(12)->toDateString();

        // Para facturas VENTA, el nombre del cliente está en proveedor.razon_social (proveedor_id apunta al cliente cuando tipo=VENTA).
        $filas = DB::table('facturas')
            ->where('facturas.empresa_id', $empresaId)
            ->where('facturas.tipo', 'VENTA')
            ->where('facturas.estado', '!=', 'ANULADA')
            ->whereDate('facturas.fecha_emision', '>=', $desde)
            ->whereNull('facturas.deleted_at')
            ->join('proveedores', 'proveedores.id', '=', 'facturas.proveedor_id')
            ->groupBy('facturas.proveedor_id', 'proveedores.razon_social')
            ->selectRaw('proveedores.id as id, proveedores.razon_social as nombre, SUM(facturas.monto_bruto) as monto')
            ->orderByRaw('SUM(facturas.monto_bruto) DESC')
            ->limit(5)
            ->get();

        return $filas->map(function (object $f) {
            /** @var object{id: int, nombre: string, monto: string|null} $f */
            return [
                'id' => (int) $f->id,
                'nombre' => $f->nombre,
                'monto' => round((float) $f->monto, 2),
            ];
        })->all();
    }

    private function facturasUrgentes(int $empresaId): array
    {
        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->orderBy('fecha_emision', 'asc')
            ->limit(5)
            ->get(['id', 'numero_factura', 'fecha_emision', 'fecha_vencimiento', 'monto_bruto', 'estado']);

        return $filas->map(fn ($f) => [
            'id' => $f->id,
            'numero_factura' => $f->numero_factura,
            'fecha_emision' => $f->fecha_emision->toDateString(),
            'fecha_vencimiento' => $f->fecha_vencimiento?->toDateString(),
            'monto_bruto' => (float) $f->monto_bruto,
            'estado' => $f->estado,
        ])->all();
    }

    private function distribucionFacturas(int $empresaId): array
    {
        $hoy = Carbon::now()->toDateString();

        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->get(['estado', 'fecha_vencimiento', 'monto_bruto']);

        $dist = [
            'pagadas' => ['cantidad' => 0, 'monto' => 0.0],
            'pendientes' => ['cantidad' => 0, 'monto' => 0.0],
            'vencidas' => ['cantidad' => 0, 'monto' => 0.0],
            'anuladas' => ['cantidad' => 0, 'monto' => 0.0],
        ];

        foreach ($filas as $f) {
            $monto = (float) $f->monto_bruto;
            if ($f->estado === 'PAGADA') {
                $dist['pagadas']['cantidad']++;
                $dist['pagadas']['monto'] += $monto;
            } elseif ($f->estado === 'ANULADA') {
                $dist['anuladas']['cantidad']++;
                $dist['anuladas']['monto'] += $monto;
            } elseif ($f->fecha_vencimiento !== null && Carbon::parse($f->fecha_vencimiento)->toDateString() < $hoy) {
                $dist['vencidas']['cantidad']++;
                $dist['vencidas']['monto'] += $monto;
            } else {
                $dist['pendientes']['cantidad']++;
                $dist['pendientes']['monto'] += $monto;
            }
        }

        foreach ($dist as $k => $v) {
            $dist[$k]['monto'] = round($v['monto'], 2);
        }

        return $dist;
    }

    private function pipelineCotizaciones(int $empresaId): array
    {
        $desde = Carbon::now()->startOfMonth()->subMonths(11)->toDateString();

        $estados = EstadoCotizacion::all(['id', 'nombre']);
        $pipeline = [];

        foreach ($estados as $estado) {
            $count = Cotizacion::where('empresa_id', $empresaId)
                ->where('estado_id', $estado->id)
                ->whereDate('fecha_emision', '>=', $desde)
                ->count();
            $monto = (float) Cotizacion::where('empresa_id', $empresaId)
                ->where('estado_id', $estado->id)
                ->whereDate('fecha_emision', '>=', $desde)
                ->sum('monto_total');
            $pipeline[] = [
                'estado' => $estado->nombre,
                'cantidad' => $count,
                'monto' => round($monto, 2),
            ];
        }

        // Tasa de conversión: Aceptadas / (Aceptadas + Rechazadas + Expiradas)
        $aceptadas = collect($pipeline)->firstWhere('estado', 'Aceptada')['cantidad'] ?? 0;
        $rechazadas = collect($pipeline)->firstWhere('estado', 'Rechazada')['cantidad'] ?? 0;
        $expiradas = collect($pipeline)->firstWhere('estado', 'Expirada')['cantidad'] ?? 0;
        $denominador = $aceptadas + $rechazadas + $expiradas;
        $tasaConversion = $denominador > 0 ? round($aceptadas / $denominador * 100, 1) : null;

        return [
            'etapas' => $pipeline,
            'tasa_conversion' => $tasaConversion,
        ];
    }

    private function clientesNuevos6m(int $empresaId): array
    {
        $desde = Carbon::now()->startOfMonth()->subMonths(5)->toDateString();

        $filas = Cliente::where('empresa_id', $empresaId)
            ->whereDate('created_at', '>=', $desde)
            ->get(['created_at']);

        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $clave = Carbon::now()->startOfMonth()->subMonths($i)->format('Y-m');
            $meses[$clave] = 0;
        }

        foreach ($filas as $cliente) {
            $clave = Carbon::parse($cliente->created_at)->format('Y-m');
            if (isset($meses[$clave])) {
                $meses[$clave]++;
            }
        }

        return array_map(
            fn ($mes, $count) => ['mes' => $mes, 'cantidad' => $count],
            array_keys($meses),
            array_values($meses)
        );
    }

    private function dso(int $empresaId): ?float
    {
        $desde = Carbon::now()->startOfMonth()->subMonths(11)->toDateString();

        $ventas12m = (float) Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->where('estado', '!=', 'ANULADA')
            ->whereDate('fecha_emision', '>=', $desde)
            ->sum('monto_bruto');

        if ($ventas12m <= 0) {
            return null;
        }

        $cuentasCobrar = (float) Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->sum('monto_bruto');

        return round(($cuentasCobrar / $ventas12m) * 365, 1);
    }

    private function proximasVencer(int $empresaId, int $dias = 7): array
    {
        $hoy = Carbon::now()->toDateString();
        $limite = Carbon::now()->addDays($dias)->toDateString();

        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->whereDate('fecha_vencimiento', '<=', $limite)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get(['id', 'numero_factura', 'fecha_emision', 'fecha_vencimiento', 'monto_bruto', 'estado']);

        return $filas->map(fn ($f) => [
            'id' => $f->id,
            'numero_factura' => $f->numero_factura,
            'fecha_vencimiento' => optional($f->fecha_vencimiento)->toDateString(),
            'monto_bruto' => round((float) $f->monto_bruto, 2),
            'estado' => $f->estado,
            'dias_restantes' => (int) Carbon::now()->diffInDays($f->fecha_vencimiento, false),
        ])->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangoPeriodo(string $periodo, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        if ($fechaDesde !== null && $fechaHasta !== null) {
            return [
                Carbon::parse($fechaDesde)->startOfDay(),
                Carbon::parse($fechaHasta)->endOfDay(),
            ];
        }

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

    /**
     * Rango del periodo inmediatamente anterior (misma duración), usado para 'variacion_pct'.
     * Con fechas explícitas, calcula el rango de igual duración justo antes de fecha_desde.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangoPeriodoAnterior(string $periodo, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        if ($fechaDesde !== null && $fechaHasta !== null) {
            $inicio = Carbon::parse($fechaDesde)->startOfDay();
            $fin = Carbon::parse($fechaHasta)->endOfDay();
            $dias = $inicio->diffInDays($fin) + 1;

            $finAnterior = $inicio->copy()->subDay()->endOfDay();
            $inicioAnterior = $finAnterior->copy()->subDays($dias - 1)->startOfDay();

            return [$inicioAnterior, $finAnterior];
        }

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

    /**
     * KPIs y serie de ventas del período equivalente del año anterior (mismo rango, un año atrás),
     * para comparación interanual. Se apoya en $kpisActuales (ya calculados) para el delta porcentual.
     */
    private function compararAnioAnterior(int $empresaId, Carbon $inicio, Carbon $fin, array $kpisActuales): array
    {
        $inicioAnt = $inicio->copy()->subYear();
        $finAnt = $fin->copy()->subYear();

        $ventasAnt = (float) Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->where('estado', '!=', 'ANULADA')
            ->whereDate('fecha_emision', '>=', $inicioAnt->toDateString())
            ->whereDate('fecha_emision', '<=', $finAnt->toDateString())
            ->sum('monto_bruto');

        $facturasAnt = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->where('estado', '!=', 'ANULADA')
            ->whereBetween('fecha_emision', [$inicioAnt->toDateString(), $finAnt->toDateString()])
            ->count();

        $ventasActuales = (float) ($kpisActuales['ventas_mes'] ?? 0);
        $facturasActuales = (int) ($kpisActuales['facturas_emitidas_mes'] ?? 0);

        $variacionVentasPct = $ventasAnt > 0 ? round((($ventasActuales - $ventasAnt) / $ventasAnt) * 100, 2) : null;
        $variacionFacturasPct = $facturasAnt > 0 ? round((($facturasActuales - $facturasAnt) / $facturasAnt) * 100, 2) : null;

        return [
            'ventas' => round($ventasAnt, 2),
            'variacion_pct' => $variacionVentasPct,
            'facturas_emitidas' => (int) $facturasAnt,
            'facturas_emitidas_variacion_pct' => $variacionFacturasPct,
            'serie' => $this->serieVentasComparativa($empresaId, $inicio, $fin, $inicioAnt, $finAnt),
            'periodo' => [
                'desde' => $inicioAnt->toDateString(),
                'hasta' => $finAnt->toDateString(),
            ],
        ];
    }

    /**
     * Alinea, mes a mes, la serie de ventas del período actual con la del período equivalente
     * del año anterior, para graficar ambas líneas superpuestas en el frontend.
     */
    private function serieVentasComparativa(int $empresaId, Carbon $inicio, Carbon $fin, Carbon $inicioAnt, Carbon $finAnt): array
    {
        $serieActual = $this->serieVentasEnRango($empresaId, $inicio, $fin);
        $serieAnterior = $this->serieVentasEnRango($empresaId, $inicioAnt, $finAnt);

        $total = max(count($serieActual), count($serieAnterior));
        $salida = [];
        for ($i = 0; $i < $total; $i++) {
            $salida[] = [
                'mes' => $serieActual[$i]['mes'] ?? null,
                'mes_anio_anterior' => $serieAnterior[$i]['mes'] ?? null,
                'actual' => $serieActual[$i]['monto'] ?? 0.0,
                'anio_anterior' => $serieAnterior[$i]['monto'] ?? 0.0,
            ];
        }

        return $salida;
    }

    /**
     * Serie mensual de ventas (VENTA, no ANULADA) para un rango de fechas arbitrario.
     * Agrupación en PHP (compatible SQLite y MySQL), igual patrón que serieVentas12m().
     */
    private function serieVentasEnRango(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $inicioMes = $desde->copy()->startOfMonth();
        $finMes = $hasta->copy()->startOfMonth();

        $filas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->where('estado', '!=', 'ANULADA')
            ->whereDate('fecha_emision', '>=', $desde->toDateString())
            ->whereDate('fecha_emision', '<=', $hasta->toDateString())
            ->get(['fecha_emision', 'monto_bruto']);

        $meses = [];
        $cursor = $inicioMes->copy();
        while ($cursor->lte($finMes)) {
            $meses[$cursor->format('Y-m')] = 0.0;
            $cursor->addMonth();
        }

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
}
