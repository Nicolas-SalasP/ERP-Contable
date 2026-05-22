import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    EmptyState,
    ErrorNotice,
    Field,
    formatCurrency,
    formatNumber,
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
    sku: '',
    nombre: '',
    descripcion: '',
    unidad_medida_id: '',
    costo_promedio: '',
    stock_minimo: '',
    maneja_lotes: false,
    requiere_fecha_vencimiento: false,
    activo: true,
};

const ProductosInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const {
        productos,
        catalogos,
        cargarProductosCache,
        cargarCatalogosCache,
        invalidarProductos,
    } = useInventarioData();

    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState(initialForm);
    const [busqueda, setBusqueda] = useState('');

    const cargarDatos = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            await Promise.all([
                cargarProductosCache({ force }),
                cargarCatalogosCache({ force }),
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

    const productosFiltrados = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        if (!term) {
            return productos;
        }

        return productos.filter((producto) => {
            return [
                producto.sku,
                producto.nombre,
                producto.descripcion,
                producto.unidad_medida?.nombre,
            ].some((value) => String(value || '').toLowerCase().includes(term));
        });
    }, [productos, busqueda]);

    const unidades = useMemo(() => {
        return catalogos?.unidades_medida
            || catalogos?.unidades
            || catalogos?.data?.unidades_medida
            || [];
    }, [catalogos]);

    const handleChange = (event) => {
        const { name, value, type, checked } = event.target;

        setForm((current) => ({
            ...current,
            [name]: type === 'checkbox' ? checked : value,
        }));
    };

    const limpiarFormulario = () => {
        setForm(initialForm);
        setError(null);
    };

    const guardarProducto = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            const payload = {
                ...form,
                unidad_medida_id: Number(form.unidad_medida_id),
                costo_promedio: form.costo_promedio === '' ? 0 : Number(form.costo_promedio),
                stock_minimo: form.stock_minimo === '' ? 0 : Number(form.stock_minimo),
                maneja_lotes: Boolean(form.maneja_lotes),
                requiere_fecha_vencimiento: Boolean(form.requiere_fecha_vencimiento),
                activo: Boolean(form.activo),
            };

            await inventarioApi.productos.crear(payload);

            await Swal.fire({
                icon: 'success',
                title: 'Producto creado',
                text: 'El producto fue registrado correctamente en Inventario.',
                confirmButtonColor: '#10b981',
            });

            limpiarFormulario();
            setMostrarFormulario(false);
            invalidarProductos();
            await cargarDatos(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return <LoadingState text="Cargando productos de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Productos de Inventario"
                description="Gestión demo-operativa del catálogo de productos utilizado por movimientos, Kardex, lotes, reservas y tomas físicas."
                helpModuloId="inventario"
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarDatos(true)}>
                            <i className="fas fa-rotate-right"></i>
                            Actualizar
                        </SecondaryButton>

                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)}>
                            <i className={mostrarFormulario ? 'fas fa-xmark' : 'fas fa-plus'}></i>
                            {mostrarFormulario ? 'Cerrar formulario' : 'Nuevo producto'}
                        </PrimaryButton>
                    </>
                )}
            />

            {mostrarFormulario && (
                <Panel
                    title="Crear producto"
                    subtitle="El stock inicial se mantiene en cero; los movimientos controlan entradas y salidas."
                >
                    <ErrorNotice error={error} />

                    <form onSubmit={guardarProducto} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                        <Field label="SKU">
                            <input
                                type="text"
                                name="sku"
                                value={form.sku}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="SKU-001"
                                required
                            />
                        </Field>

                        <Field label="Nombre">
                            <input
                                type="text"
                                name="nombre"
                                value={form.nombre}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="Producto demo"
                                required
                            />
                        </Field>

                        <Field label="Unidad de medida">
                            <select
                                name="unidad_medida_id"
                                value={form.unidad_medida_id}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                required
                            >
                                <option value="">Seleccionar</option>
                                {unidades.map((unidad) => (
                                    <option key={unidad.id} value={unidad.id}>
                                        {unidad.nombre || unidad.codigo}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Costo promedio inicial">
                            <input
                                type="number"
                                name="costo_promedio"
                                value={form.costo_promedio}
                                onChange={handleChange}
                                min="0"
                                step="1"
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="0"
                            />
                        </Field>

                        <Field label="Stock mínimo">
                            <input
                                type="number"
                                name="stock_minimo"
                                value={form.stock_minimo}
                                onChange={handleChange}
                                min="0"
                                step="1"
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="0"
                            />
                        </Field>

                        <Field label="Descripción">
                            <input
                                type="text"
                                name="descripcion"
                                value={form.descripcion}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="Descripción opcional"
                            />
                        </Field>

                        <div className="md:col-span-2 xl:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <label className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 cursor-pointer hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    name="maneja_lotes"
                                    checked={form.maneja_lotes}
                                    onChange={handleChange}
                                    className="w-4 h-4 accent-emerald-500"
                                />
                                <span className="font-black text-slate-700 text-sm">
                                    Maneja lotes
                                </span>
                            </label>

                            <label className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 cursor-pointer hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    name="requiere_fecha_vencimiento"
                                    checked={form.requiere_fecha_vencimiento}
                                    onChange={handleChange}
                                    className="w-4 h-4 accent-emerald-500"
                                />
                                <span className="font-black text-slate-700 text-sm">
                                    Requiere vencimiento
                                </span>
                            </label>

                            <label className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 cursor-pointer hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    name="activo"
                                    checked={form.activo}
                                    onChange={handleChange}
                                    className="w-4 h-4 accent-emerald-500"
                                />
                                <span className="font-black text-slate-700 text-sm">
                                    Producto activo
                                </span>
                            </label>
                        </div>

                        <div className="md:col-span-2 xl:col-span-4 flex flex-wrap justify-end gap-3">
                            <SecondaryButton type="button" onClick={limpiarFormulario}>
                                Limpiar
                            </SecondaryButton>

                            <PrimaryButton type="submit" disabled={saving}>
                                <i className="fas fa-save"></i>
                                {saving ? 'Guardando...' : 'Guardar producto'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Listado de productos"
                subtitle="Productos disponibles para movimientos, valorización, reservas y conteos físicos."
                actions={(
                    <input
                        type="text"
                        value={busqueda}
                        onChange={(event) => setBusqueda(event.target.value)}
                        className="w-full md:w-80 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        placeholder="Buscar por SKU, nombre o unidad..."
                    />
                )}
            >
                {productosFiltrados.length === 0 ? (
                    <EmptyState
                        title="Sin productos"
                        description="Crea un producto para comenzar a registrar movimientos de inventario."
                        icon="fas fa-box-open"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>SKU</Th>
                                <Th>Producto</Th>
                                <Th>Unidad</Th>
                                <Th align="right">PMP</Th>
                                <Th align="right">Stock mínimo</Th>
                                <Th>Lotes</Th>
                                <Th>Estado</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {productosFiltrados.map((producto) => (
                                <tr key={producto.id} className="hover:bg-slate-50/70 transition-colors">
                                    <Td className="font-black text-slate-800">
                                        {producto.sku}
                                    </Td>

                                    <Td>
                                        <div>
                                            <p className="font-black text-slate-800">
                                                {producto.nombre}
                                            </p>

                                            {producto.descripcion && (
                                                <p className="text-xs text-slate-400 font-medium mt-1">
                                                    {producto.descripcion}
                                                </p>
                                            )}
                                        </div>
                                    </Td>

                                    <Td className="font-bold text-slate-500">
                                        {producto.unidad_medida?.nombre || producto.unidad?.nombre || '-'}
                                    </Td>

                                    <Td align="right" className="font-black text-slate-800">
                                        {formatCurrency(producto.costo_promedio || 0)}
                                    </Td>

                                    <Td align="right" className="font-black text-slate-700">
                                        {formatNumber(producto.stock_minimo || 0, 2)}
                                    </Td>

                                    <Td>
                                        <div className="flex flex-col gap-1 text-xs font-black">
                                            <span className={producto.maneja_lotes ? 'text-emerald-600' : 'text-slate-400'}>
                                                {producto.maneja_lotes ? 'Maneja lotes' : 'Sin lotes'}
                                            </span>

                                            {producto.requiere_fecha_vencimiento && (
                                                <span className="text-amber-600">
                                                    Vencimiento requerido
                                                </span>
                                            )}
                                        </div>
                                    </Td>

                                    <Td>
                                        <span className={`inline-flex px-2.5 py-1 rounded-full text-[11px] font-black uppercase border ${
                                            producto.activo
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                : 'bg-slate-100 text-slate-500 border-slate-200'
                                        }`}
                                        >
                                            {producto.activo ? 'Activo' : 'Inactivo'}
                                        </span>
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

export default ProductosInventario;