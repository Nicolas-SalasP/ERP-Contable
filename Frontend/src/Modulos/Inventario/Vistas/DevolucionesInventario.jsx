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

const inputClass = 'w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none';

const tipos = [
    'DEVOLUCION',
    'REVERSA_TOTAL',
    'REVERSA_PARCIAL',
    'DIFERENCIA_POST_DESPACHO',
];

const estados = ['PENDIENTE', 'CONFIRMADA', 'CON_DIFERENCIAS', 'CANCELADA'];

const DevolucionesInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [devoluciones, setDevoluciones] = useState([]);
    const [despachos, setDespachos] = useState([]);
    const [ubicaciones, setUbicaciones] = useState([]);
    const [reversable, setReversable] = useState(null);
    const [estadoFiltro, setEstadoFiltro] = useState('');
    const [tipoFiltro, setTipoFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState({
        despacho_orden_id: '',
        tipo: 'DEVOLUCION',
        motivo: 'devolucion_post_despacho',
        referencia: '',
        observacion: '',
        ubicacion_destino_id: '',
        detalles: [],
    });

    const { invalidarTodoInventario } = useInventarioData();

    const cargarDatos = async () => {
        try {
            setLoading(true);
            setError(null);

            const [devolucionesResponse, despachosResponse, ubicacionesResponse] = await Promise.all([
                inventarioApi.devoluciones.listar({ per_page: 100 }),
                inventarioApi.despachos.listar({ per_page: 100 }),
                inventarioApi.ubicaciones.listar({ per_page: 200 }),
            ]);

            setDevoluciones(Array.isArray(devolucionesResponse.data) ? devolucionesResponse.data : []);
            setDespachos(Array.isArray(despachosResponse.data) ? despachosResponse.data : []);
            setUbicaciones(Array.isArray(ubicacionesResponse.data) ? ubicacionesResponse.data : []);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const despachosReversables = useMemo(
        () => despachos.filter((orden) => ['DESPACHADO', 'CON_DIFERENCIAS'].includes(orden.estado)),
        [despachos],
    );

    const devolucionesFiltradas = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return devoluciones.filter((orden) => {
            const coincideEstado = estadoFiltro ? orden.estado === estadoFiltro : true;
            const coincideTipo = tipoFiltro ? orden.tipo === tipoFiltro : true;
            const coincideBusqueda = !term || [
                orden.codigo,
                orden.tipo,
                orden.estado,
                orden.referencia,
                orden.motivo,
                orden.observacion,
                orden.despacho?.codigo,
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideEstado && coincideTipo && coincideBusqueda;
        });
    }, [devoluciones, estadoFiltro, tipoFiltro, busqueda]);

    const resumen = useMemo(() => ({
        total: devoluciones.length,
        pendientes: devoluciones.filter((item) => item.estado === 'PENDIENTE').length,
        confirmadas: devoluciones.filter((item) => item.estado === 'CONFIRMADA').length,
        diferencias: devoluciones.filter((item) => item.estado === 'CON_DIFERENCIAS').length,
    }), [devoluciones]);

    const ubicacionesPorBodega = useMemo(() => {
        const map = new Map();
        ubicaciones.forEach((ubicacion) => {
            const key = Number(ubicacion.bodega_id);
            map.set(key, [...(map.get(key) || []), ubicacion]);
        });
        return map;
    }, [ubicaciones]);

    const consultarReversable = async (despachoId, tipo = form.tipo, ubicacionDestinoId = form.ubicacion_destino_id) => {
        if (!despachoId) {
            setReversable(null);
            setForm((current) => ({ ...current, despacho_orden_id: '', detalles: [] }));
            return;
        }

        try {
            setSaving(true);
            setError(null);

            const response = await inventarioApi.devoluciones.reversable(despachoId);
            const data = response.data || null;
            const detalles = (data?.detalles || [])
                .filter((detalle) => Number(detalle.cantidad_reversable || 0) > 0)
                .map((detalle) => ({
                    despacho_detalle_id: detalle.despacho_detalle_id,
                    producto_id: detalle.producto_id,
                    producto: detalle.producto,
                    bodega_id: detalle.bodega_id,
                    ubicacion_origen_id: detalle.ubicacion_origen_id,
                    lote_id: detalle.lote_id,
                    cantidad_reversable: Number(detalle.cantidad_reversable || 0),
                    cantidad_devolver: tipo === 'REVERSA_TOTAL' ? Number(detalle.cantidad_reversable || 0) : '',
                    ubicacion_destino_id: ubicacionDestinoId || detalle.ubicacion_origen_id || '',
                    observacion: '',
                }));

            setReversable(data);
            setForm((current) => ({
                ...current,
                despacho_orden_id: despachoId,
                tipo,
                detalles,
            }));
        } catch (err) {
            setError(err?.response?.data || err);
            setReversable(null);
            setForm((current) => ({ ...current, detalles: [] }));
        } finally {
            setSaving(false);
        }
    };

    const actualizarTipo = (tipo) => {
        const motivo = {
            DEVOLUCION: 'devolucion_post_despacho',
            REVERSA_TOTAL: 'reversa_total_despacho',
            REVERSA_PARCIAL: 'reversa_parcial_despacho',
            DIFERENCIA_POST_DESPACHO: 'diferencia_post_despacho',
        }[tipo] || 'devolucion_post_despacho';

        setForm((current) => ({ ...current, tipo, motivo }));

        if (form.despacho_orden_id) {
            consultarReversable(form.despacho_orden_id, tipo, form.ubicacion_destino_id);
        }
    };

    const actualizarUbicacionGlobal = (ubicacionId) => {
        setForm((current) => ({
            ...current,
            ubicacion_destino_id: ubicacionId,
            detalles: current.detalles.map((detalle) => ({ ...detalle, ubicacion_destino_id: ubicacionId || detalle.ubicacion_origen_id || '' })),
        }));
    };

    const actualizarDetalle = (index, cambios) => {
        setForm((current) => ({
            ...current,
            detalles: current.detalles.map((detalle, detalleIndex) => (
                detalleIndex === index ? { ...detalle, ...cambios } : detalle
            )),
        }));
    };

    const crearDevolucion = async (event) => {
        event.preventDefault();

        const detallesPayload = form.tipo === 'REVERSA_TOTAL'
            ? []
            : form.detalles
                .filter((detalle) => Number(detalle.cantidad_devolver || 0) > 0)
                .map((detalle) => ({
                    despacho_detalle_id: detalle.despacho_detalle_id,
                    cantidad_devolver: Number(detalle.cantidad_devolver || 0),
                    ubicacion_destino_id: detalle.ubicacion_destino_id || null,
                    observacion: detalle.observacion || null,
                }));

        try {
            setSaving(true);
            setError(null);

            await inventarioApi.devoluciones.crear({
                despacho_orden_id: Number(form.despacho_orden_id),
                tipo: form.tipo,
                motivo: form.motivo || null,
                referencia: form.referencia || null,
                observacion: form.observacion || null,
                ubicacion_destino_id: form.ubicacion_destino_id || null,
                detalles: detallesPayload,
            });

            await Swal.fire({
                icon: 'success',
                title: 'Devolución/reversa creada',
                text: 'La orden quedó pendiente de confirmación logística. No se emitió DTE/SII ni asiento contable automático.',
                confirmButtonColor: '#10b981',
            });

            setMostrarFormulario(false);
            setReversable(null);
            setForm({ despacho_orden_id: '', tipo: 'DEVOLUCION', motivo: 'devolucion_post_despacho', referencia: '', observacion: '', ubicacion_destino_id: '', detalles: [] });
            await cargarDatos();
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    const ejecutarAccion = async (orden, accion) => {
        const result = await Swal.fire({
            icon: accion === 'cancelar' ? 'warning' : 'question',
            title: accion === 'cancelar' ? 'Cancelar devolución/reversa' : 'Confirmar devolución/reversa',
            text: accion === 'cancelar'
                ? 'Solo se puede cancelar una orden pendiente. No se moverá stock.'
                : 'Se reingresará stock físico para cantidades aceptadas y se registrará movimiento/kardex no tributario.',
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

            if (accion === 'confirmar') await inventarioApi.devoluciones.confirmar(orden.id);
            if (accion === 'cancelar') await inventarioApi.devoluciones.cancelar(orden.id, { observacion: 'Cancelado desde frontend operativo de inventario.' });

            invalidarTodoInventario();
            await cargarDatos();
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingState text="Cargando devoluciones y reversas post-despacho..." />;

    return (
        <div className="space-y-6">
            <PageHeader
                eyebrow="Inventario · Fase 16"
                title="Devoluciones, reversas y diferencias post-despacho"
                description="Control logístico posterior a despacho interno: reingreso físico, reversa total/parcial y diferencias operativas sin DTE/SII ni contabilidad automática."
                actions={(
                    <>
                        <SecondaryButton onClick={cargarDatos} disabled={saving}>
                            <i className="fas fa-rotate"></i> Actualizar
                        </SecondaryButton>
                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)} disabled={saving}>
                            <i className="fas fa-rotate-left"></i> Nueva devolución/reversa
                        </PrimaryButton>
                    </>
                )}
            />

            <ErrorNotice error={error} />

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <StatCard title="Órdenes" value={formatNumber(resumen.total)} icon="fas fa-rotate-left" />
                <StatCard title="Pendientes" value={formatNumber(resumen.pendientes)} icon="fas fa-hourglass-half" tone="blue" />
                <StatCard title="Confirmadas" value={formatNumber(resumen.confirmadas)} icon="fas fa-circle-check" tone="emerald" />
                <StatCard title="Con diferencias" value={formatNumber(resumen.diferencias)} icon="fas fa-triangle-exclamation" tone="rose" />
            </div>

            <AlertBox tone="emerald">
                Fase 16 se apoya en despachos DESPACHADOS o CON_DIFERENCIAS. Las cantidades reversables se calculan contra lo realmente despachado menos devoluciones confirmadas; no se crean guías, facturas, folios ni XML tributarios.
            </AlertBox>

            {mostrarFormulario && (
                <Panel title="Crear devolución/reversa desde despacho confirmado" subtitle="Consulta el saldo reversable antes de crear la orden post-despacho.">
                    <form onSubmit={crearDevolucion} className="space-y-5">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <Field label="Despacho confirmado">
                                <select
                                    className={inputClass}
                                    value={form.despacho_orden_id}
                                    onChange={(event) => consultarReversable(event.target.value)}
                                    required
                                >
                                    <option value="">Seleccionar</option>
                                    {despachosReversables.map((orden) => (
                                        <option key={orden.id} value={orden.id}>{orden.codigo} · {orden.estado}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Tipo">
                                <select className={inputClass} value={form.tipo} onChange={(event) => actualizarTipo(event.target.value)} required>
                                    {tipos.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                                </select>
                            </Field>
                            <Field label="Ubicación destino global">
                                <select className={inputClass} value={form.ubicacion_destino_id} onChange={(event) => actualizarUbicacionGlobal(event.target.value)}>
                                    <option value="">Usar ubicación origen / por detalle</option>
                                    {ubicaciones.map((ubicacion) => <option key={ubicacion.id} value={ubicacion.id}>{ubicacion.codigo} · {ubicacion.nombre}</option>)}
                                </select>
                            </Field>
                            <Field label="Referencia">
                                <input className={inputClass} value={form.referencia} onChange={(event) => setForm((current) => ({ ...current, referencia: event.target.value }))} placeholder="Referencia interna" />
                            </Field>
                            <Field label="Motivo">
                                <input className={inputClass} value={form.motivo} onChange={(event) => setForm((current) => ({ ...current, motivo: event.target.value }))} required />
                            </Field>
                            <div className="md:col-span-3">
                                <Field label="Observación">
                                    <input className={inputClass} value={form.observacion} onChange={(event) => setForm((current) => ({ ...current, observacion: event.target.value }))} placeholder="Trazabilidad logística" />
                                </Field>
                            </div>
                        </div>

                        {reversable && (
                            <div className="rounded-2xl border border-slate-200 overflow-hidden">
                                <div className="bg-slate-50 px-4 py-3 text-sm font-black text-slate-700 flex justify-between gap-3">
                                    <span>Detalles reversables · Total: {formatNumber(reversable.total_reversable, 4)}</span>
                                    <span>{reversable.despacho?.codigo}</span>
                                </div>
                                {form.detalles.length === 0 ? (
                                    <div className="p-4"><EmptyState title="Sin saldo reversable" description="El despacho seleccionado no tiene cantidades pendientes de reversar." icon="fas fa-box-open" /></div>
                                ) : (
                                    <TableShell>
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <Th>Producto</Th>
                                                <Th align="right">Reversable</Th>
                                                <Th>Cantidad</Th>
                                                <Th>Ubicación destino</Th>
                                                <Th>Observación</Th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {form.detalles.map((detalle, index) => (
                                                <tr key={detalle.despacho_detalle_id} className="align-top">
                                                    <Td>
                                                        <p className="font-black text-slate-700">{getProductoNombre(detalle)}</p>
                                                        <p className="text-xs text-slate-500">Detalle despacho #{detalle.despacho_detalle_id}</p>
                                                    </Td>
                                                    <Td align="right">{formatNumber(detalle.cantidad_reversable, 4)}</Td>
                                                    <Td>
                                                        <input
                                                            className={inputClass}
                                                            type="number"
                                                            min="0"
                                                            max={detalle.cantidad_reversable}
                                                            step="0.0001"
                                                            value={form.tipo === 'REVERSA_TOTAL' ? detalle.cantidad_reversable : detalle.cantidad_devolver}
                                                            onChange={(event) => actualizarDetalle(index, { cantidad_devolver: event.target.value })}
                                                            disabled={form.tipo === 'REVERSA_TOTAL'}
                                                        />
                                                    </Td>
                                                    <Td>
                                                        <select className={inputClass} value={detalle.ubicacion_destino_id || ''} onChange={(event) => actualizarDetalle(index, { ubicacion_destino_id: event.target.value })}>
                                                            <option value="">Seleccionar</option>
                                                            {(ubicacionesPorBodega.get(Number(detalle.bodega_id)) || []).map((ubicacion) => (
                                                                <option key={ubicacion.id} value={ubicacion.id}>{ubicacion.codigo} · {ubicacion.nombre}</option>
                                                            ))}
                                                        </select>
                                                    </Td>
                                                    <Td>
                                                        <input className={inputClass} value={detalle.observacion} onChange={(event) => actualizarDetalle(index, { observacion: event.target.value })} placeholder="Detalle" />
                                                    </Td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </TableShell>
                                )}
                            </div>
                        )}

                        <div className="flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setMostrarFormulario(false)} disabled={saving}>Cancelar</SecondaryButton>
                            <PrimaryButton type="submit" disabled={saving || !form.despacho_orden_id || !form.detalles.length}>Crear orden</PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Órdenes post-despacho"
                actions={(
                    <div className="flex flex-col sm:flex-row gap-2">
                        <input value={busqueda} onChange={(event) => setBusqueda(event.target.value)} className={inputClass} placeholder="Buscar devolución/reversa" />
                        <select value={tipoFiltro} onChange={(event) => setTipoFiltro(event.target.value)} className={inputClass}>
                            <option value="">Todos los tipos</option>
                            {tipos.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                        </select>
                        <select value={estadoFiltro} onChange={(event) => setEstadoFiltro(event.target.value)} className={inputClass}>
                            <option value="">Todos los estados</option>
                            {estados.map((estado) => <option key={estado} value={estado}>{estado}</option>)}
                        </select>
                    </div>
                )}
            >
                {devolucionesFiltradas.length === 0 ? (
                    <EmptyState title="Sin devoluciones/reversas" description="Crea una orden post-despacho desde un despacho confirmado." icon="fas fa-rotate-left" />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Estado</Th>
                                <Th>Despacho</Th>
                                <Th>Detalle</Th>
                                <Th>Fechas</Th>
                                <Th align="right">Acciones</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {devolucionesFiltradas.map((orden) => (
                                <tr key={orden.id} className="hover:bg-slate-50/60 align-top">
                                    <Td>
                                        <p className="font-black text-slate-800">{orden.codigo}</p>
                                        <p className="text-xs text-slate-500">{orden.tipo}</p>
                                        <p className="text-xs text-slate-500">{orden.motivo}</p>
                                    </Td>
                                    <Td><EstadoBadge value={orden.estado} /></Td>
                                    <Td>
                                        <p className="font-black text-slate-700">{orden.despacho?.codigo || `#${orden.despacho_orden_id}`}</p>
                                        <p className="text-xs text-slate-500">{orden.referencia || 'Sin referencia'}</p>
                                    </Td>
                                    <Td>
                                        {(orden.detalles || []).map((detalle) => (
                                            <div key={detalle.id} className="mb-2 last:mb-0 text-xs text-slate-600">
                                                <p className="font-black text-slate-700">{getProductoNombre(detalle)}</p>
                                                <p>Solicitada: {formatNumber(detalle.cantidad_devolver, 4)} · Aceptada: {formatNumber(detalle.cantidad_aceptada, 4)} · Rechazada: {formatNumber(detalle.cantidad_rechazada, 4)}</p>
                                                <p>Destino: {detalle.ubicacion_destino?.codigo || detalle.ubicacionDestino?.codigo || '-'}</p>
                                                <p><EstadoBadge value={detalle.estado} /></p>
                                            </div>
                                        ))}
                                    </Td>
                                    <Td>
                                        <p className="text-xs text-slate-500">Creación: {formatDate(orden.fecha_creacion)}</p>
                                        <p className="text-xs text-slate-500">Confirmación: {formatDate(orden.fecha_confirmacion)}</p>
                                        <p className="text-xs text-slate-500">Cancelación: {formatDate(orden.fecha_cancelacion)}</p>
                                    </Td>
                                    <Td align="right">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <PrimaryButton onClick={() => ejecutarAccion(orden, 'confirmar')} disabled={saving || orden.estado !== 'PENDIENTE'}>Confirmar</PrimaryButton>
                                            <SecondaryButton onClick={() => ejecutarAccion(orden, 'cancelar')} disabled={saving || orden.estado !== 'PENDIENTE'}>Cancelar</SecondaryButton>
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

export default DevolucionesInventario;
