import React, { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Loader2, AlertTriangle, Check, Wallet, ArrowRight } from 'lucide-react';
import { api } from '../../Configuracion/api';
import { logger } from '../../Configuracion/logger';
import { usePermisos } from '../../Contextos/Permisos';
import GraficoVentas from './componentes/GraficoVentas';
import GraficoTopClientes from './componentes/GraficoTopClientes';
import GraficoComprasVsVentas from './componentes/GraficoComprasVsVentas';
import GraficoStock from './componentes/GraficoStock';
import AlertasPendientes from './componentes/AlertasPendientes';
import RhrrhResumen from './componentes/RhrrhResumen';
import ExportacionDashboard from './componentes/ExportacionDashboard';
import GraficoFlujoCaja from './componentes/GraficoFlujoCaja';
import GraficoAgingAR from './componentes/GraficoAgingAR';
import GraficoAgingAP from './componentes/GraficoAgingAP';
import GraficoOrdenesPendientes from './componentes/GraficoOrdenesPendientes';
import GraficoMargenBruto from './componentes/GraficoMargenBruto';
import GraficoDistribucionFacturas from './componentes/GraficoDistribucionFacturas';
import GraficoPipelineCotizaciones from './componentes/GraficoPipelineCotizaciones';
import GraficoClientesNuevos from './componentes/GraficoClientesNuevos';
import TablaProximasVencer from './componentes/TablaProximasVencer';

import { formatearMoneda } from '../../Utilidades/formato';
const formatMoneda = formatearMoneda;

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha + 'T00:00:00').toLocaleDateString('es-CL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const PERIODOS = [
    { valor: 'mes', etiqueta: 'Este mes' },
    { valor: 'trimestre', etiqueta: 'Trimestre' },
    { valor: 'año', etiqueta: 'Este año' },
];

const BadgeVariacion = ({ pct, etiqueta }) => {
    if (pct === null || pct === undefined) return null;

    if (pct > 0) {
        return (
            <span className="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                <i className="fas fa-arrow-up text-[10px]" />
                +{pct.toFixed(1)}%{etiqueta ? ` ${etiqueta}` : ''}
            </span>
        );
    }
    if (pct < 0) {
        return (
            <span className="inline-flex items-center gap-1 text-xs font-bold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full">
                <i className="fas fa-arrow-down text-[10px]" />
                {pct.toFixed(1)}%{etiqueta ? ` ${etiqueta}` : ''}
            </span>
        );
    }
    return (
        <span className="inline-flex items-center gap-1 text-xs font-bold text-slate-500 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-full">
            Sin variación{etiqueta ? ` ${etiqueta}` : ''}
        </span>
    );
};

const TarjetaKpi = ({ icono, etiqueta, valor, colorIcono = 'text-slate-400', bgIcono = 'bg-slate-100', children }) => (
    <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 flex flex-col gap-3 hover:shadow-md transition-shadow">
        <div className="flex items-center justify-between">
            <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">{etiqueta}</p>
            <div className={`${bgIcono} ${colorIcono} w-9 h-9 rounded-xl flex items-center justify-center`}>
                <i className={`${icono} text-sm`} />
            </div>
        </div>
        <p className="text-3xl font-black text-slate-800 dark:text-slate-200 truncate" title={String(valor)}>
            {valor}
        </p>
        {children && <div className="mt-1">{children}</div>}
    </div>
);

const Dashboard = () => {
    const [periodo, setPeriodo] = useState('mes');
    const [fechaDesde, setFechaDesde] = useState('');
    const [fechaHasta, setFechaHasta] = useState('');
    const [compararAnioAnterior, setCompararAnioAnterior] = useState(false);
    const [resumen, setResumen] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);

    const { tieneAlgunPermiso } = usePermisos();
    const tieneInventario = tieneAlgunPermiso(['inventario.productos.ver']);
    const tieneRrhh = tieneAlgunPermiso(['rrhh.remuneraciones.ver']);
    const navigate = useNavigate();

    const rangoCustomActivo = Boolean(fechaDesde && fechaHasta);

    const cargarResumen = useCallback(async () => {
        setCargando(true);
        setError(null);
        try {
            const params = new URLSearchParams();
            if (rangoCustomActivo) {
                params.set('fecha_desde', fechaDesde);
                params.set('fecha_hasta', fechaHasta);
            } else {
                params.set('periodo', periodo);
            }
            if (compararAnioAnterior) {
                params.set('comparar_anio_anterior', '1');
            }

            const res = await api.get(`/dashboard/resumen?${params.toString()}`);
            if (res.success) {
                setResumen(res.data);
            } else {
                throw new Error('Respuesta inesperada del servidor');
            }
        } catch (err) {
            logger.error('Error al cargar resumen del dashboard:', err);
            setError(err?.message ?? 'Error al conectar con el servidor');
        } finally {
            setCargando(false);
        }
    }, [periodo, fechaDesde, fechaHasta, rangoCustomActivo, compararAnioAnterior]);

    useEffect(() => {
        cargarResumen();
    }, [cargarResumen]);

    const seleccionarPeriodoPreset = (valor) => {
        setFechaDesde('');
        setFechaHasta('');
        setPeriodo(valor);
    };

    const irAClienteFicha = (clienteId) => navigate(`/clientes/visor/${clienteId}`);
    const irAFacturasPorEstado = () => navigate('/facturas/historial');

    if (cargando) {
        return (
            <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-slate-500">
                <Loader2 size={40} strokeWidth={1.75} className="animate-spin text-emerald-500" />
                <span className="text-sm font-medium">Calculando métricas del período…</span>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4">
                <div className="bg-red-50 text-red-600 rounded-2xl p-8 text-center max-w-sm">
                    <i className="fas fa-exclamation-circle text-3xl mb-3 block" />
                    <p className="font-bold text-lg mb-1">No se pudo cargar el dashboard</p>
                    <p className="text-sm text-red-500 mb-4">{error}</p>
                    <button
                        onClick={cargarResumen}
                        className="bg-red-600 text-white text-sm font-bold px-5 py-2 rounded-xl hover:bg-red-700 transition-colors"
                    >
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    const kpis = resumen?.kpis ?? {};
    const facturasUrgentes = resumen?.facturas_urgentes ?? [];

    return (
        <div className="w-full max-w-full xl:max-w-[90rem] mx-auto px-2 sm:px-4 py-4 md:py-6 lg:py-8 font-sans text-slate-800 dark:text-slate-200 pb-12">

            <div className="mb-8 flex flex-col gap-4">
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <h1 className="text-3xl md:text-4xl font-black text-slate-900 dark:text-slate-100">Resumen Ejecutivo</h1>
                        <p className="text-slate-500 text-base mt-2">Radiografía financiera y operativa en tiempo real.</p>
                    </div>

                    <div className="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1 self-start sm:self-auto">
                        {PERIODOS.map(({ valor, etiqueta }) => (
                            <button
                                key={valor}
                                onClick={() => seleccionarPeriodoPreset(valor)}
                                className={`text-sm font-bold px-4 py-2 rounded-lg transition-colors whitespace-nowrap ${
                                    !rangoCustomActivo && periodo === valor
                                        ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                                }`}
                            >
                                {etiqueta}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex flex-col sm:flex-row sm:items-center gap-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3">
                    <div className="flex items-center gap-2">
                        <label htmlFor="dashboard-fecha-desde" className="text-xs font-bold text-slate-500 uppercase tracking-wide">
                            Desde
                        </label>
                        <input
                            id="dashboard-fecha-desde"
                            type="date"
                            value={fechaDesde}
                            onChange={(e) => setFechaDesde(e.target.value)}
                            max={fechaHasta || undefined}
                            className="text-sm border border-slate-200 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-2 py-1.5 text-slate-700 dark:text-slate-300"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <label htmlFor="dashboard-fecha-hasta" className="text-xs font-bold text-slate-500 uppercase tracking-wide">
                            Hasta
                        </label>
                        <input
                            id="dashboard-fecha-hasta"
                            type="date"
                            value={fechaHasta}
                            onChange={(e) => setFechaHasta(e.target.value)}
                            min={fechaDesde || undefined}
                            className="text-sm border border-slate-200 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-2 py-1.5 text-slate-700 dark:text-slate-300"
                        />
                    </div>
                    {rangoCustomActivo && (
                        <button
                            onClick={() => { setFechaDesde(''); setFechaHasta(''); }}
                            className="text-xs font-bold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 underline"
                        >
                            Limpiar rango custom
                        </button>
                    )}

                    <label className="flex items-center gap-2 sm:ml-auto cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={compararAnioAnterior}
                            onChange={(e) => setCompararAnioAnterior(e.target.checked)}
                            className="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span className="text-sm font-bold text-slate-600 dark:text-slate-300">Comparar con año anterior</span>
                    </label>
                </div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-6 mb-8">

                <TarjetaKpi
                    icono="fas fa-chart-line"
                    etiqueta="Ventas del período"
                    valor={formatMoneda(kpis.ventas_mes)}
                    colorIcono="text-emerald-600"
                    bgIcono="bg-emerald-50"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <BadgeVariacion pct={kpis.variacion_pct} />
                        {resumen?.comparacion_anio_anterior && (
                            <BadgeVariacion
                                pct={resumen.comparacion_anio_anterior.variacion_pct}
                                etiqueta="vs año anterior"
                            />
                        )}
                    </div>
                    {resumen?.comparacion_anio_anterior && (
                        <p className="text-xs text-slate-400 mt-1">
                            Año anterior: {formatMoneda(resumen.comparacion_anio_anterior.ventas)}
                        </p>
                    )}
                </TarjetaKpi>

                <TarjetaKpi
                    icono="fas fa-file-invoice"
                    etiqueta="Facturas emitidas"
                    valor={kpis.facturas_emitidas_mes ?? 0}
                    colorIcono="text-blue-600"
                    bgIcono="bg-blue-50"
                />

                <TarjetaKpi
                    icono="fas fa-clock"
                    etiqueta="Facturas pendientes"
                    valor={kpis.facturas_pendientes ?? 0}
                    colorIcono={(kpis.facturas_pendientes ?? 0) > 0 ? 'text-amber-600' : 'text-slate-400'}
                    bgIcono={(kpis.facturas_pendientes ?? 0) > 0 ? 'bg-amber-50' : 'bg-slate-100'}
                />

                <TarjetaKpi
                    icono="fas fa-users"
                    etiqueta="Clientes activos"
                    valor={kpis.clientes_activos ?? 0}
                    colorIcono="text-purple-600"
                    bgIcono="bg-purple-50"
                />

                <TarjetaKpi
                    icono="fas fa-file-contract"
                    etiqueta="Cotizaciones pendientes"
                    valor={kpis.cotizaciones_pendientes ?? 0}
                    colorIcono="text-indigo-600"
                    bgIcono="bg-indigo-50"
                />

                {resumen?.dso !== null && resumen?.dso !== undefined && (
                    <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 flex flex-col gap-3 hover:shadow-md transition-shadow">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">DSO</p>
                            <div className="bg-purple-50 text-purple-600 w-9 h-9 rounded-xl flex items-center justify-center">
                                <i className="fas fa-calendar-check text-sm" />
                            </div>
                        </div>
                        <p className="text-3xl font-black text-purple-600 dark:text-purple-400">{resumen.dso}d</p>
                        <p className="text-xs text-slate-400">días promedio cobro</p>
                    </div>
                )}

                <div className="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-6 flex flex-col justify-between shadow-sm">
                    <p className="text-xs font-bold text-emerald-100 uppercase tracking-widest">Acciones rápidas</p>
                    <div className="flex flex-col gap-2 mt-3">
                        <Link to="/cotizaciones" className="text-sm font-bold text-white hover:text-emerald-100 flex items-center gap-2 transition-colors">
                            <i className="fas fa-plus-circle text-emerald-200" /> Nueva cotización
                        </Link>
                        <Link to="/facturas/historial" className="text-sm font-bold text-white hover:text-emerald-100 flex items-center gap-2 transition-colors">
                            <i className="fas fa-file-invoice text-emerald-200" /> Registrar factura
                        </Link>
                        <Link to="/clientes" className="text-sm font-bold text-white hover:text-emerald-100 flex items-center gap-2 transition-colors">
                            <i className="fas fa-users text-emerald-200" /> Directorio clientes
                        </Link>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                    <h3 className="text-base font-bold text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                        <i className="fas fa-chart-area text-emerald-500" />
                        Evolución de ventas — últimos 12 meses
                    </h3>
                    <GraficoVentas
                        datos={resumen?.serie_ventas_12m ?? []}
                        comparacion={compararAnioAnterior ? resumen?.comparacion_anio_anterior : null}
                    />
                </div>

                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                    <h3 className="text-base font-bold text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                        <i className="fas fa-trophy text-amber-500" />
                        Top clientes del período
                    </h3>
                    <GraficoTopClientes datos={resumen?.top_clientes ?? []} onClienteClick={irAClienteFicha} />
                </div>
            </div>

            <div className="mb-8">
                <ExportacionDashboard resumen={resumen} periodo={periodo} />
            </div>

            <AlertasPendientes alertas={resumen?.alertas_pendientes} />

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 xl:col-span-1">
                    <h3 className="text-base font-bold text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                        <i className="fas fa-chart-bar text-blue-500" />
                        Ventas vs Compras — últimos 12 meses
                    </h3>
                    <GraficoComprasVsVentas
                        ventas={resumen?.serie_ventas_12m ?? []}
                        compras={resumen?.compras_12m ?? []}
                    />
                </div>
            </div>

            <div className="mb-8">
                <GraficoMargenBruto ventas={resumen?.serie_ventas_12m} compras={resumen?.compras_12m} />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <GraficoDistribucionFacturas datos={resumen?.distribucion_facturas} onSegmentoClick={irAFacturasPorEstado} />
                <GraficoPipelineCotizaciones datos={resumen?.pipeline_cotizaciones} />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <GraficoClientesNuevos datos={resumen?.clientes_nuevos_6m} />
                <TablaProximasVencer datos={resumen?.proximas_vencer_7d} />
            </div>

            <div className="mb-8">
                <GraficoFlujoCaja datos={resumen?.flujo_caja_30d} />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
                <GraficoAgingAR datos={resumen?.aging_ar ?? []} />
                <GraficoAgingAP datos={resumen?.aging_ap ?? []} />
            </div>

            {(tieneInventario && resumen?.inventario) || resumen?.ordenes_compra_pendientes ? (
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
                    {tieneInventario && resumen?.inventario && (
                        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                            <h3 className="text-base font-bold text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                                <i className="fas fa-boxes text-teal-500" />
                                Estado de inventario
                            </h3>
                            <GraficoStock datos={resumen.inventario} />
                        </div>
                    )}
                    <GraficoOrdenesPendientes datos={resumen?.ordenes_compra_pendientes} />
                </div>
            ) : null}

            {tieneRrhh && resumen?.rrhh && (
                <div className="mb-8">
                    <RhrrhResumen datos={resumen.rrhh} />
                </div>
            )}

            <div>
                <h3 className="text-xl font-bold text-slate-800 dark:text-slate-200 mb-5 flex items-center gap-2">
                    <AlertTriangle size={24} strokeWidth={1.75} className="text-amber-500" />
                    Facturas que requieren atención
                </h3>

                <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    {facturasUrgentes.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-14 px-6 text-slate-400">
                            <div className="bg-emerald-50 text-emerald-500 p-4 rounded-full mb-4">
                                <Check size={48} strokeWidth={1.75} />
                            </div>
                            <h4 className="text-xl font-bold text-slate-800 dark:text-slate-200 mb-1">¡Todo al día!</h4>
                            <p className="text-sm text-center text-slate-500 max-w-xs">
                                No hay facturas pendientes de gestión en este período.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="min-w-full w-full text-left">
                                    <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 border-b border-slate-100 dark:border-slate-700">
                                        <tr>
                                            <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">N° Factura</th>
                                            <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Emisión</th>
                                            <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Vencimiento</th>
                                            <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Monto bruto</th>
                                            <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Estado</th>
                                            <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                        {facturasUrgentes.map((fac) => (
                                            <tr key={fac.id} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                                <td className="px-6 py-4 font-mono text-sm font-bold text-slate-600 dark:text-slate-400">
                                                    {fac.numero_factura ?? `#${fac.id}`}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                                    {formatFecha(fac.fecha_emision)}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                                    {formatFecha(fac.fecha_vencimiento)}
                                                </td>
                                                <td className="px-6 py-4 text-base font-black text-slate-900 dark:text-slate-100 text-right whitespace-nowrap">
                                                    {formatMoneda(fac.monto_bruto)}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="inline-block text-xs font-bold px-3 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                                        {fac.estado ?? 'REGISTRADA'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Link
                                                        to="/facturas/historial"
                                                        className="inline-flex items-center gap-1.5 text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-4 py-2 rounded-lg border border-emerald-200 transition-colors"
                                                    >
                                                        <Wallet size={14} strokeWidth={1.75} />
                                                        Gestionar
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="bg-slate-50 dark:bg-slate-900 p-4 text-center border-t border-slate-200 dark:border-slate-700">
                                <Link
                                    to="/facturas/historial"
                                    className="text-sm font-bold text-slate-500 dark:text-slate-400 hover:text-blue-600 transition-colors inline-flex items-center gap-2"
                                >
                                    Ver historial completo de facturas
                                    <ArrowRight size={16} strokeWidth={1.75} />
                                </Link>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};

export default Dashboard;
