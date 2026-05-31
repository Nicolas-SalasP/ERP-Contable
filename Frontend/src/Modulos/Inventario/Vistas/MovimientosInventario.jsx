import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    AlertBox,
    EmptyState,
    ErrorNotice,
    Field,
    formatCurrency,
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

const tiposMovimiento = [
    { value: 'entrada', label: 'Entrada' },
    { value: 'salida', label: 'Salida' },
    { value: 'traspaso', label: 'Traspaso' },
    { value: 'ajuste_positivo', label: 'Ajuste positivo' },
    { value: 'ajuste_negativo', label: 'Ajuste negativo' },
];

const initialForm = {
    tipo: 'entrada',
    producto_id: '',
    bodega_origen_id: '',
    bodega_destino_id: '',
    lote_id: '',
    cantidad: '',
    costo_unitario: '',
    referencia: '',
    motivo: '',
    observacion: '',
};

const necesitaOrigen = (tipo) => ['salida', 'traspaso', 'ajuste_negativo'].includes(tipo);
const necesitaDestino = (tipo) => ['entrada', 'traspaso', 'ajuste_positivo'].includes(tipo);
const necesitaCosto = (tipo) => ['entrada', 'ajuste_positivo'].includes(tipo);

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

const MovimientosInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const {
        productos,
        bodegas,
        lotes,
        cargarProductosCache,
        cargarBodegasCache,
        cargarLotesCache,
        invalidarProductos,
        invalidarLotes,
    } = useInventarioData();

    const [movimientos, setMovimientos] = useState([]);

    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState(initialForm);
    const [filtroTipo, setFiltroTipo] = useState('');
    const [busqueda, setBusqueda] = useState('');

    const cargarDatos = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            const [movimientosResponse] = await Promise.allSettled([
                inventarioApi.movimientos.listar(),
                cargarProductosCache({ force }),
                cargarBodegasCache({ force }),
                cargarLotesCache({ force }),
            ]);

            if (movimientosResponse.status === 'fulfilled') {
                setMovimientos(movimientosResponse.value.data || []);
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

    const lotesDelProducto = useMemo(() => {
        if (!form.producto_id) {
            return lotes;
        }

        return lotes.filter((lote) => Number(lote.producto_id) === Number(form.producto_id));
    }, [lotes, form.producto_id]);

    const movimientosFiltrados = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return movimientos.filter((movimiento) => {
            const coincideTipo = filtroTipo ? movimiento.tipo === filtroTipo : true;

            const coincideBusqueda = !term || [
                movimiento.tipo,
                movimiento.referencia,
                movimiento.motivo,
                movimiento.observacion,
                getProductoNombre(movimiento),
                getBodegaOrigen(movimiento),
                getBodegaDestino(movimiento),
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideTipo && coincideBusqueda;
        });
    }, [movimientos, filtroTipo, busqueda]);

    const handleChange = (event) => {
        const { name, value } = event.target;

        setForm((current) => {
            const next = {
                ...current,
                [name]: value,
            };

            if (name === 'tipo') {
                if (!necesitaOrigen(value)) {
                    next.bodega_origen_id = '';
                }

                if (!necesitaDestino(value)) {
                    next.bodega_destino_id = '';
                }

                if (!necesitaCosto(value)) {
                    next.costo_unitario = '';
                }
            }

            if (name === 'producto_id') {
                next.lote_id = '';
            }

            return next;
        });
    };

    const limpiarFormulario = () => {
        setForm(initialForm);
        setError(null);
    };

    const guardarMovimiento = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            const payload = {
                tipo: form.tipo,
                producto_id: Number(form.producto_id),
                cantidad: Number(form.cantidad),
                referencia: form.referencia || null,
                motivo: form.motivo || null,
                observacion: form.observacion || null,
            };

            if (necesitaOrigen(form.tipo)) {
                payload.bodega_origen_id = Number(form.bodega_origen_id);
            }

            if (necesitaDestino(form.tipo)) {
                payload.bodega_destino_id = Number(form.bodega_destino_id);
            }

            if (necesitaCosto(form.tipo)) {
                payload.costo_unitario = Number(form.costo_unitario || 0);
            }

            if (form.lote_id) {
                payload.lote_id = Number(form.lote_id);
            }

            await inventarioApi.movimientos.registrar(payload);

            await Swal.fire({
                icon: 'success',
                title: 'Movimiento registrado',
                text: 'El movimiento fue registrado correctamente y el Kardex fue actualizado.',
                confirmButtonColor: '#10b981',
            });

            invalidarProductos();
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
        return <LoadingState text="Cargando movimientos de inventario..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Movimientos de Inventario"
                description="Registra entradas, salidas, traspasos y ajustes. Estos movimientos alimentan el Kardex, stock, PMP, lotes y valorización."
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarDatos(true)}>
                            <i className="fas fa-rotate-right"></i>
                            Actualizar
                        </SecondaryButton>

                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)}>
                            <i className={mostrarFormulario ? 'fas fa-xmark' : 'fas fa-plus'}></i>
                            {mostrarFormulario ? 'Cerrar formulario' : 'Nuevo movimiento'}
                        </PrimaryButton>
                    </>
                )}
            />

            <AlertBox tone="blue">
                Los tipos de movimiento se envían en minúscula al backend: entrada, salida, traspaso, ajuste_positivo y ajuste_negativo.
            </AlertBox>

            {mostrarFormulario && (
                <Panel
                    title="Registrar movimiento"
                    subtitle="La operación modifica stock real y genera trazabilidad en Kardex."
                >
                    <ErrorNotice error={error} />

                    <form onSubmit={guardarMovimiento} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                        <Field label="Tipo de movimiento">
                            <select
                                name="tipo"
                                value={form.tipo}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                required
                            >
                                {tiposMovimiento.map((tipo) => (
                                    <option key={tipo.value} value={tipo.value}>
                                        {tipo.label}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Producto">
                            <select
                                name="producto_id"
                                value={form.producto_id}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                required
                            >
                                <option value="">Seleccionar producto</option>
                                {productos.map((producto) => (
                                    <option key={producto.id} value={producto.id}>
                                        {producto.sku ? `${producto.sku} - ${producto.nombre}` : producto.nombre}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        {necesitaOrigen(form.tipo) && (
                            <Field label="Bodega origen">
                                <select
                                    name="bodega_origen_id"
                                    value={form.bodega_origen_id}
                                    onChange={handleChange}
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                    required
                                >
                                    <option value="">Seleccionar origen</option>
                                    {bodegas.map((bodega) => (
                                        <option key={bodega.id} value={bodega.id}>
                                            {bodega.codigo ? `${bodega.codigo} - ${bodega.nombre}` : bodega.nombre}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}

                        {necesitaDestino(form.tipo) && (
                            <Field label="Bodega destino">
                                <select
                                    name="bodega_destino_id"
                                    value={form.bodega_destino_id}
                                    onChange={handleChange}
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                    required
                                >
                                    <option value="">Seleccionar destino</option>
                                    {bodegas.map((bodega) => (
                                        <option key={bodega.id} value={bodega.id}>
                                            {bodega.codigo ? `${bodega.codigo} - ${bodega.nombre}` : bodega.nombre}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}

                        <Field label="Lote opcional">
                            <select
                                name="lote_id"
                                value={form.lote_id}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            >
                                <option value="">Sin lote</option>
                                {lotesDelProducto.map((lote) => (
                                    <option key={lote.id} value={lote.id}>
                                        {lote.codigo_lote || lote.codigo || `Lote #${lote.id}`}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Cantidad">
                            <input
                                type="number"
                                name="cantidad"
                                min="1"
                                step="1"
                                value={form.cantidad}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                required
                            />
                        </Field>

                        {necesitaCosto(form.tipo) && (
                            <Field label="Costo unitario">
                                <input
                                    type="number"
                                    name="costo_unitario"
                                    min="0"
                                    step="1"
                                    value={form.costo_unitario}
                                    onChange={handleChange}
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                    required
                                />
                            </Field>
                        )}

                        <Field label="Referencia">
                            <input
                                type="text"
                                name="referencia"
                                value={form.referencia}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="ENT-DEMO-001"
                            />
                        </Field>

                        <Field label="Motivo">
                            <input
                                type="text"
                                name="motivo"
                                value={form.motivo}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="compra, venta, ajuste..."
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
                                    placeholder="Detalle operacional del movimiento"
                                />
                            </Field>
                        </div>

                        <div className="md:col-span-2 xl:col-span-4 flex flex-wrap justify-end gap-3">
                            <SecondaryButton type="button" onClick={limpiarFormulario}>
                                Limpiar
                            </SecondaryButton>

                            <PrimaryButton type="submit" disabled={saving}>
                                <i className="fas fa-save"></i>
                                {saving ? 'Registrando...' : 'Registrar movimiento'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Historial de movimientos"
                subtitle="Registro operacional que alimenta Kardex y stock."
                actions={(
                    <div className="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                        <select
                            value={filtroTipo}
                            onChange={(event) => setFiltroTipo(event.target.value)}
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todos los tipos</option>
                            {tiposMovimiento.map((tipo) => (
                                <option key={tipo.value} value={tipo.value}>
                                    {tipo.label}
                                </option>
                            ))}
                        </select>

                        <input
                            type="text"
                            value={busqueda}
                            onChange={(event) => setBusqueda(event.target.value)}
                            className="w-full md:w-80 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            placeholder="Buscar producto, bodega o referencia..."
                        />
                    </div>
                )}
            >
                {movimientosFiltrados.length === 0 ? (
                    <EmptyState
                        title="Sin movimientos"
                        description="Registra una entrada inicial para comenzar a operar el inventario."
                        icon="fas fa-arrows-rotate"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Fecha</Th>
                                <Th>Tipo</Th>
                                <Th>Producto</Th>
                                <Th>Origen</Th>
                                <Th>Destino</Th>
                                <Th align="right">Cantidad</Th>
                                <Th align="right">Costo</Th>
                                <Th>Referencia</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {movimientosFiltrados.map((movimiento) => (
                                <tr key={movimiento.id} className="hover:bg-slate-50/70 transition-colors">
                                    <Td className="text-slate-500 font-semibold">
                                        {formatDate(movimiento.fecha_movimiento || movimiento.created_at)}
                                    </Td>

                                    <Td>
                                        <span className="inline-flex px-2.5 py-1 rounded-full text-[11px] font-black uppercase border bg-emerald-50 text-emerald-700 border-emerald-200">
                                            {String(movimiento.tipo || '-').replaceAll('_', ' ')}
                                        </span>
                                    </Td>

                                    <Td className="font-black text-slate-800">
                                        {getProductoNombre(movimiento)}
                                    </Td>

                                    <Td className="font-semibold text-slate-500">
                                        {getBodegaOrigen(movimiento)}
                                    </Td>

                                    <Td className="font-semibold text-slate-500">
                                        {getBodegaDestino(movimiento)}
                                    </Td>

                                    <Td align="right" className="font-black text-slate-800">
                                        {formatNumber(movimiento.cantidad, 2)}
                                    </Td>

                                    <Td align="right" className="font-black text-slate-700">
                                        {formatCurrency(movimiento.costo_unitario || 0)}
                                    </Td>

                                    <Td className="text-slate-500">
                                        {movimiento.referencia || '-'}
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

export default MovimientosInventario;