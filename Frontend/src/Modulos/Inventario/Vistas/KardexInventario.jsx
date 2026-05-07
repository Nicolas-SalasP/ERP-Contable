import React, { useEffect, useMemo, useState } from 'react';
import inventarioApi from '../Servicios/inventarioApi';
import {
    EmptyState,
    Field,
    formatDate,
    formatNumber,
    getBodegaNombre,
    getProductoNombre,
    LoadingState,
    PageHeader,
    Panel,
    PrimaryButton,
    SecondaryButton,
    TableShell,
    Td,
    Th,
} from '../Componentes/InventarioUI';

const tiposMovimiento = [
    { value: 'entrada', label: 'Entrada' },
    { value: 'salida', label: 'Salida' },
    { value: 'traspaso', label: 'Traspaso' },
    { value: 'ajuste_positivo', label: 'Ajuste positivo' },
    { value: 'ajuste_negativo', label: 'Ajuste negativo' },
];

const initialFiltros = {
    producto_id: '',
    bodega_id: '',
    lote_id: '',
    tipo: '',
    fecha_desde: '',
    fecha_hasta: '',
};

const esEntrada = (tipo) => ['entrada', 'ajuste_positivo'].includes(tipo);
const esSalida = (tipo) => ['salida', 'ajuste_negativo', 'merma'].includes(tipo);

const KardexInventario = () => {
    const [loading, setLoading] = useState(true);
    const [consultando, setConsultando] = useState(false);
    const [error, setError] = useState(null);

    const [productos, setProductos] = useState([]);
    const [bodegas, setBodegas] = useState([]);
    const [lotes, setLotes] = useState([]);
    const [kardex, setKardex] = useState([]);

    const [filtros, setFiltros] = useState(initialFiltros);

    const cargarBase = async () => {
        try {
            setLoading(true);
            setError(null);

            const [
                productosResponse,
                bodegasResponse,
                lotesResponse,
                kardexResponse,
            ] = await Promise.allSettled([
                inventarioApi.productos.listar(),
                inventarioApi.bodegas.listar(),
                inventarioApi.lotes.listar(),
                inventarioApi.kardex.listar(),
            ]);

            if (productosResponse.status === 'fulfilled') {
                setProductos(productosResponse.value.data || []);
            }

            if (bodegasResponse.status === 'fulfilled') {
                setBodegas(bodegasResponse.value.data || []);
            }

            if (lotesResponse.status === 'fulfilled') {
                setLotes(lotesResponse.value.data || []);
            }

            if (kardexResponse.status === 'fulfilled') {
                setKardex(kardexResponse.value.data || []);
            }
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarBase();
    }, []);

    const lotesFiltrados = useMemo(() => {
        if (!filtros.producto_id) {
            return lotes;
        }

        return lotes.filter((lote) => Number(lote.producto_id) === Number(filtros.producto_id));
    }, [lotes, filtros.producto_id]);

    const handleChange = (event) => {
        const { name, value } = event.target;

        setFiltros((current) => {
            const next = {
                ...current,
                [name]: value,
            };

            if (name === 'producto_id') {
                next.lote_id = '';
            }

            return next;
        });
    };

    const filtrosActivos = () => {
        return Object.fromEntries(
            Object.entries(filtros).filter(([, value]) => value !== undefined && value !== null && value !== '')
        );
    };

    const consultarKardex = async (event) => {
        event?.preventDefault();

        try {
            setConsultando(true);
            setError(null);

            const response = await inventarioApi.kardex.listar(filtrosActivos());

            setKardex(response.data || []);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setConsultando(false);
        }
    };

    const limpiarFiltros = async () => {
        setFiltros(initialFiltros);

        try {
            setConsultando(true);
            const response = await inventarioApi.kardex.listar();
            setKardex(response.data || []);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setConsultando(false);
        }
    };

    const getEntrada = (item) => {
        if (item.entrada !== undefined && item.entrada !== null) {
            return Number(item.entrada);
        }

        if (esEntrada(item.tipo)) {
            return Number(item.cantidad || 0);
        }

        return 0;
    };

    const getSalida = (item) => {
        if (item.salida !== undefined && item.salida !== null) {
            return Number(item.salida);
        }

        if (esSalida(item.tipo)) {
            return Number(item.cantidad || 0);
        }

        return 0;
    };

    const getSaldo = (item) => {
        return item.saldo
            ?? item.stock_resultante
            ?? item.stock_despues
            ?? item.stock_actual
            ?? '-';
    };

    if (loading) {
        return <LoadingState text="Cargando Kardex de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Kardex de Inventario"
                description="Consulta la trazabilidad de movimientos por producto, bodega, lote, tipo y fecha."
                actions={(
                    <SecondaryButton onClick={cargarBase}>
                        <i className="fas fa-rotate-right"></i>
                        Actualizar
                    </SecondaryButton>
                )}
            />

            {error && (
                <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 font-bold">
                    {error?.message || 'No se pudo consultar el Kardex.'}
                </div>
            )}

            <Panel
                title="Filtros de consulta"
                subtitle="Usa filtros para revisar la trazabilidad específica antes de una auditoría o toma física."
            >
                <form onSubmit={consultarKardex} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-5">
                    <Field label="Producto">
                        <select
                            name="producto_id"
                            value={filtros.producto_id}
                            onChange={handleChange}
                            className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todos</option>
                            {productos.map((producto) => (
                                <option key={producto.id} value={producto.id}>
                                    {producto.sku ? `${producto.sku} - ${producto.nombre}` : producto.nombre}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Bodega">
                        <select
                            name="bodega_id"
                            value={filtros.bodega_id}
                            onChange={handleChange}
                            className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todas</option>
                            {bodegas.map((bodega) => (
                                <option key={bodega.id} value={bodega.id}>
                                    {bodega.codigo ? `${bodega.codigo} - ${bodega.nombre}` : bodega.nombre}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Lote">
                        <select
                            name="lote_id"
                            value={filtros.lote_id}
                            onChange={handleChange}
                            className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todos</option>
                            {lotesFiltrados.map((lote) => (
                                <option key={lote.id} value={lote.id}>
                                    {lote.codigo_lote || lote.codigo || `Lote #${lote.id}`}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Tipo">
                        <select
                            name="tipo"
                            value={filtros.tipo}
                            onChange={handleChange}
                            className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todos</option>
                            {tiposMovimiento.map((tipo) => (
                                <option key={tipo.value} value={tipo.value}>
                                    {tipo.label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Desde">
                        <input
                            type="date"
                            name="fecha_desde"
                            value={filtros.fecha_desde}
                            onChange={handleChange}
                            className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        />
                    </Field>

                    <Field label="Hasta">
                        <input
                            type="date"
                            name="fecha_hasta"
                            value={filtros.fecha_hasta}
                            onChange={handleChange}
                            className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        />
                    </Field>

                    <div className="md:col-span-2 xl:col-span-6 flex flex-wrap justify-end gap-3">
                        <SecondaryButton type="button" onClick={limpiarFiltros}>
                            Limpiar
                        </SecondaryButton>

                        <PrimaryButton type="submit" disabled={consultando}>
                            <i className="fas fa-search"></i>
                            {consultando ? 'Consultando...' : 'Consultar Kardex'}
                        </PrimaryButton>
                    </div>
                </form>
            </Panel>

            <Panel
                title="Resultado Kardex"
                subtitle="Historial de entradas, salidas, ajustes y saldo resultante."
            >
                {kardex.length === 0 ? (
                    <EmptyState
                        title="Sin movimientos en Kardex"
                        description="No hay movimientos para los filtros seleccionados."
                        icon="fas fa-list-check"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Fecha</Th>
                                <Th>Tipo</Th>
                                <Th>Producto</Th>
                                <Th>Bodega</Th>
                                <Th align="right">Entrada</Th>
                                <Th align="right">Salida</Th>
                                <Th align="right">Saldo</Th>
                                <Th>Referencia</Th>
                                <Th>Motivo</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {kardex.map((item) => (
                                <tr key={item.id} className="hover:bg-slate-50/70 transition-colors">
                                    <Td className="text-slate-500 font-semibold">
                                        {formatDate(item.fecha_movimiento || item.fecha || item.created_at)}
                                    </Td>

                                    <Td>
                                        <span className="inline-flex px-2.5 py-1 rounded-full text-[11px] font-black uppercase border bg-indigo-50 text-indigo-700 border-indigo-200">
                                            {String(item.tipo || '-').replaceAll('_', ' ')}
                                        </span>
                                    </Td>

                                    <Td className="font-black text-slate-800">
                                        {getProductoNombre(item)}
                                    </Td>

                                    <Td className="font-semibold text-slate-500">
                                        {getBodegaNombre(item)}
                                    </Td>

                                    <Td align="right" className="font-black text-emerald-600">
                                        {getEntrada(item) > 0 ? formatNumber(getEntrada(item), 2) : '-'}
                                    </Td>

                                    <Td align="right" className="font-black text-rose-600">
                                        {getSalida(item) > 0 ? formatNumber(getSalida(item), 2) : '-'}
                                    </Td>

                                    <Td align="right" className="font-black text-slate-800">
                                        {typeof getSaldo(item) === 'number'
                                            ? formatNumber(getSaldo(item), 2)
                                            : getSaldo(item)}
                                    </Td>

                                    <Td className="text-slate-500">
                                    <span
                                        className="block max-w-[180px] truncate"
                                        title={item.referencia || '-'}
                                    >
                                        {item.referencia || '-'}
                                    </span>
                                </Td>

                                    <Td className="text-slate-500">
                                        {item.motivo || '-'}
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

export default KardexInventario;