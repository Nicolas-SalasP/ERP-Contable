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

const DespachosInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [despachos, setDespachos] = useState([]);
    const [packing, setPacking] = useState([]);
    const [estadoFiltro, setEstadoFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState({ packing_orden_id: '', referencia: '', motivo: 'despacho_interno', observacion: '' });

    const { invalidarTodoInventario } = useInventarioData();

    const cargarDatos = async () => {
        try {
            setLoading(true);
            setError(null);

            const [despachosResponse, packingResponse] = await Promise.all([
                inventarioApi.despachos.listar({ per_page: 100 }),
                inventarioApi.packing.listar({ per_page: 100 }),
            ]);

            setDespachos(Array.isArray(despachosResponse.data) ? despachosResponse.data : []);
            setPacking(Array.isArray(packingResponse.data) ? packingResponse.data : []);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const packingListoParaDespacho = useMemo(() => {
        const usados = new Set(despachos.map((orden) => Number(orden.packing_orden_id)));
        return packing.filter((orden) => orden.estado === 'EMPACADO' && !usados.has(Number(orden.id)));
    }, [packing, despachos]);

    const despachosFiltrados = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return despachos.filter((orden) => {
            const coincideEstado = estadoFiltro ? orden.estado === estadoFiltro : true;
            const coincideBusqueda = !term || [
                orden.codigo,
                orden.estado,
                orden.referencia,
                orden.motivo,
                orden.observacion,
                orden.packing_orden?.codigo,
                orden.packingOrden?.codigo,
                orden.picking_orden?.codigo,
                orden.pickingOrden?.codigo,
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideEstado && coincideBusqueda;
        });
    }, [despachos, estadoFiltro, busqueda]);

    const resumen = useMemo(() => ({
        total: despachos.length,
        pendientes: despachos.filter((item) => item.estado === 'PENDIENTE').length,
        enDespacho: despachos.filter((item) => item.estado === 'EN_DESPACHO').length,
        diferencias: despachos.filter((item) => item.estado === 'CON_DIFERENCIAS').length,
    }), [despachos]);

    const crearDespacho = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            await inventarioApi.despachos.crear({
                packing_orden_id: Number(form.packing_orden_id),
                referencia: form.referencia || null,
                motivo: form.motivo || 'despacho_interno',
                observacion: form.observacion || null,
            });

            await Swal.fire({
                icon: 'success',
                title: 'Despacho generado',
                text: 'La orden de despacho interno fue creada desde packing empacado. No se generó documento tributario.',
                confirmButtonColor: '#10b981',
            });

            setForm({ packing_orden_id: '', referencia: '', motivo: 'despacho_interno', observacion: '' });
            setMostrarFormulario(false);
            await cargarDatos();
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    const confirmarConCantidades = async (orden) => {
        const detalles = orden.detalles || [];
        const html = detalles.map((detalle, index) => `
            <div style="text-align:left;margin-bottom:12px">
                <label style="font-weight:800;font-size:12px;color:#334155;display:block;margin-bottom:4px">
                    ${detalle.producto?.nombre || `Producto #${detalle.producto_id}`} · Empacado: ${formatNumber(detalle.cantidad_empacada, 4)}
                </label>
                <input id="cantidad-desp-${detalle.id}" type="number" min="0" step="0.0001" value="${Number(detalle.cantidad_empacada || 0)}" class="swal2-input" style="margin:0;width:100%" />
                <input id="obs-desp-${detalle.id}" type="text" placeholder="Observación detalle ${index + 1}" class="swal2-input" style="margin:6px 0 0;width:100%" />
            </div>
        `).join('');

        const result = await Swal.fire({
            icon: 'question',
            title: 'Confirmar salida física',
            html: `<p style="font-size:13px;color:#64748b;margin-bottom:14px">Puedes confirmar total o informar diferencias. No se emite DTE/SII.</p>${html}`,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Confirmar despacho',
            cancelButtonText: 'Volver',
            preConfirm: () => detalles.map((detalle) => ({
                id: detalle.id,
                cantidad_despachada: Number(document.getElementById(`cantidad-desp-${detalle.id}`)?.value || 0),
                observacion: document.getElementById(`obs-desp-${detalle.id}`)?.value || null,
            })),
        });

        if (!result.isConfirmed) return;

        await ejecutarAccion(orden, 'confirmar', { detalles: result.value });
    };

    const ejecutarAccion = async (orden, accion, payload = {}) => {
        const textos = {
            iniciar: ['Iniciar despacho', 'La orden pasará a salida logística controlada.'],
            confirmar: ['Confirmar despacho', 'Se descontará stock físico y se consumirá/liberará la reserva interna según cantidades.'],
            cancelar: ['Cancelar despacho', 'La orden de despacho quedará cancelada. No se libera picking ni packing.'],
        };

        if (accion !== 'confirmar') {
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
        }

        try {
            setSaving(true);
            setError(null);

            if (accion === 'iniciar') await inventarioApi.despachos.iniciar(orden.id);
            if (accion === 'confirmar') await inventarioApi.despachos.confirmar(orden.id, payload);
            if (accion === 'cancelar') await inventarioApi.despachos.cancelar(orden.id, { observacion: 'Cancelado desde frontend operativo de bodega.' });

            invalidarTodoInventario();
            await cargarDatos();
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingState text="Cargando despachos internos..." />;

    return (
        <div className="space-y-6">
            <PageHeader
                eyebrow="Inventario · Fase 15"
                title="Despacho interno"
                description="Salida logística controlada desde packing empacado. Descuenta stock físico y consume/libera reservas sin facturación, DTE/SII ni contabilidad automática."
                actions={(
                    <>
                        <SecondaryButton onClick={cargarDatos} disabled={saving}>
                            <i className="fas fa-rotate"></i> Actualizar
                        </SecondaryButton>
                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)} disabled={saving}>
                            <i className="fas fa-truck-ramp-box"></i> Generar despacho
                        </PrimaryButton>
                    </>
                )}
            />

            <ErrorNotice error={error} />

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <StatCard title="Despachos" value={formatNumber(resumen.total)} icon="fas fa-truck-ramp-box" />
                <StatCard title="Pendientes" value={formatNumber(resumen.pendientes)} icon="fas fa-hourglass-half" tone="blue" />
                <StatCard title="En despacho" value={formatNumber(resumen.enDespacho)} icon="fas fa-dolly" tone="amber" />
                <StatCard title="Con diferencias" value={formatNumber(resumen.diferencias)} icon="fas fa-triangle-exclamation" tone="rose" />
            </div>

            <AlertBox tone="emerald">
                Despacho solo nace desde packing EMPACADO. La confirmación registra salida interna, movimiento/kardex y consumo/liberación de reserva; no emite guías, facturas, folios, XML ni integración SII.
            </AlertBox>

            {mostrarFormulario && (
                <Panel title="Generar despacho desde packing empacado" subtitle="Selecciona un packing finalizado y aún no despachado.">
                    <form onSubmit={crearDespacho} className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <Field label="Packing empacado">
                            <select className={inputClass} value={form.packing_orden_id} onChange={(event) => setForm((current) => ({ ...current, packing_orden_id: event.target.value }))} required>
                                <option value="">Seleccionar</option>
                                {packingListoParaDespacho.map((orden) => <option key={orden.id} value={orden.id}>{orden.codigo} · {orden.estado}</option>)}
                            </select>
                        </Field>
                        <Field label="Referencia">
                            <input className={inputClass} value={form.referencia} onChange={(event) => setForm((current) => ({ ...current, referencia: event.target.value }))} placeholder="Pedido / referencia interna" />
                        </Field>
                        <Field label="Motivo">
                            <input className={inputClass} value={form.motivo} onChange={(event) => setForm((current) => ({ ...current, motivo: event.target.value }))} placeholder="despacho_interno" />
                        </Field>
                        <Field label="Observación">
                            <input className={inputClass} value={form.observacion} onChange={(event) => setForm((current) => ({ ...current, observacion: event.target.value }))} placeholder="Observación interna" />
                        </Field>
                        <div className="md:col-span-4 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setMostrarFormulario(false)}>Cancelar</SecondaryButton>
                            <PrimaryButton type="submit" disabled={saving || !packingListoParaDespacho.length}>Crear despacho</PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Órdenes de despacho"
                actions={(
                    <div className="flex flex-col sm:flex-row gap-2">
                        <input value={busqueda} onChange={(event) => setBusqueda(event.target.value)} className={inputClass} placeholder="Buscar despacho/packing" />
                        <select value={estadoFiltro} onChange={(event) => setEstadoFiltro(event.target.value)} className={inputClass}>
                            <option value="">Todos</option>
                            {['PENDIENTE', 'EN_DESPACHO', 'DESPACHADO', 'CON_DIFERENCIAS', 'CANCELADO'].map((estado) => <option key={estado} value={estado}>{estado}</option>)}
                        </select>
                    </div>
                )}
            >
                {despachosFiltrados.length === 0 ? (
                    <EmptyState title="Sin despachos" description="Genera un despacho desde un packing empacado." icon="fas fa-truck-ramp-box" />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Estado</Th>
                                <Th>Origen</Th>
                                <Th>Detalle logístico</Th>
                                <Th>Fechas</Th>
                                <Th align="right">Acciones</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {despachosFiltrados.map((orden) => (
                                <tr key={orden.id} className="hover:bg-slate-50/60 align-top">
                                    <Td>
                                        <p className="font-black text-slate-800">{orden.codigo}</p>
                                        <p className="text-xs text-slate-500">{orden.referencia || 'Sin referencia'}</p>
                                        <p className="text-xs text-slate-500">{orden.motivo || 'despacho_interno'}</p>
                                    </Td>
                                    <Td><EstadoBadge value={orden.estado} /></Td>
                                    <Td>
                                        <p className="font-black text-slate-700">Packing: {orden.packing_orden?.codigo || orden.packingOrden?.codigo || `#${orden.packing_orden_id}`}</p>
                                        <p className="text-xs text-slate-500">Picking: {orden.picking_orden?.codigo || orden.pickingOrden?.codigo || `#${orden.picking_orden_id}`}</p>
                                        <p className="text-xs text-slate-500">Reserva: {orden.reserva?.codigo_reserva || '-'}</p>
                                    </Td>
                                    <Td>
                                        {(orden.detalles || []).map((detalle) => (
                                            <div key={detalle.id} className="mb-2 last:mb-0 text-xs text-slate-600">
                                                <p className="font-black text-slate-700">{getProductoNombre(detalle)}</p>
                                                <p>Empacada: {formatNumber(detalle.cantidad_empacada, 4)} · Despachada: {formatNumber(detalle.cantidad_despachada, 4)} · Faltante: {formatNumber(detalle.cantidad_faltante, 4)}</p>
                                                <p>Ubicación: {detalle.ubicacion_origen?.codigo || detalle.ubicacionOrigen?.codigo || '-'}</p>
                                                <p><EstadoBadge value={detalle.estado} /></p>
                                            </div>
                                        ))}
                                    </Td>
                                    <Td>
                                        <p className="text-xs text-slate-500">Creación: {formatDate(orden.fecha_creacion)}</p>
                                        <p className="text-xs text-slate-500">Inicio: {formatDate(orden.fecha_inicio)}</p>
                                        <p className="text-xs text-slate-500">Confirmación: {formatDate(orden.fecha_confirmacion)}</p>
                                    </Td>
                                    <Td align="right">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <SecondaryButton onClick={() => ejecutarAccion(orden, 'iniciar')} disabled={saving || orden.estado !== 'PENDIENTE'}>Iniciar</SecondaryButton>
                                            <PrimaryButton onClick={() => confirmarConCantidades(orden)} disabled={saving || orden.estado !== 'EN_DESPACHO'}>Confirmar</PrimaryButton>
                                            <SecondaryButton onClick={() => ejecutarAccion(orden, 'cancelar')} disabled={saving || !['PENDIENTE', 'EN_DESPACHO'].includes(orden.estado)}>Cancelar</SecondaryButton>
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

export default DespachosInventario;
