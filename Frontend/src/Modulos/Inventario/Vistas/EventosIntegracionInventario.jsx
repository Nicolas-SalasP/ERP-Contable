import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { usePermisos } from '../../../Contextos/Permisos';
import {
    AlertBox,
    EmptyState,
    ErrorNotice,
    EstadoBadge,
    Field,
    formatDate,
    LoadingState,
    PageHeader,
    Panel,
    PrimaryButton,
    SecondaryButton,
    TableShell,
    Td,
    Th,
    StatCard,
} from '../Componentes/InventarioUI';

const inputClass = 'w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none bg-white';

const eventos = [
    'INVENTARIO_MOVIMIENTO_CREADO',
    'INVENTARIO_AJUSTE_CRITICO_CREADO',
    'INVENTARIO_MERMA_REGISTRADA',
    'INVENTARIO_RESERVA_CREADA',
    'INVENTARIO_RESERVA_CONFIRMADA',
    'INVENTARIO_RESERVA_CANCELADA',
    'INVENTARIO_RESERVA_LIBERADA',
    'INVENTARIO_RESERVA_CONSUMIDA',
    'INVENTARIO_PICKING_CREADO',
    'INVENTARIO_PICKING_CONFIRMADO',
    'INVENTARIO_PICKING_CANCELADO',
    'INVENTARIO_PACKING_CREADO',
    'INVENTARIO_PACKING_CONFIRMADO',
    'INVENTARIO_PACKING_CANCELADO',
    'INVENTARIO_DESPACHO_CREADO',
    'INVENTARIO_DESPACHO_INICIADO',
    'INVENTARIO_DESPACHO_CONFIRMADO',
    'INVENTARIO_DESPACHO_CANCELADO',
    'INVENTARIO_DEVOLUCION_CREADA',
    'INVENTARIO_DEVOLUCION_CONFIRMADA',
    'INVENTARIO_DEVOLUCION_CANCELADA',
    'INVENTARIO_REVERSA_TOTAL_CONFIRMADA',
    'INVENTARIO_REVERSA_PARCIAL_CONFIRMADA',
    'INVENTARIO_DIFERENCIA_POST_DESPACHO_REGISTRADA',
    'INVENTARIO_TOMA_FISICA_AJUSTADA',
    'INVENTARIO_STOCK_BAJO_DETECTADO',
    'INVENTARIO_STOCK_UBICACION_AJUSTADO',
];

const estados = ['PENDIENTE', 'PROCESADO', 'IGNORADO', 'ERROR'];
const prioridades = ['BAJA', 'NORMAL', 'ALTA', 'CRITICA'];

const pretty = (value) => String(value || '-').replaceAll('_', ' ');

const JsonBlock = ({ title, value }) => {
    if (!value || (typeof value === 'object' && !Object.keys(value).length)) {
        return null;
    }

    return (
        <div>
            <h4 className="text-xs font-black text-slate-500 uppercase tracking-widest mb-2">{title}</h4>
            <pre className="max-h-80 overflow-auto rounded-2xl bg-slate-950 text-slate-100 p-4 text-xs leading-relaxed custom-scrollbar">
                {JSON.stringify(value, null, 2)}
            </pre>
        </div>
    );
};

const EventosIntegracionInventario = () => {
    const { tienePermiso } = usePermisos();
    const puedeVer = tienePermiso('inventario.eventos_integracion.ver');
    const puedeDetalle = tienePermiso('inventario.eventos_integracion.detalle');
    const puedeProcesar = tienePermiso('inventario.eventos_integracion.procesar') || tienePermiso('inventario.eventos_integracion.gestionar');
    const puedeGestionar = tienePermiso('inventario.eventos_integracion.gestionar');

    const [loading, setLoading] = useState(true);
    const [accionLoading, setAccionLoading] = useState(false);
    const [error, setError] = useState(null);
    const [registros, setRegistros] = useState([]);
    const [resumen, setResumen] = useState(null);
    const [seleccionado, setSeleccionado] = useState(null);
    const [filtros, setFiltros] = useState({
        evento: '',
        estado: '',
        prioridad: '',
        entidad_tipo: '',
        entidad_id: '',
        usuario_id: '',
        correlacion_id: '',
        fecha_desde: '',
        fecha_hasta: '',
    });

    const params = useMemo(() => Object.fromEntries(
        Object.entries(filtros).filter(([, value]) => value !== undefined && value !== null && value !== ''),
    ), [filtros]);

    const cargarDatos = async (overrideParams = null) => {
        const filtrosConsulta = overrideParams ?? params;

        try {
            setLoading(true);
            setError(null);
            const [listaResponse, resumenResponse] = await Promise.all([
                inventarioApi.eventosIntegracion.listar({ ...filtrosConsulta, per_page: 100 }),
                inventarioApi.eventosIntegracion.resumen(filtrosConsulta),
            ]);

            setRegistros(Array.isArray(listaResponse.data) ? listaResponse.data : []);
            setResumen(resumenResponse.data || null);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (puedeVer) {
            cargarDatos();
        }
    }, [puedeVer]);

    const aplicarFiltros = (event) => {
        event.preventDefault();
        cargarDatos(params);
    };

    const limpiarFiltros = () => {
        const vacios = {
            evento: '',
            estado: '',
            prioridad: '',
            entidad_tipo: '',
            entidad_id: '',
            usuario_id: '',
            correlacion_id: '',
            fecha_desde: '',
            fecha_hasta: '',
        };
        setFiltros(vacios);
        cargarDatos({});
    };

    const verDetalle = async (evento) => {
        if (!puedeDetalle) {
            setSeleccionado(evento);
            return;
        }

        try {
            setAccionLoading(true);
            const response = await inventarioApi.eventosIntegracion.obtener(evento.id);
            setSeleccionado(response.data || evento);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setAccionLoading(false);
        }
    };

    const ejecutarAccion = async (tipo, evento) => {
        try {
            setAccionLoading(true);
            setError(null);

            if (tipo === 'procesar') {
                await inventarioApi.eventosIntegracion.procesar(evento.id);
            }

            if (tipo === 'ignorar') {
                const result = await Swal.fire({
                    title: 'Ignorar evento',
                    input: 'text',
                    inputLabel: 'Motivo interno',
                    inputPlaceholder: 'Ej: evento informativo sin acción requerida',
                    showCancelButton: true,
                    confirmButtonText: 'Ignorar',
                    cancelButtonText: 'Cancelar',
                });

                if (!result.isConfirmed) return;
                await inventarioApi.eventosIntegracion.ignorar(evento.id, { motivo: result.value || null });
            }

            if (tipo === 'error') {
                const result = await Swal.fire({
                    title: 'Marcar con error',
                    input: 'textarea',
                    inputLabel: 'Mensaje de error',
                    inputPlaceholder: 'Describe el problema de integración interna',
                    showCancelButton: true,
                    confirmButtonText: 'Marcar error',
                    cancelButtonText: 'Cancelar',
                    inputValidator: (value) => (!value ? 'El mensaje es obligatorio.' : null),
                });

                if (!result.isConfirmed) return;
                await inventarioApi.eventosIntegracion.error(evento.id, { mensaje: result.value });
            }

            await cargarDatos(params);
            if (seleccionado?.id === evento.id) {
                const actualizado = await inventarioApi.eventosIntegracion.obtener(evento.id);
                setSeleccionado(actualizado.data || null);
            }
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setAccionLoading(false);
        }
    };

    if (!puedeVer) {
        return (
            <AlertBox tone="rose">
                No tienes permisos para consultar eventos internos de integración de Inventario.
            </AlertBox>
        );
    }

    if (loading) {
        return <LoadingState text="Cargando eventos internos de integración..." />;
    }

    return (
        <div className="space-y-6">
            <PageHeader
                eyebrow="Fase 18"
                title="Eventos de Integración Interna"
                description="Bandeja técnica de eventos internos no tributarios para preparar integración futura entre Inventario y otros módulos sin acoplar Facturación, Contabilidad ni DTE/SII."
                actions={(
                    <SecondaryButton onClick={() => cargarDatos()}>
                        <i className="fas fa-rotate"></i>
                        Actualizar
                    </SecondaryButton>
                )}
            />

            <ErrorNotice error={error} />

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <StatCard title="Eventos" value={resumen?.total_eventos ?? registros.length} icon="fas fa-diagram-project" tone="emerald" />
                <StatCard title="Pendientes" value={resumen?.pendientes ?? resumen?.por_estado?.PENDIENTE ?? 0} icon="fas fa-clock" tone="amber" />
                <StatCard title="Errores" value={resumen?.errores ?? resumen?.por_estado?.ERROR ?? 0} icon="fas fa-triangle-exclamation" tone="rose" />
                <StatCard title="Críticos" value={resumen?.por_prioridad?.CRITICA ?? 0} icon="fas fa-bolt" tone="blue" />
            </div>

            <Panel title="Filtros" subtitle="Filtra por evento, estado, prioridad, entidad, usuario, correlación o rango de fechas.">
                <form onSubmit={aplicarFiltros} className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Field label="Evento">
                        <select className={inputClass} value={filtros.evento} onChange={(e) => setFiltros({ ...filtros, evento: e.target.value })}>
                            <option value="">Todos</option>
                            {eventos.map((evento) => <option key={evento} value={evento}>{pretty(evento)}</option>)}
                        </select>
                    </Field>
                    <Field label="Estado">
                        <select className={inputClass} value={filtros.estado} onChange={(e) => setFiltros({ ...filtros, estado: e.target.value })}>
                            <option value="">Todos</option>
                            {estados.map((estado) => <option key={estado} value={estado}>{estado}</option>)}
                        </select>
                    </Field>
                    <Field label="Prioridad">
                        <select className={inputClass} value={filtros.prioridad} onChange={(e) => setFiltros({ ...filtros, prioridad: e.target.value })}>
                            <option value="">Todas</option>
                            {prioridades.map((prioridad) => <option key={prioridad} value={prioridad}>{prioridad}</option>)}
                        </select>
                    </Field>
                    <Field label="Entidad tipo">
                        <input className={inputClass} value={filtros.entidad_tipo} onChange={(e) => setFiltros({ ...filtros, entidad_tipo: e.target.value })} placeholder="Ej: InventarioDespachoOrden" />
                    </Field>
                    <Field label="Entidad ID">
                        <input className={inputClass} type="number" value={filtros.entidad_id} onChange={(e) => setFiltros({ ...filtros, entidad_id: e.target.value })} />
                    </Field>
                    <Field label="Usuario ID">
                        <input className={inputClass} type="number" value={filtros.usuario_id} onChange={(e) => setFiltros({ ...filtros, usuario_id: e.target.value })} />
                    </Field>
                    <Field label="Correlación">
                        <input className={inputClass} value={filtros.correlacion_id} onChange={(e) => setFiltros({ ...filtros, correlacion_id: e.target.value })} placeholder="UUID/código interno" />
                    </Field>
                    <Field label="Desde">
                        <input className={inputClass} type="date" value={filtros.fecha_desde} onChange={(e) => setFiltros({ ...filtros, fecha_desde: e.target.value })} />
                    </Field>
                    <Field label="Hasta">
                        <input className={inputClass} type="date" value={filtros.fecha_hasta} onChange={(e) => setFiltros({ ...filtros, fecha_hasta: e.target.value })} />
                    </Field>
                    <div className="flex items-end gap-2 md:col-span-3">
                        <SecondaryButton type="submit">
                            <i className="fas fa-filter"></i>
                            Aplicar
                        </SecondaryButton>
                        <SecondaryButton type="button" onClick={limpiarFiltros}>Limpiar</SecondaryButton>
                    </div>
                </form>
            </Panel>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <Panel className="xl:col-span-2" title="Eventos registrados" subtitle="Solo se registran operaciones mutantes relevantes; no consultas GET comunes.">
                    {registros.length === 0 ? (
                        <EmptyState title="Sin eventos" description="No hay eventos de integración para los filtros seleccionados." icon="fas fa-diagram-project" />
                    ) : (
                        <TableShell>
                            <thead>
                                <tr>
                                    <Th>Evento</Th>
                                    <Th>Estado</Th>
                                    <Th>Prioridad</Th>
                                    <Th>Entidad</Th>
                                    <Th>Usuario</Th>
                                    <Th>Fecha</Th>
                                    <Th align="right">Acciones</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {registros.map((evento) => (
                                    <tr key={evento.id} className="border-t border-slate-100 hover:bg-slate-50/70">
                                        <Td>
                                            <div className="font-black text-slate-800 text-xs uppercase tracking-wide">{pretty(evento.evento)}</div>
                                            <div className="text-[11px] text-slate-400 mt-1">Corr: {evento.correlacion_id || '-'}</div>
                                        </Td>
                                        <Td><EstadoBadge value={evento.estado} /></Td>
                                        <Td><EstadoBadge value={evento.prioridad} /></Td>
                                        <Td>
                                            <div className="text-xs font-bold text-slate-700">{String(evento.entidad_tipo || '-').split('\\').pop()}</div>
                                            <div className="text-[11px] text-slate-400">ID {evento.entidad_id || '-'}</div>
                                        </Td>
                                        <Td>{evento.usuario?.nombre || evento.usuario_id || '-'}</Td>
                                        <Td>{formatDate(evento.created_at)}</Td>
                                        <Td align="right">
                                            <div className="flex justify-end gap-2 flex-wrap">
                                                <SecondaryButton type="button" onClick={() => verDetalle(evento)} disabled={accionLoading}>
                                                    Ver
                                                </SecondaryButton>
                                                {puedeProcesar && evento.estado === 'PENDIENTE' && (
                                                    <PrimaryButton type="button" onClick={() => ejecutarAccion('procesar', evento)} disabled={accionLoading}>
                                                        Procesar
                                                    </PrimaryButton>
                                                )}
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </TableShell>
                    )}
                </Panel>

                <Panel title="Detalle" subtitle="Payload y metadata saneados; no se almacenan tokens, passwords ni campos tributarios.">
                    {!seleccionado ? (
                        <EmptyState title="Selecciona un evento" description="El detalle permite revisar payload, metadata, origen y correlación." icon="fas fa-code-branch" />
                    ) : (
                        <div className="space-y-5">
                            <div className="rounded-2xl border border-slate-100 p-4 bg-slate-50">
                                <div className="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Evento</div>
                                <div className="font-black text-slate-800">{pretty(seleccionado.evento)}</div>
                                <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                    <div><span className="font-bold text-slate-500">Estado:</span> {seleccionado.estado}</div>
                                    <div><span className="font-bold text-slate-500">Prioridad:</span> {seleccionado.prioridad}</div>
                                    <div><span className="font-bold text-slate-500">Empresa:</span> {seleccionado.empresa_id}</div>
                                    <div><span className="font-bold text-slate-500">Usuario:</span> {seleccionado.usuario?.nombre || seleccionado.usuario_id || '-'}</div>
                                    <div className="col-span-2"><span className="font-bold text-slate-500">Correlación:</span> {seleccionado.correlacion_id || '-'}</div>
                                </div>
                            </div>

                            <JsonBlock title="Payload" value={seleccionado.payload_json} />
                            <JsonBlock title="Metadata" value={seleccionado.metadata_json} />

                            {(puedeGestionar || puedeProcesar) && (
                                <div className="flex gap-2 flex-wrap">
                                    {puedeProcesar && seleccionado.estado === 'PENDIENTE' && (
                                        <PrimaryButton type="button" onClick={() => ejecutarAccion('procesar', seleccionado)} disabled={accionLoading}>
                                            Marcar procesado
                                        </PrimaryButton>
                                    )}
                                    {puedeGestionar && seleccionado.estado === 'PENDIENTE' && (
                                        <SecondaryButton type="button" onClick={() => ejecutarAccion('ignorar', seleccionado)} disabled={accionLoading}>
                                            Ignorar
                                        </SecondaryButton>
                                    )}
                                    {puedeGestionar && seleccionado.estado !== 'PROCESADO' && (
                                        <SecondaryButton type="button" onClick={() => ejecutarAccion('error', seleccionado)} disabled={accionLoading}>
                                            Marcar error
                                        </SecondaryButton>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </Panel>
            </div>
        </div>
    );
};

export default EventosIntegracionInventario;
