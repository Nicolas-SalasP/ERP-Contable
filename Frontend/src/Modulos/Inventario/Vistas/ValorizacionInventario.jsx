import React, { useEffect, useMemo, useState } from 'react';
import inventarioApi from '../Servicios/inventarioApi';
import {
    EmptyState,
    formatCurrency,
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

const getStock = (item) => {
    return Number(
        item.stock_actual
        ?? item.stock
        ?? item.cantidad
        ?? item.total_stock
        ?? 0
    );
};

const getCostoPromedio = (item) => {
    return Number(
        item.costo_promedio
        ?? item.pmp
        ?? item.costo_unitario_promedio
        ?? item.producto?.costo_promedio
        ?? 0
    );
};

const getValorTotal = (item) => {
    const explicit = item.valor_total
        ?? item.total_valorizado
        ?? item.valor_stock
        ?? item.total;

    if (explicit !== undefined && explicit !== null) {
        return Number(explicit);
    }

    return getStock(item) * getCostoPromedio(item);
};

const ValorizacionInventario = () => {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [valorizacion, setValorizacion] = useState([]);
    const [productos, setProductos] = useState([]);
    const [bodegas, setBodegas] = useState([]);

    const [productoFiltro, setProductoFiltro] = useState('');
    const [bodegaFiltro, setBodegaFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');

    const cargarDatos = async () => {
        try {
            setLoading(true);
            setError(null);

            const [
                valorizacionResponse,
                productosResponse,
                bodegasResponse,
            ] = await Promise.allSettled([
                inventarioApi.valorizacion.listar(),
                inventarioApi.productos.listar(),
                inventarioApi.bodegas.listar(),
            ]);

            if (valorizacionResponse.status === 'fulfilled') {
                setValorizacion(valorizacionResponse.value.data || []);
            }

            if (productosResponse.status === 'fulfilled') {
                setProductos(productosResponse.value.data || []);
            }

            if (bodegasResponse.status === 'fulfilled') {
                setBodegas(bodegasResponse.value.data || []);
            }
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const valorizacionFiltrada = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return valorizacion.filter((item) => {
            const coincideProducto = productoFiltro ? Number(item.producto_id || item.producto?.id) === Number(productoFiltro) : true;
            const coincideBodega = bodegaFiltro ? Number(item.bodega_id || item.bodega?.id) === Number(bodegaFiltro) : true;

            const coincideBusqueda = !term || [
                getProductoNombre(item),
                getBodegaNombre(item),
                item.sku,
                item.producto?.sku,
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideProducto && coincideBodega && coincideBusqueda;
        });
    }, [valorizacion, productoFiltro, bodegaFiltro, busqueda]);

    const resumen = useMemo(() => {
        const totalStock = valorizacionFiltrada.reduce((total, item) => total + getStock(item), 0);
        const totalValor = valorizacionFiltrada.reduce((total, item) => total + getValorTotal(item), 0);
        const promedio = totalStock > 0 ? totalValor / totalStock : 0;

        return {
            registros: valorizacionFiltrada.length,
            totalStock,
            totalValor,
            promedio,
        };
    }, [valorizacionFiltrada]);

    if (loading) {
        return <LoadingState text="Cargando valorización de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Valorización de Inventario"
                description="Consulta de stock valorizado con base en PMP/costo promedio. Útil para reportes contables, auditoría y dashboard."
                helpModuloId="inventario"
                actions={(
                    <SecondaryButton onClick={cargarDatos}>
                        <i className="fas fa-rotate-right"></i>
                        Actualizar
                    </SecondaryButton>
                )}
            />

            {error && (
                <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 font-bold">
                    {error?.message || 'No se pudo cargar la valorización.'}
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <StatCard
                    title="Registros"
                    value={formatNumber(resumen.registros)}
                    helper="Líneas valorizadas"
                    icon="fas fa-list"
                    tone="blue"
                />

                <StatCard
                    title="Stock total"
                    value={formatNumber(resumen.totalStock, 2)}
                    helper="Cantidad física acumulada"
                    icon="fas fa-boxes-stacked"
                    tone="emerald"
                />

                <StatCard
                    title="Valor total"
                    value={formatCurrency(resumen.totalValor)}
                    helper="Stock valorizado"
                    icon="fas fa-dollar-sign"
                    tone="amber"
                />

                <StatCard
                    title="Costo promedio global"
                    value={formatCurrency(resumen.promedio)}
                    helper="Estimación sobre líneas filtradas"
                    icon="fas fa-chart-line"
                    tone="indigo"
                />
            </div>

            <Panel
                title="Filtros"
                subtitle="Filtra la valorización por producto, bodega o búsqueda general."
            >
                <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <select
                        value={productoFiltro}
                        onChange={(event) => setProductoFiltro(event.target.value)}
                        className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                    >
                        <option value="">Todos los productos</option>
                        {productos.map((producto) => (
                            <option key={producto.id} value={producto.id}>
                                {producto.sku ? `${producto.sku} - ${producto.nombre}` : producto.nombre}
                            </option>
                        ))}
                    </select>

                    <select
                        value={bodegaFiltro}
                        onChange={(event) => setBodegaFiltro(event.target.value)}
                        className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                    >
                        <option value="">Todas las bodegas</option>
                        {bodegas.map((bodega) => (
                            <option key={bodega.id} value={bodega.id}>
                                {bodega.codigo ? `${bodega.codigo} - ${bodega.nombre}` : bodega.nombre}
                            </option>
                        ))}
                    </select>

                    <input
                        type="text"
                        value={busqueda}
                        onChange={(event) => setBusqueda(event.target.value)}
                        className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        placeholder="Buscar producto, SKU o bodega..."
                    />
                </div>
            </Panel>

            <Panel
                title="Stock valorizado"
                subtitle="Detalle por producto/bodega según respuesta del backend."
            >
                {valorizacionFiltrada.length === 0 ? (
                    <EmptyState
                        title="Sin valorización"
                        description="Registra entradas con costo unitario para alimentar PMP y valorización."
                        icon="fas fa-chart-pie"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Producto</Th>
                                <Th>Bodega</Th>
                                <Th align="right">Stock</Th>
                                <Th align="right">PMP / Costo promedio</Th>
                                <Th align="right">Valor total</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {valorizacionFiltrada.map((item, index) => (
                                <tr key={item.id || `${item.producto_id}-${item.bodega_id}-${index}`} className="hover:bg-slate-50/70 transition-colors">
                                    <Td>
                                        <div>
                                            <p className="font-black text-slate-800">
                                                {getProductoNombre(item)}
                                            </p>

                                            {(item.sku || item.producto?.sku) && (
                                                <p className="text-xs text-slate-400 font-black mt-1">
                                                    {item.sku || item.producto?.sku}
                                                </p>
                                            )}
                                        </div>
                                    </Td>

                                    <Td className="font-semibold text-slate-500">
                                        {getBodegaNombre(item)}
                                    </Td>

                                    <Td align="right" className="font-black text-slate-800">
                                        {formatNumber(getStock(item), 2)}
                                    </Td>

                                    <Td align="right" className="font-black text-slate-700">
                                        {formatCurrency(getCostoPromedio(item))}
                                    </Td>

                                    <Td align="right" className="font-black text-emerald-600">
                                        {formatCurrency(getValorTotal(item))}
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

export default ValorizacionInventario;