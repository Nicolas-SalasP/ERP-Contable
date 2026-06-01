import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    AlertBox,
    EmptyState,
    ErrorNotice,
    EstadoBadge,
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
    StatCard,
    TableShell,
    Td,
    Th,
} from '../Componentes/InventarioUI';

const initialForm = {
    bodega_id: '',
    prioridad: 'NORMAL',
    referencia: '',
    motivo: 'picking_interno',
    observacion: '',
    producto_id: '',
    cantidad: '',
};

const inputClass = 'w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none';

const PickingInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [ordenes, setOrdenes] = useState([]);
    const [estadoFiltro, setEstadoFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState(initialForm);

    const {
        productos,
        bodegas,
        cargarProductosCache,
        cargarBodegasCache,
        invalidarTodoInventario,
    } = useInventarioData();

    const cargarDatos = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            await Promise.all([
                cargarProductosCache({ force }),
                cargarBodegasCache({ force }),
            ]);

            const response = await inventarioApi.picking.listar({ per_page: 100 });
            setOrdenes(Array.isArray(response.data) ? response.data : []);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const ordenesFiltradas = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return ordenes.filter((orden) => {
            const coincideEstado = estadoFiltro ? orden.estado === estadoFiltro : true;
            const coincideBusqueda = !term || [
                orden.codigo,
                orden.estado,
                orden.prioridad,
                orden.referencia,
                orden.motivo,
                orden.observacion,
                orden.bodega?.nombre,
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideEstado && coincideBusqueda;
        });
    }, [ordenes, estadoFiltro, busqueda]);

    const resumen = useMemo(() => ({
        total: ordenes.length,
        pendientes: ordenes.filter((item) => item.estado === 'PENDIENTE').length,
        preparacion: ordenes.filter((item) => item.estado === 'EN_PREPARACION').length,
        diferencias: ordenes.filter((item) => item.estado === 'CON_DIFERENCIAS').length,
    }), [ordenes]);

    const handleChange = (event) => {
        const { name, value } = event.target;
        setForm((current) => ({ ...current, [name]: value }));
    };

    const crearOrden = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            await inventarioApi.picking.crear({
                bodega_id: Number(form.bodega_id),
                prioridad: form.prioridad,
                referencia: form.referencia || null,
                motivo: form.motivo || 'picking_interno',
                observacion: form.observacion || null,
                detalles: [{
                    producto_id: Number(form.producto_id),
                    cantidad: Number(form.cantidad),
                }],
            });

            await Swal.fire({
                icon: 'success',
                title: 'Picking creado',
                text: 'La orden quedó pendiente de asignación sugerida. No se generó DTE/SII.',
                confirmButtonColor: '#10b981',
            });

            setForm(initialForm);
            setMostrarFormulario(false);
            await cargarDatos(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    const ejecutarAccion = async (orden, accion) => {
        const textos = {
            asignar: ['Asignar ubicación', 'Se sugerirán ubicaciones/lotes y se comprometerá stock disponible mediante reserva interna.'],
            iniciar: ['Iniciar picking', 'La orden pasará a preparación operativa.'],
            confirmar: ['Confirmar picking', 'Se confirmarán por defecto las cantidades asignadas. No se descontará stock físico.'],
            cancelar: ['Cancelar picking', 'Se liberará la reserva interna pendiente y la orden quedará cancelada.'],
        };

        const result = await Swal.fire({
            icon: accion === 'cancelar' ? 'warning' : 'question',
            title: textos[accion][0],
            text: textos[accion][1],
            showCancelButton: true,
            confirmButtonColor: accion === 'cancelar' ? '#e11d48' : '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Volver',
        });

        if (!result.isConfirmed) return;

        try {
            setSaving(true);
            setError(null);

            if (accion === 'asignar') await inventarioApi.picking.asignar(orden.id);
            if (accion === 'iniciar') await inventarioApi.picking.iniciar(orden.id);
            if (accion === 'confirmar') await inventarioApi.picking.confirmar(orden.id);
            if (accion === 'cancelar') await inventarioApi.picking.cancelar(orden.id, { observacion: 'Cancelado desde frontend operativo de bodega.' });

            invalidarTodoInventario();
            await cargarDatos(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingState text="Cargando operación de picking..." />;

    return (
        <div className="space-y-6">
            <PageHeader
                eyebrow="Inventario · Fase 14.1"
                title="Picking de Bodega"
                description="Órdenes internas de preparación con asignación sugerida multiubicación/multilote, reserva operativa y control de diferencias. Sin DTE/SII ni contabilidad automática."
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarDatos(true)} disabled={saving}>
                            <i className="fas fa-rotate"></i> Actualizar
                        </SecondaryButton>
                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)} disabled={saving}>
                            <i className="fas fa-plus"></i> Nueva orden
                        </PrimaryButton>
                    </>
                )}
            />

            <ErrorNotice error={error} />

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <StatCard title="Órdenes" value={formatNumber(resumen.total)} icon="fas fa-clipboard-list" />
                <StatCard title="Pendientes" value={formatNumber(resumen.pendientes)} icon="fas fa-hourglass-half" tone="blue" />
                <StatCard title="En preparación" value={formatNumber(resumen.preparacion)} icon="fas fa-person-dolly" tone="amber" />
                <StatCard title="Con diferencias" value={formatNumber(resumen.diferencias)} icon="fas fa-triangle-exclamation" tone="rose" />
            </div>

            <AlertBox tone="blue">
                Picking compromete stock disponible mediante reserva interna y puede dividir una línea entre varias ubicaciones/lotes cuando el stock está fragmentado. La salida real y cualquier documento tributario quedan fuera de esta fase.
            </AlertBox>

            {mostrarFormulario && (
                <Panel title="Crear orden interna de picking" subtitle="Versión básica: un producto por orden. Puedes ampliar a múltiples líneas desde API.">
                    <form onSubmit={crearOrden} className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Field label="Bodega">
                            <select name="bodega_id" value={form.bodega_id} onChange={handleChange} className={inputClass} required>
                                <option value="">Seleccionar</option>
                                {bodegas.map((bodega) => <option key={bodega.id} value={bodega.id}>{bodega.codigo} · {bodega.nombre}</option>)}
                            </select>
                        </Field>
                        <Field label="Producto">
                            <select name="producto_id" value={form.producto_id} onChange={handleChange} className={inputClass} required>
                                <option value="">Seleccionar</option>
                                {productos.map((producto) => <option key={producto.id} value={producto.id}>{producto.sku} · {producto.nombre}</option>)}
                            </select>
                        </Field>
                        <Field label="Cantidad">
                            <input name="cantidad" value={form.cantidad} onChange={handleChange} className={inputClass} type="number" step="0.0001" min="0.0001" required />
                        </Field>
                        <Field label="Prioridad">
                            <select name="prioridad" value={form.prioridad} onChange={handleChange} className={inputClass}>
                                {['BAJA', 'NORMAL', 'ALTA', 'URGENTE'].map((value) => <option key={value} value={value}>{value}</option>)}
                            </select>
                        </Field>
                        <Field label="Referencia">
                            <input name="referencia" value={form.referencia} onChange={handleChange} className={inputClass} placeholder="PED-0001 / interno" />
                        </Field>
                        <Field label="Motivo">
                            <input name="motivo" value={form.motivo} onChange={handleChange} className={inputClass} />
                        </Field>
                        <div className="md:col-span-3">
                            <Field label="Observación">
                                <textarea name="observacion" value={form.observacion} onChange={handleChange} className={inputClass} rows={3} />
                            </Field>
                        </div>
                        <div className="md:col-span-3 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setMostrarFormulario(false)}>Cancelar</SecondaryButton>
                            <PrimaryButton type="submit" disabled={saving}>Guardar picking</PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Órdenes de picking"
                actions={(
                    <div className="flex flex-col sm:flex-row gap-2">
                        <input value={busqueda} onChange={(event) => setBusqueda(event.target.value)} className={inputClass} placeholder="Buscar código/referencia" />
                        <select value={estadoFiltro} onChange={(event) => setEstadoFiltro(event.target.value)} className={inputClass}>
                            <option value="">Todos</option>
                            {['PENDIENTE', 'EN_PREPARACION', 'PICKING_COMPLETO', 'CON_DIFERENCIAS', 'CANCELADO'].map((estado) => <option key={estado} value={estado}>{estado}</option>)}
                        </select>
                    </div>
                )}
            >
                {ordenesFiltradas.length === 0 ? (
                    <EmptyState title="Sin órdenes de picking" description="Crea una orden para comenzar la operación de bodega." />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Estado</Th>
                                <Th>Bodega</Th>
                                <Th>Detalle</Th>
                                <Th>Fechas</Th>
                                <Th align="right">Acciones</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {ordenesFiltradas.map((orden) => (
                                <tr key={orden.id} className="hover:bg-slate-50/60 align-top">
                                    <Td>
                                        <p className="font-black text-slate-800">{orden.codigo}</p>
                                        <p className="text-xs text-slate-500">{orden.referencia || 'Sin referencia'}</p>
                                    </Td>
                                    <Td><EstadoBadge value={orden.estado} /></Td>
                                    <Td>{getBodegaNombre(orden)}</Td>
                                    <Td>
                                        {(orden.detalles || []).map((detalle) => (
                                            <div key={detalle.id} className="mb-2 last:mb-0 text-xs text-slate-600">
                                                <p className="font-black text-slate-700">{getProductoNombre(detalle)}</p>
                                                <p>Solicitada: {formatNumber(detalle.cantidad_solicitada, 4)} · Asignada: {formatNumber(detalle.cantidad_asignada, 4)} · Pickeada: {formatNumber(detalle.cantidad_pickeada, 4)}</p>
                                                {(detalle.asignaciones || []).length > 0 ? (
                                                    <div className="mt-1 rounded-lg bg-slate-50 border border-slate-100 p-2 space-y-1">
                                                        {(detalle.asignaciones || []).map((asignacion) => (
                                                            <p key={asignacion.id}>
                                                                <span className="font-bold">{asignacion.ubicacion_origen?.codigo || asignacion.ubicacionOrigen?.codigo || '-'}</span>
                                                                {asignacion.lote?.codigo_lote ? ` · Lote ${asignacion.lote.codigo_lote}` : ''}
                                                                {' '}→ {formatNumber(asignacion.cantidad_asignada, 4)} asignadas · {formatNumber(asignacion.cantidad_pickeada, 4)} pickeadas
                                                            </p>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p>Ubicación: {detalle.ubicacion_origen?.codigo || detalle.ubicacionOrigen?.codigo || '-'}</p>
                                                )}
                                            </div>
                                        ))}
                                    </Td>
                                    <Td>
                                        <p className="text-xs text-slate-500">Creación: {formatDate(orden.fecha_creacion)}</p>
                                        <p className="text-xs text-slate-500">Confirmación: {formatDate(orden.fecha_confirmacion)}</p>
                                    </Td>
                                    <Td align="right">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <SecondaryButton onClick={() => ejecutarAccion(orden, 'asignar')} disabled={saving || !!orden.reserva_id || ['CANCELADO', 'PICKING_COMPLETO'].includes(orden.estado)}>Asignar</SecondaryButton>
                                            <SecondaryButton onClick={() => ejecutarAccion(orden, 'iniciar')} disabled={saving || !orden.reserva_id || orden.estado === 'EN_PREPARACION' || ['CANCELADO', 'PICKING_COMPLETO'].includes(orden.estado)}>Iniciar</SecondaryButton>
                                            <PrimaryButton onClick={() => ejecutarAccion(orden, 'confirmar')} disabled={saving || orden.estado !== 'EN_PREPARACION'}>Confirmar</PrimaryButton>
                                            <SecondaryButton onClick={() => ejecutarAccion(orden, 'cancelar')} disabled={saving || ['CANCELADO', 'PICKING_COMPLETO'].includes(orden.estado)}>Cancelar</SecondaryButton>
                                        </div>
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

export default PickingInventario;
