import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    EmptyState,
    ErrorNotice,
    Field,
    formatDate,
    formatNumber,
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

const initialForm = {
    producto_id: '',
    codigo_lote: '',
    fecha_fabricacion: '',
    fecha_vencimiento: '',
    observacion: '',
};

const getCodigoLote = (lote) => {
    return lote?.codigo_lote
        || lote?.codigo
        || lote?.numero_lote
        || `Lote #${lote?.id ?? '-'}`;
};

const getStockLote = (lote) => {
    if (lote?.stock_actual !== undefined && lote?.stock_actual !== null) {
        return Number(lote.stock_actual);
    }

    if (lote?.stock_lote !== undefined && lote?.stock_lote !== null) {
        return Number(lote.stock_lote);
    }

    if (Array.isArray(lote?.stock_lotes)) {
        return lote.stock_lotes.reduce((total, item) => total + Number(item.stock_actual || 0), 0);
    }

    return null;
};

const getEstadoVencimiento = (fecha) => {
    if (!fecha) {
        return {
            label: 'Sin vencimiento',
            className: 'bg-slate-100 text-slate-500 border-slate-200',
        };
    }

    const hoy = new Date();
    const vencimiento = new Date(fecha);
    const diffMs = vencimiento.getTime() - hoy.getTime();
    const diffDias = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

    if (Number.isNaN(diffDias)) {
        return {
            label: 'Fecha inválida',
            className: 'bg-slate-100 text-slate-500 border-slate-200',
        };
    }

    if (diffDias < 0) {
        return {
            label: 'Vencido',
            className: 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }

    if (diffDias <= 30) {
        return {
            label: `Vence en ${diffDias} días`,
            className: 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }

    return {
        label: 'Vigente',
        className: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    };
};

const LotesInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const {
        productos,
        lotes,
        cargarProductosCache,
        cargarLotesCache,
        invalidarLotes,
    } = useInventarioData();

    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState(initialForm);
    const [productoFiltro, setProductoFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');

    const cargarDatos = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            await Promise.allSettled([
                cargarProductosCache({ force }),
                cargarLotesCache({ force }),
            ]);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const productosConLotes = useMemo(() => {
        return productos.filter((producto) => producto.maneja_lotes);
    }, [productos]);

    const lotesFiltrados = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return lotes.filter((lote) => {
            const coincideProducto = productoFiltro ? Number(lote.producto_id) === Number(productoFiltro) : true;

            const coincideBusqueda = !term || [
                getCodigoLote(lote),
                getProductoNombre(lote),
                lote.observacion,
                lote.estado,
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideProducto && coincideBusqueda;
        });
    }, [lotes, productoFiltro, busqueda]);

    const handleChange = (event) => {
        const { name, value } = event.target;

        setForm((current) => ({
            ...current,
            [name]: value,
        }));
    };

    const limpiarFormulario = () => {
        setForm(initialForm);
        setError(null);
    };

    const guardarLote = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            const payload = {
                producto_id: Number(form.producto_id),
                codigo_lote: form.codigo_lote,
                fecha_fabricacion: form.fecha_fabricacion || null,
                fecha_vencimiento: form.fecha_vencimiento || null,
                observacion: form.observacion || null,
            };

            await inventarioApi.lotes.crear(payload);

            await Swal.fire({
                icon: 'success',
                title: 'Lote creado',
                text: 'El lote fue registrado correctamente.',
                confirmButtonColor: '#10b981',
            });

            invalidarLotes();
            limpiarFormulario();
            setMostrarFormulario(false);
            await cargarDatos(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return <LoadingState text="Cargando lotes de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Lotes y Vencimientos"
                description="Control granular para productos que manejan trazabilidad por lote y fecha de vencimiento."
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarDatos(true)}>
                            <i className="fas fa-rotate-right"></i>
                            Actualizar
                        </SecondaryButton>

                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)}>
                            <i className={mostrarFormulario ? 'fas fa-xmark' : 'fas fa-plus'}></i>
                            {mostrarFormulario ? 'Cerrar formulario' : 'Nuevo lote'}
                        </PrimaryButton>
                    </>
                )}
            />

            {mostrarFormulario && (
                <Panel
                    title="Crear lote"
                    subtitle="El lote define trazabilidad; el stock por lote se mueve mediante entradas, salidas, traspasos y ajustes."
                >
                    <ErrorNotice error={error} />

                    <form onSubmit={guardarLote} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                        <Field label="Producto">
                            <select
                                name="producto_id"
                                value={form.producto_id}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                required
                            >
                                <option value="">Seleccionar producto</option>
                                {productosConLotes.map((producto) => (
                                    <option key={producto.id} value={producto.id}>
                                        {producto.sku ? `${producto.sku} - ${producto.nombre}` : producto.nombre}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Código lote">
                            <input
                                type="text"
                                name="codigo_lote"
                                value={form.codigo_lote}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="LOTE-001"
                                required
                            />
                        </Field>

                        <Field label="Fecha fabricación">
                            <input
                                type="date"
                                name="fecha_fabricacion"
                                value={form.fecha_fabricacion}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            />
                        </Field>

                        <Field label="Fecha vencimiento">
                            <input
                                type="date"
                                name="fecha_vencimiento"
                                value={form.fecha_vencimiento}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            />
                        </Field>

                        <div className="md:col-span-2 xl:col-span-4">
                            <Field label="Observación">
                                <textarea
                                    name="observacion"
                                    value={form.observacion}
                                    onChange={handleChange}
                                    rows="3"
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none resize-none"
                                    placeholder="Detalle o nota del lote"
                                />
                            </Field>
                        </div>

                        <div className="md:col-span-2 xl:col-span-4 flex flex-wrap justify-end gap-3">
                            <SecondaryButton type="button" onClick={limpiarFormulario}>
                                Limpiar
                            </SecondaryButton>

                            <PrimaryButton type="submit" disabled={saving}>
                                <i className="fas fa-save"></i>
                                {saving ? 'Guardando...' : 'Guardar lote'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Listado de lotes"
                subtitle="Lotes disponibles para movimientos, reservas, Kardex y tomas físicas."
                actions={(
                    <div className="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                        <select
                            value={productoFiltro}
                            onChange={(event) => setProductoFiltro(event.target.value)}
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todos los productos</option>
                            {productosConLotes.map((producto) => (
                                <option key={producto.id} value={producto.id}>
                                    {producto.sku ? `${producto.sku} - ${producto.nombre}` : producto.nombre}
                                </option>
                            ))}
                        </select>

                        <input
                            type="text"
                            value={busqueda}
                            onChange={(event) => setBusqueda(event.target.value)}
                            className="w-full md:w-80 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            placeholder="Buscar lote o producto..."
                        />
                    </div>
                )}
            >
                {lotesFiltrados.length === 0 ? (
                    <EmptyState
                        title="Sin lotes"
                        description="Crea lotes para productos que requieren trazabilidad granular."
                        icon="fas fa-barcode"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código lote</Th>
                                <Th>Producto</Th>
                                <Th>Fabricación</Th>
                                <Th>Vencimiento</Th>
                                <Th>Estado</Th>
                                <Th align="right">Stock lote</Th>
                                <Th>Observación</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {lotesFiltrados.map((lote) => {
                                const estado = getEstadoVencimiento(lote.fecha_vencimiento);
                                const stock = getStockLote(lote);

                                return (
                                    <tr key={lote.id} className="hover:bg-slate-50/70 transition-colors">
                                        <Td className="font-black text-slate-800">
                                            {getCodigoLote(lote)}
                                        </Td>

                                        <Td className="font-black text-slate-800">
                                            {getProductoNombre(lote)}
                                        </Td>

                                        <Td className="text-slate-500 font-semibold">
                                            {formatDate(lote.fecha_fabricacion)}
                                        </Td>

                                        <Td className="text-slate-500 font-semibold">
                                            {formatDate(lote.fecha_vencimiento)}
                                        </Td>

                                        <Td>
                                            <span className={`inline-flex px-2.5 py-1 rounded-full text-[11px] font-black uppercase border ${estado.className}`}>
                                                {estado.label}
                                            </span>
                                        </Td>

                                        <Td align="right" className="font-black text-slate-800">
                                            {stock === null ? '-' : formatNumber(stock, 2)}
                                        </Td>

                                        <Td className="text-slate-500 max-w-md">
                                            {lote.observacion || '-'}
                                        </Td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </TableShell>
                )}
            </Panel>
        </div>
    );
};

export default LotesInventario;