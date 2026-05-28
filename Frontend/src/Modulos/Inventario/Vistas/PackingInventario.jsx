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

const PackingInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [packing, setPacking] = useState([]);
    const [picking, setPicking] = useState([]);
    const [estadoFiltro, setEstadoFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [form, setForm] = useState({ picking_orden_id: '', observacion: '' });
    const [mostrarFormulario, setMostrarFormulario] = useState(false);

    const { invalidarTodoInventario } = useInventarioData();

    const cargarDatos = async () => {
        try {
            setLoading(true);
            setError(null);

            const [packingResponse, pickingResponse] = await Promise.all([
                inventarioApi.packing.listar({ per_page: 100 }),
                inventarioApi.picking.listar({ per_page: 100 }),
            ]);

            setPacking(Array.isArray(packingResponse.data) ? packingResponse.data : []);
            setPicking(Array.isArray(pickingResponse.data) ? pickingResponse.data : []);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const pickingListoParaPacking = useMemo(() => {
        const usados = new Set(packing.map((orden) => Number(orden.picking_orden_id)));
        return picking.filter((orden) => ['PICKING_COMPLETO', 'CON_DIFERENCIAS'].includes(orden.estado) && !usados.has(Number(orden.id)));
    }, [picking, packing]);

    const packingFiltrado = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return packing.filter((orden) => {
            const coincideEstado = estadoFiltro ? orden.estado === estadoFiltro : true;
            const coincideBusqueda = !term || [
                orden.codigo,
                orden.estado,
                orden.observacion,
                orden.picking_orden?.codigo,
                orden.pickingOrden?.codigo,
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideEstado && coincideBusqueda;
        });
    }, [packing, estadoFiltro, busqueda]);

    const resumen = useMemo(() => ({
        total: packing.length,
        pendientes: packing.filter((item) => item.estado === 'PENDIENTE').length,
        empaque: packing.filter((item) => item.estado === 'EN_EMPAQUE').length,
        diferencias: packing.filter((item) => item.estado === 'CON_DIFERENCIAS').length,
    }), [packing]);

    const crearPacking = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            await inventarioApi.packing.crear({
                picking_orden_id: Number(form.picking_orden_id),
                observacion: form.observacion || null,
            });

            await Swal.fire({
                icon: 'success',
                title: 'Packing generado',
                text: 'La orden de packing fue creada desde picking confirmado. No se generó documento fiscal.',
                confirmButtonColor: '#10b981',
            });

            setForm({ picking_orden_id: '', observacion: '' });
            setMostrarFormulario(false);
            await cargarDatos();
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    const ejecutarAccion = async (orden, accion) => {
        const textos = {
            iniciar: ['Iniciar packing', 'La orden pasará a empaque operativo.'],
            confirmar: ['Confirmar packing', 'Se confirmarán por defecto las cantidades pickeadas como empacadas.'],
            cancelar: ['Cancelar packing', 'La orden de packing quedará cancelada. No altera DTE/SII.'],
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

            if (accion === 'iniciar') await inventarioApi.packing.iniciar(orden.id);
            if (accion === 'confirmar') await inventarioApi.packing.confirmar(orden.id);
            if (accion === 'cancelar') await inventarioApi.packing.cancelar(orden.id, { observacion: 'Cancelado desde frontend operativo de bodega.' });

            invalidarTodoInventario();
            await cargarDatos();
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingState text="Cargando operación de packing..." />;

    return (
        <div className="space-y-6">
            <PageHeader
                eyebrow="Inventario · Fase 14.1"
                title="Packing de Bodega"
                description="Empaque operativo desde picking confirmado. Controla diferencias y prepara internamente sin facturación, DTE/SII ni contabilidad automática."
                actions={(
                    <>
                        <SecondaryButton onClick={cargarDatos} disabled={saving}>
                            <i className="fas fa-rotate"></i> Actualizar
                        </SecondaryButton>
                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)} disabled={saving}>
                            <i className="fas fa-box-open"></i> Generar packing
                        </PrimaryButton>
                    </>
                )}
            />

            <ErrorNotice error={error} />

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <StatCard title="Packing" value={formatNumber(resumen.total)} icon="fas fa-box-open" />
                <StatCard title="Pendientes" value={formatNumber(resumen.pendientes)} icon="fas fa-hourglass-half" tone="blue" />
                <StatCard title="En empaque" value={formatNumber(resumen.empaque)} icon="fas fa-boxes-packing" tone="amber" />
                <StatCard title="Con diferencias" value={formatNumber(resumen.diferencias)} icon="fas fa-triangle-exclamation" tone="rose" />
            </div>

            <AlertBox tone="emerald">
                Packing nace solo desde picking completo o con diferencias aceptadas. Esta vista no emite guías, facturas, XML ni folios tributarios.
            </AlertBox>

            {mostrarFormulario && (
                <Panel title="Generar packing desde picking" subtitle="Selecciona una orden de picking ya confirmada.">
                    <form onSubmit={crearPacking} className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Field label="Picking confirmado">
                            <select className={inputClass} value={form.picking_orden_id} onChange={(event) => setForm((current) => ({ ...current, picking_orden_id: event.target.value }))} required>
                                <option value="">Seleccionar</option>
                                {pickingListoParaPacking.map((orden) => <option key={orden.id} value={orden.id}>{orden.codigo} · {orden.estado}</option>)}
                            </select>
                        </Field>
                        <div className="md:col-span-2">
                            <Field label="Observación">
                                <input className={inputClass} value={form.observacion} onChange={(event) => setForm((current) => ({ ...current, observacion: event.target.value }))} placeholder="Observación interna" />
                            </Field>
                        </div>
                        <div className="md:col-span-3 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setMostrarFormulario(false)}>Cancelar</SecondaryButton>
                            <PrimaryButton type="submit" disabled={saving || !pickingListoParaPacking.length}>Crear packing</PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Órdenes de packing"
                actions={(
                    <div className="flex flex-col sm:flex-row gap-2">
                        <input value={busqueda} onChange={(event) => setBusqueda(event.target.value)} className={inputClass} placeholder="Buscar packing/picking" />
                        <select value={estadoFiltro} onChange={(event) => setEstadoFiltro(event.target.value)} className={inputClass}>
                            <option value="">Todos</option>
                            {['PENDIENTE', 'EN_EMPAQUE', 'EMPACADO', 'CON_DIFERENCIAS', 'CANCELADO'].map((estado) => <option key={estado} value={estado}>{estado}</option>)}
                        </select>
                    </div>
                )}
            >
                {packingFiltrado.length === 0 ? (
                    <EmptyState title="Sin órdenes de packing" description="Genera packing desde una orden de picking confirmada." />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Estado</Th>
                                <Th>Picking origen</Th>
                                <Th>Detalle</Th>
                                <Th>Fechas</Th>
                                <Th align="right">Acciones</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {packingFiltrado.map((orden) => (
                                <tr key={orden.id} className="hover:bg-slate-50/60 align-top">
                                    <Td>
                                        <p className="font-black text-slate-800">{orden.codigo}</p>
                                        <p className="text-xs text-slate-500">{orden.observacion || 'Sin observación'}</p>
                                    </Td>
                                    <Td><EstadoBadge value={orden.estado} /></Td>
                                    <Td>
                                        <p className="font-black text-slate-700">{orden.picking_orden?.codigo || orden.pickingOrden?.codigo || `Picking #${orden.picking_orden_id}`}</p>
                                        <p className="text-xs text-slate-500">{orden.picking_orden?.estado || orden.pickingOrden?.estado || '-'}</p>
                                    </Td>
                                    <Td>
                                        {(orden.detalles || []).map((detalle) => (
                                            <div key={detalle.id} className="mb-2 last:mb-0 text-xs text-slate-600">
                                                <p className="font-black text-slate-700">{getProductoNombre(detalle)}</p>
                                                <p>Pickeada: {formatNumber(detalle.cantidad_pickeada, 4)} · Empacada: {formatNumber(detalle.cantidad_empacada, 4)}</p>
                                                <p>Ubicación: {detalle.ubicacion_origen?.codigo || detalle.ubicacionOrigen?.codigo || '-'}</p>
                                            </div>
                                        ))}
                                    </Td>
                                    <Td>
                                        <p className="text-xs text-slate-500">Creación: {formatDate(orden.fecha_creacion)}</p>
                                        <p className="text-xs text-slate-500">Confirmación: {formatDate(orden.fecha_confirmacion)}</p>
                                    </Td>
                                    <Td align="right">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <SecondaryButton onClick={() => ejecutarAccion(orden, 'iniciar')} disabled={saving || orden.estado !== 'PENDIENTE'}>Iniciar</SecondaryButton>
                                            <PrimaryButton onClick={() => ejecutarAccion(orden, 'confirmar')} disabled={saving || orden.estado !== 'EN_EMPAQUE'}>Confirmar</PrimaryButton>
                                            <SecondaryButton onClick={() => ejecutarAccion(orden, 'cancelar')} disabled={saving || ['CANCELADO', 'EMPACADO'].includes(orden.estado)}>Cancelar</SecondaryButton>
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

export default PackingInventario;
