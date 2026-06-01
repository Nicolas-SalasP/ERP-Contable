import React, { useEffect, useMemo, useState } from 'react';
import inventarioApi from '../Servicios/inventarioApi';
import {
    AlertBox,
    EmptyState,
    EstadoBadge,
    formatCurrency,
    formatDate,
    formatNumber,
    getBodegaNombre,
    getProductoNombre,
    LoadingState,
    PageHeader,
    Panel,
    SecondaryButton,
    StatCard,
    TableShell,
    Td,
    Th,
} from '../Componentes/InventarioUI';

const asArray = (value) => (Array.isArray(value) ? value : []);

const emptyDashboard = {
    resumen: {
        productos: 0,
        productos_activos: 0,
        bodegas: 0,
        stock_total: 0,
        stock_valorizado: 0,
        valor_total_inventario: 0,
        productos_bajo_minimo: 0,
        productos_sin_stock: 0,
        productos_sin_movimiento: 0,
        lotes_vencidos: 0,
        lotes_por_vencer: 0,
        reservas_activas: 0,
        tomas_abiertas: 0,
        tomas_pendientes: 0,
        alertas_criticas: 0,
        alertas_total: 0,
        sugerencias_reposicion: 0,
        exactitud_toma_fisica: 0,
        rotacion_simple: 0,
    },
    kpis: {},
    stock_por_bodega: [],
    stock_por_lote: [],
    alertas_criticas: [],
    sugerencias_reposicion: [],
    ultimos_movimientos: [],
    ajustes_criticos_recientes: [],
    tomas_recientes: [],
    metadata: null,
};

const normalizeDashboard = (data = {}) => ({
    ...emptyDashboard,
    ...data,
    resumen: {
        ...emptyDashboard.resumen,
        ...(data.resumen || {}),
    },
    kpis: data.kpis || {},
    stock_por_bodega: asArray(data.stock_por_bodega),
    stock_por_lote: asArray(data.stock_por_lote),
    alertas_criticas: asArray(data.alertas_criticas),
    sugerencias_reposicion: asArray(data.sugerencias_reposicion),
    ultimos_movimientos: asArray(data.ultimos_movimientos),
    ajustes_criticos_recientes: asArray(data.ajustes_criticos_recientes),
    tomas_recientes: asArray(data.tomas_recientes),
    metadata: data.metadata || null,
});

const getBodegaOrigen = (movimiento) => {
    return movimiento?.bodega_origen?.nombre
        || movimiento?.bodegaOrigen?.nombre
        || movimiento?.bodega_origen_nombre
        || movimiento?.bodega?.nombre
        || (movimiento?.bodega_origen_id ? `Bodega #${movimiento.bodega_origen_id}` : '-');
};

const getBodegaDestino = (movimiento) => {
    return movimiento?.bodega_destino?.nombre
        || movimiento?.bodegaDestino?.nombre
        || movimiento?.bodega_destino_nombre
        || movimiento?.bodega?.nombre
        || (movimiento?.bodega_destino_id ? `Bodega #${movimiento.bodega_destino_id}` : '-');
};

const getLoteCodigo = (item) => item?.lote?.codigo_lote || item?.codigo_lote || item?.lote_codigo || '-';
const getTipoAjuste = (item) => item?.tipo?.nombre || item?.tipo_nombre || item?.tipo_codigo || item?.tipo_movimiento || '-';
const getFecha = (item) => item?.fecha_movimiento || item?.fecha || item?.fecha_inicio || item?.created_at || null;

const MiniMetric = ({ label, value, helper, tone = 'slate' }) => {
    const tones = {
        slate: 'bg-slate-50 border-slate-200 text-slate-700',
        emerald: 'bg-emerald-50 border-emerald-200 text-emerald-700',
        amber: 'bg-amber-50 border-amber-200 text-amber-700',
        rose: 'bg-rose-50 border-rose-200 text-rose-700',
        blue: 'bg-blue-50 border-blue-200 text-blue-700',
    };

    return (
        <div className={`rounded-2xl border p-4 ${tones[tone] || tones.slate}`}>
            <p className="text-[11px] uppercase tracking-widest font-black opacity-75">{label}</p>
            <p className="text-2xl font-black mt-1">{value}</p>
            {helper && <p className="text-xs font-bold mt-1 opacity-80">{helper}</p>}
        </div>
    );
};

const InventarioDashboard = () => {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [dashboard, setDashboard] = useState(emptyDashboard);

    const cargarDashboard = async () => {
        try {
            setLoading(true);
            setError(null);

            const response = await inventarioApi.dashboard.obtener();
            setDashboard(normalizeDashboard(response?.data || {}));
        } catch (err) {
            setError(err?.response?.data || err?.message || 'No se pudo cargar el dashboard de inventario.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDashboard();
    }, []);

    useEffect(() => {
        const handler = () => cargarDashboard();

        window.addEventListener('inventario:actualizado', handler);
        window.addEventListener('inventario:stock-critico', handler);

        return () => {
            window.removeEventListener('inventario:actualizado', handler);
            window.removeEventListener('inventario:stock-critico', handler);
        };
    }, []);

    const resumen = dashboard.resumen || emptyDashboard.resumen;

    const valorInventario = resumen.valor_total_inventario ?? resumen.stock_valorizado;
    const alertasCriticas = dashboard.alertas_criticas;
    const sugerenciasReposicion = dashboard.sugerencias_reposicion;
    const movimientos = dashboard.ultimos_movimientos;
    const ajustes = dashboard.ajustes_criticos_recientes;
    const tomas = dashboard.tomas_recientes;
    const stockPorBodega = dashboard.stock_por_bodega;
    const stockPorLote = dashboard.stock_por_lote;

    const dashboardVacio = useMemo(() => {
        return !loading
            && !error
            && Number(resumen.productos || resumen.productos_activos || 0) === 0
            && Number(resumen.bodegas || 0) === 0
            && movimientos.length === 0
            && tomas.length === 0;
    }, [error, loading, movimientos.length, resumen.bodegas, resumen.productos, resumen.productos_activos, tomas.length]);

    if (loading) {
        return <LoadingState text="Cargando dashboard gerencial de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Dashboard de Inventario"
                description="Vista gerencial del módulo: KPIs, valorización, alertas, reposición, movimientos, ajustes críticos, stock por bodega y seguimiento de tomas físicas."
                actions={(
                    <SecondaryButton type="button" onClick={cargarDashboard}>
                        <i className="fas fa-rotate-right"></i>
                        Actualizar
                    </SecondaryButton>
                )}
            />

            {error && (
                <AlertBox tone="amber">
                    {typeof error === 'string' ? error : error?.message || 'No se pudieron cargar algunos datos del dashboard.'}
                </AlertBox>
            )}

            {dashboardVacio && (
                <EmptyState
                    title="Dashboard sin datos operativos"
                    description="Cuando existan productos, stock, movimientos, reservas o tomas físicas, los KPIs se visualizarán en esta pantalla."
                    icon="fas fa-chart-line"
                />
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <StatCard
                    title="Productos activos"
                    value={formatNumber(resumen.productos_activos ?? resumen.productos)}
                    helper="Catálogo operativo"
                    icon="fas fa-box"
                    tone="emerald"
                />

                <StatCard
                    title="Bodegas"
                    value={formatNumber(resumen.bodegas)}
                    helper="Ubicaciones activas"
                    icon="fas fa-warehouse"
                    tone="blue"
                />

                <StatCard
                    title="Stock total"
                    value={formatNumber(resumen.stock_total, 2)}
                    helper="Unidades consolidadas"
                    icon="fas fa-layer-group"
                    tone="indigo"
                />

                <StatCard
                    title="Valor inventario"
                    value={formatCurrency(valorInventario)}
                    helper="Valorización consolidada"
                    icon="fas fa-dollar-sign"
                    tone="emerald"
                />

                <StatCard
                    title="Bajo mínimo"
                    value={formatNumber(resumen.productos_bajo_minimo)}
                    helper={`${formatNumber(resumen.porcentaje_productos_bajo_minimo, 2)}% del catálogo`}
                    icon="fas fa-triangle-exclamation"
                    tone="amber"
                />

                <StatCard
                    title="Sin stock"
                    value={formatNumber(resumen.productos_sin_stock)}
                    helper={`${formatNumber(resumen.porcentaje_productos_sin_stock, 2)}% del catálogo`}
                    icon="fas fa-ban"
                    tone="rose"
                />

                <StatCard
                    title="Lotes vencidos"
                    value={formatNumber(resumen.lotes_vencidos)}
                    helper={`${formatNumber(resumen.lotes_por_vencer)} por vencer`}
                    icon="fas fa-hourglass-end"
                    tone="rose"
                />

                <StatCard
                    title="Alertas críticas"
                    value={formatNumber(resumen.alertas_criticas)}
                    helper={`${formatNumber(resumen.alertas_total)} alertas totales`}
                    icon="fas fa-bell"
                    tone="amber"
                />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-4 gap-5">
                <MiniMetric
                    label="Reservas activas"
                    value={formatNumber(resumen.reservas_activas)}
                    helper="Stock comprometido"
                    tone="blue"
                />
                <MiniMetric
                    label="Tomas pendientes"
                    value={formatNumber(resumen.tomas_pendientes ?? resumen.tomas_abiertas)}
                    helper="Inventario físico abierto"
                    tone="amber"
                />
                <MiniMetric
                    label="Sin movimiento"
                    value={formatNumber(resumen.productos_sin_movimiento)}
                    helper="Potencial obsolescencia"
                    tone="slate"
                />
                <MiniMetric
                    label="Exactitud toma física"
                    value={`${formatNumber(resumen.exactitud_toma_fisica, 2)}%`}
                    helper={`Rotación simple ${formatNumber(resumen.rotacion_simple, 4)}`}
                    tone="emerald"
                />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <Panel
                    title="Stock por bodega"
                    subtitle="Distribución valorizada por ubicación"
                    className="xl:col-span-2"
                >
                    {stockPorBodega.length === 0 ? (
                        <EmptyState
                            title="Sin stock por bodega"
                            description="No hay stock valorizado por bodega para mostrar."
                            icon="fas fa-warehouse"
                        />
                    ) : (
                        <TableShell>
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Bodega</Th>
                                    <Th align="right">Stock</Th>
                                    <Th align="right">Valor</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {stockPorBodega.map((item) => (
                                    <tr key={item.bodega_id || item.bodega_codigo} className="hover:bg-slate-50/70 transition-colors">
                                        <Td className="font-black text-slate-800">
                                            {item.bodega_nombre || item.nombre_bodega || `Bodega #${item.bodega_id ?? '-'}`}
                                            {item.bodega_codigo && <span className="block text-xs text-slate-400 font-bold">{item.bodega_codigo}</span>}
                                        </Td>
                                        <Td align="right" className="font-black text-slate-700">{formatNumber(item.stock_total, 2)}</Td>
                                        <Td align="right" className="font-black text-emerald-700">{formatCurrency(item.valor_total)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </TableShell>
                    )}
                </Panel>

                <Panel
                    title="Lotes con stock"
                    subtitle="Top de lotes vigentes por cantidad"
                >
                    {stockPorLote.length === 0 ? (
                        <EmptyState
                            title="Sin stock por lote"
                            description="No hay lotes con stock actual para mostrar."
                            icon="fas fa-boxes-packing"
                        />
                    ) : (
                        <div className="space-y-3">
                            {stockPorLote.slice(0, 6).map((item) => (
                                <div key={`${item.lote_id}-${item.producto_id}-${item.bodega_id}`} className="rounded-2xl border border-slate-100 p-4 bg-slate-50/60">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-black text-slate-800">{getLoteCodigo(item)}</p>
                                            <p className="text-xs text-slate-500 font-bold mt-1">{getProductoNombre(item)}</p>
                                            <p className="text-xs text-slate-400 font-semibold">{getBodegaNombre(item)}</p>
                                        </div>
                                        <span className="text-sm font-black text-emerald-700">{formatNumber(item.stock_actual, 2)}</span>
                                    </div>
                                    <p className="text-[11px] text-slate-400 font-bold mt-2">
                                        Vence: {formatDate(item.fecha_vencimiento)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <Panel
                    title="Alertas críticas"
                    subtitle="Riesgos operativos priorizados"
                >
                    {alertasCriticas.length === 0 ? (
                        <EmptyState
                            title="Sin alertas críticas"
                            description="No hay alertas críticas vigentes para el módulo."
                            icon="fas fa-shield-check"
                        />
                    ) : (
                        <div className="space-y-3">
                            {alertasCriticas.map((alerta, index) => (
                                <div key={alerta.referencia || `${alerta.tipo}-${index}`} className="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                                    <div className="flex flex-wrap items-center gap-2 mb-2">
                                        <EstadoBadge value={alerta.severidad || alerta.tipo} />
                                        <span className="text-[11px] font-black text-rose-700 uppercase tracking-wider">
                                            {alerta.tipo || 'ALERTA'}
                                        </span>
                                    </div>
                                    <p className="font-black text-slate-800">{alerta.titulo || alerta.descripcion || 'Alerta de inventario'}</p>
                                    {alerta.descripcion && <p className="text-sm text-slate-600 font-medium mt-1">{alerta.descripcion}</p>}
                                    <p className="text-xs text-slate-500 font-bold mt-2">
                                        {alerta.producto_nombre || '-'} · {alerta.bodega_nombre || '-'}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>

                <Panel
                    title="Sugerencias de reposición"
                    subtitle="Reposición calculada por reglas operativas"
                >
                    {sugerenciasReposicion.length === 0 ? (
                        <EmptyState
                            title="Sin sugerencias"
                            description="No hay sugerencias de reposición activas."
                            icon="fas fa-cart-flatbed"
                        />
                    ) : (
                        <TableShell>
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Producto</Th>
                                    <Th>Bodega</Th>
                                    <Th align="right">Actual</Th>
                                    <Th align="right">Sugerida</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {sugerenciasReposicion.map((item, index) => (
                                    <tr key={`${item.producto_id}-${item.bodega_id}-${index}`} className="hover:bg-slate-50/70 transition-colors">
                                        <Td className="font-bold text-slate-700">{getProductoNombre(item)}</Td>
                                        <Td className="text-slate-500 font-semibold">{getBodegaNombre(item)}</Td>
                                        <Td align="right" className="font-black text-slate-700">{formatNumber(item.stock_actual, 2)}</Td>
                                        <Td align="right" className="font-black text-emerald-700">{formatNumber(item.cantidad_sugerida, 2)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </TableShell>
                    )}
                </Panel>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <Panel
                    title="Últimos movimientos"
                    subtitle="Actividad reciente del Kardex"
                >
                    {movimientos.length === 0 ? (
                        <EmptyState
                            title="Sin movimientos recientes"
                            description="Registra entradas, salidas o ajustes para ver actividad aquí."
                            icon="fas fa-arrows-rotate"
                        />
                    ) : (
                        <TableShell>
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Tipo</Th>
                                    <Th>Producto</Th>
                                    <Th>Bodega</Th>
                                    <Th align="right">Cantidad</Th>
                                    <Th>Fecha</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {movimientos.map((movimiento) => (
                                    <tr key={movimiento.id} className="hover:bg-slate-50/70 transition-colors">
                                        <Td><EstadoBadge value={movimiento.tipo} /></Td>
                                        <Td className="font-bold text-slate-700">{getProductoNombre(movimiento)}</Td>
                                        <Td className="text-slate-500 font-semibold">
                                            {getBodegaDestino(movimiento) !== '-' ? getBodegaDestino(movimiento) : getBodegaOrigen(movimiento)}
                                        </Td>
                                        <Td align="right" className="font-black text-slate-800">{formatNumber(movimiento.cantidad, 2)}</Td>
                                        <Td className="text-slate-500">{formatDate(movimiento.fecha_movimiento)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </TableShell>
                    )}
                </Panel>

                <Panel
                    title="Ajustes críticos recientes"
                    subtitle="Ajustes con impacto operativo y trazabilidad"
                >
                    {ajustes.length === 0 ? (
                        <EmptyState
                            title="Sin ajustes críticos"
                            description="No hay ajustes críticos recientes para revisar."
                            icon="fas fa-screwdriver-wrench"
                        />
                    ) : (
                        <TableShell>
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Tipo</Th>
                                    <Th>Producto</Th>
                                    <Th>Bodega</Th>
                                    <Th align="right">Cantidad</Th>
                                    <Th align="right">Costo</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {ajustes.map((ajuste) => (
                                    <tr key={ajuste.id} className="hover:bg-slate-50/70 transition-colors">
                                        <Td className="font-bold text-slate-700">{getTipoAjuste(ajuste)}</Td>
                                        <Td className="font-bold text-slate-700">{getProductoNombre(ajuste)}</Td>
                                        <Td className="text-slate-500 font-semibold">{getBodegaNombre(ajuste)}</Td>
                                        <Td align="right" className="font-black text-slate-800">{formatNumber(ajuste.cantidad, 2)}</Td>
                                        <Td align="right" className="font-black text-rose-700">{formatCurrency(ajuste.costo_total)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </TableShell>
                    )}
                </Panel>
            </div>

            <Panel
                title="Tomas físicas recientes"
                subtitle="Seguimiento de inventario físico, diferencias y ajustes auditables"
            >
                {tomas.length === 0 ? (
                    <EmptyState
                        title="Sin tomas físicas"
                        description="Crea una toma física para comparar stock sistema contra stock contado."
                        icon="fas fa-clipboard-list"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Tipo</Th>
                                <Th>Estado</Th>
                                <Th>Bodega</Th>
                                <Th align="right">Detalles</Th>
                                <Th align="right">Diferencias</Th>
                                <Th>Fecha</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {tomas.map((toma) => (
                                <tr key={toma.id} className="hover:bg-slate-50/70 transition-colors">
                                    <Td className="font-black text-slate-800">{toma.codigo_toma || `TF-${toma.id}`}</Td>
                                    <Td className="font-bold text-slate-600">{toma.tipo || '-'}</Td>
                                    <Td><EstadoBadge value={toma.estado} /></Td>
                                    <Td className="text-slate-500 font-semibold">{getBodegaNombre(toma)}</Td>
                                    <Td align="right" className="font-black text-slate-700">{formatNumber(toma.detalles_count ?? toma.detalles, 0)}</Td>
                                    <Td align="right" className="font-black text-amber-700">{formatNumber(toma.detalles_con_diferencia_count ?? toma.detalles_con_diferencia, 0)}</Td>
                                    <Td className="text-slate-500">{formatDate(getFecha(toma))}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </TableShell>
                )}
            </Panel>
        </div>
    );
};

export default InventarioDashboard;
