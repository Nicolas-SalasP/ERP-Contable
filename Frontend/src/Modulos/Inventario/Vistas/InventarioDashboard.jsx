import React, { useEffect, useMemo, useState } from 'react';
import inventarioApi from '../Servicios/inventarioApi';
import {
    AlertBox,
    EmptyState,
    EstadoBadge,
    formatCurrency,
    formatNumber,
    getBodegaNombre,
    getProductoNombre,
    LoadingState,
    PageHeader,
    Panel,
    StatCard,
    TableShell,
    Td,
    Th,
} from '../Componentes/InventarioUI';

const emptyDashboard = {
    resumen: {
        productos: 0,
        bodegas: 0,
        reservas_activas: 0,
        tomas_abiertas: 0,
        stock_valorizado: 0,
    },
    ultimos_movimientos: [],
    tomas_recientes: [],
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
            const data = response?.data || {};

            setDashboard({
                ...emptyDashboard,
                ...data,
                resumen: {
                    ...emptyDashboard.resumen,
                    ...(data.resumen || {}),
                },
                ultimos_movimientos: Array.isArray(data.ultimos_movimientos)
                    ? data.ultimos_movimientos
                    : [],
                tomas_recientes: Array.isArray(data.tomas_recientes)
                    ? data.tomas_recientes
                    : [],
            });
        } catch (err) {
            setError(err?.response?.data || err?.message || 'No se pudo cargar el dashboard de inventario.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDashboard();
    }, []);

    const resumen = dashboard.resumen || emptyDashboard.resumen;

    const ultimosMovimientos = useMemo(() => {
        return dashboard.ultimos_movimientos || [];
    }, [dashboard]);

    const ultimasTomas = useMemo(() => {
        return dashboard.tomas_recientes || [];
    }, [dashboard]);

    if (loading) {
        return <LoadingState text="Cargando dashboard de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Dashboard de Inventario"
                description="Resumen demo-operativo optimizado del módulo de inventario: productos, bodegas, reservas, tomas físicas, movimientos recientes y valorización."
                actions={(
                    <button
                        type="button"
                        onClick={cargarDashboard}
                        className="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-black py-2.5 px-5 rounded-xl transition-all text-sm"
                    >
                        <i className="fas fa-rotate-right"></i>
                        Actualizar
                    </button>
                )}
            />

            {error && (
                <AlertBox tone="amber">
                    {typeof error === 'string' ? error : error?.message || 'No se pudieron cargar algunos datos del dashboard.'}
                </AlertBox>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <StatCard
                    title="Productos"
                    value={formatNumber(resumen.productos)}
                    helper="Catálogo activo del módulo"
                    icon="fas fa-box"
                    tone="emerald"
                />

                <StatCard
                    title="Bodegas"
                    value={formatNumber(resumen.bodegas)}
                    helper="Ubicaciones operativas"
                    icon="fas fa-warehouse"
                    tone="blue"
                />

                <StatCard
                    title="Reservas activas"
                    value={formatNumber(resumen.reservas_activas)}
                    helper="Stock comprometido"
                    icon="fas fa-lock"
                    tone="amber"
                />

                <StatCard
                    title="Tomas abiertas"
                    value={formatNumber(resumen.tomas_abiertas)}
                    helper="Borrador, en conteo o cerradas"
                    icon="fas fa-clipboard-check"
                    tone="indigo"
                />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <Panel
                    title="Valorización referencial"
                    subtitle="Valor consolidado calculado desde backend"
                    className="xl:col-span-1"
                >
                    <div className="rounded-3xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-6 shadow-lg shadow-emerald-100">
                        <p className="text-xs uppercase tracking-[0.2em] font-black text-emerald-50">
                            Stock valorizado
                        </p>

                        <h3 className="text-4xl font-black mt-3">
                            {formatCurrency(resumen.stock_valorizado)}
                        </h3>

                        <p className="mt-3 text-emerald-50 font-semibold text-sm">
                            Este valor se obtiene desde un endpoint agregado para evitar múltiples llamadas desde el dashboard.
                        </p>
                    </div>
                </Panel>

                <Panel
                    title="Últimos movimientos"
                    subtitle="Actividad reciente del Kardex"
                    className="xl:col-span-2"
                >
                    {ultimosMovimientos.length === 0 ? (
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
                                    <Th>Referencia</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {ultimosMovimientos.map((movimiento) => (
                                    <tr key={movimiento.id} className="hover:bg-slate-50/70 transition-colors">
                                        <Td>
                                            <span className="font-black text-slate-700 uppercase text-xs">
                                                {String(movimiento.tipo || '-').replaceAll('_', ' ')}
                                            </span>
                                        </Td>

                                        <Td className="font-bold text-slate-700">
                                            <span
                                                className="block max-w-[220px] truncate"
                                                title={getProductoNombre(movimiento)}
                                            >
                                                {getProductoNombre(movimiento)}
                                            </span>
                                        </Td>

                                        <Td className="text-slate-500 font-semibold">
                                            {getBodegaNombre(movimiento)}
                                        </Td>

                                        <Td align="right" className="font-black text-slate-800">
                                            {formatNumber(movimiento.cantidad, 2)}
                                        </Td>

                                        <Td className="text-slate-500">
                                            <span
                                                className="block max-w-[180px] truncate"
                                                title={movimiento.referencia || '-'}
                                            >
                                                {movimiento.referencia || '-'}
                                            </span>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </TableShell>
                    )}
                </Panel>
            </div>

            <Panel
                title="Tomas físicas recientes"
                subtitle="Seguimiento de inventario físico y ajustes auditables"
            >
                {ultimasTomas.length === 0 ? (
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
                                <Th>Referencia</Th>
                                <Th>Fecha inicio</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {ultimasTomas.map((toma) => (
                                <tr key={toma.id} className="hover:bg-slate-50/70 transition-colors">
                                    <Td className="font-black text-slate-800">
                                        {toma.codigo_toma || `TF-${toma.id}`}
                                    </Td>

                                    <Td className="font-bold text-slate-600">
                                        {toma.tipo || '-'}
                                    </Td>

                                    <Td>
                                        <EstadoBadge value={toma.estado} />
                                    </Td>

                                    <Td className="text-slate-500 font-semibold">
                                        {getBodegaNombre(toma)}
                                    </Td>

                                    <Td className="text-slate-500">
                                        {toma.referencia || '-'}
                                    </Td>

                                    <Td className="text-slate-500">
                                        {toma.fecha_inicio || toma.created_at || '-'}
                                    </Td>
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