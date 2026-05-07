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

const InventarioDashboard = () => {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [productos, setProductos] = useState([]);
    const [bodegas, setBodegas] = useState([]);
    const [movimientos, setMovimientos] = useState([]);
    const [reservas, setReservas] = useState([]);
    const [tomasFisicas, setTomasFisicas] = useState([]);
    const [valorizacion, setValorizacion] = useState([]);

    const cargarDashboard = async () => {
        try {
            setLoading(true);
            setError(null);

            const [
                productosResponse,
                bodegasResponse,
                movimientosResponse,
                reservasResponse,
                tomasFisicasResponse,
                valorizacionResponse,
            ] = await Promise.allSettled([
                inventarioApi.productos.listar(),
                inventarioApi.bodegas.listar(),
                inventarioApi.movimientos.listar(),
                inventarioApi.reservas.listar(),
                inventarioApi.tomasFisicas.listar(),
                inventarioApi.valorizacion.listar(),
            ]);

            if (productosResponse.status === 'fulfilled') {
                setProductos(productosResponse.value.data || []);
            }

            if (bodegasResponse.status === 'fulfilled') {
                setBodegas(bodegasResponse.value.data || []);
            }

            if (movimientosResponse.status === 'fulfilled') {
                setMovimientos(movimientosResponse.value.data || []);
            }

            if (reservasResponse.status === 'fulfilled') {
                setReservas(reservasResponse.value.data || []);
            }

            if (tomasFisicasResponse.status === 'fulfilled') {
                setTomasFisicas(tomasFisicasResponse.value.data || []);
            }

            if (valorizacionResponse.status === 'fulfilled') {
                setValorizacion(valorizacionResponse.value.data || []);
            }

            const falloCritico = [
                productosResponse,
                bodegasResponse,
            ].some((response) => response.status === 'rejected');

            if (falloCritico) {
                setError('No se pudieron cargar algunos datos principales del dashboard.');
            }
        } catch (err) {
            setError(err?.message || 'No se pudo cargar el dashboard de inventario.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDashboard();
    }, []);

    const totalValorizado = useMemo(() => {
        return valorizacion.reduce((total, item) => {
            return total + Number(item?.valor_total ?? item?.total_valorizado ?? item?.valor_stock ?? 0);
        }, 0);
    }, [valorizacion]);

    const tomasAbiertas = useMemo(() => {
        return tomasFisicas.filter((toma) => ['BORRADOR', 'EN_CONTEO', 'CERRADA'].includes(toma.estado)).length;
    }, [tomasFisicas]);

    const reservasActivas = useMemo(() => {
        return reservas.filter((reserva) => ['ACTIVA', 'PARCIAL', 'ACTIVA_RESERVA'].includes(reserva.estado)).length;
    }, [reservas]);

    const ultimosMovimientos = useMemo(() => {
        return [...movimientos].slice(0, 6);
    }, [movimientos]);

    const ultimasTomas = useMemo(() => {
        return [...tomasFisicas].slice(0, 6);
    }, [tomasFisicas]);

    if (loading) {
        return <LoadingState text="Cargando dashboard de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Dashboard de Inventario"
                description="Resumen demo-operativo del módulo de inventario: productos, bodegas, movimientos, valorización, reservas y tomas físicas."
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
                    {error}
                </AlertBox>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <StatCard
                    title="Productos"
                    value={formatNumber(productos.length)}
                    helper="Catálogo activo del módulo"
                    icon="fas fa-box"
                    tone="emerald"
                />

                <StatCard
                    title="Bodegas"
                    value={formatNumber(bodegas.length)}
                    helper="Ubicaciones operativas"
                    icon="fas fa-warehouse"
                    tone="blue"
                />

                <StatCard
                    title="Reservas activas"
                    value={formatNumber(reservasActivas)}
                    helper="Stock comprometido"
                    icon="fas fa-lock"
                    tone="amber"
                />

                <StatCard
                    title="Tomas abiertas"
                    value={formatNumber(tomasAbiertas)}
                    helper="Borrador, en conteo o cerradas"
                    icon="fas fa-clipboard-check"
                    tone="indigo"
                />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <Panel
                    title="Valorización referencial"
                    subtitle="Valor consolidado según la respuesta del backend"
                    className="xl:col-span-1"
                >
                    <div className="rounded-3xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-6 shadow-lg shadow-emerald-100">
                        <p className="text-xs uppercase tracking-[0.2em] font-black text-emerald-50">
                            Stock valorizado
                        </p>

                        <h3 className="text-4xl font-black mt-3">
                            {formatCurrency(totalValorizado)}
                        </h3>

                        <p className="mt-3 text-emerald-50 font-semibold text-sm">
                            Este valor depende de los endpoints de PMP y valorización implementados en backend.
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