import React, { useEffect, useMemo, useState } from 'react';
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
    SecondaryButton,
    StatCard,
    TableShell,
    Td,
    Th,
} from '../Componentes/InventarioUI';

const inputClass = 'w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none bg-white';

const acciones = [
    'PRODUCTO_CREADO',
    'PRODUCTO_ACTUALIZADO',
    'MOVIMIENTO_CREADO',
    'AJUSTE_CRITICO_CREADO',
    'MERMA_REGISTRADA',
    'RESERVA_CREADA',
    'RESERVA_CONFIRMADA',
    'RESERVA_CANCELADA',
    'PICKING_CREADO',
    'PICKING_CONFIRMADO',
    'PICKING_CANCELADO',
    'PACKING_CREADO',
    'PACKING_CONFIRMADO',
    'PACKING_CANCELADO',
    'DESPACHO_CREADO',
    'DESPACHO_INICIADO',
    'DESPACHO_CONFIRMADO',
    'DESPACHO_CANCELADO',
    'DEVOLUCION_CREADA',
    'DEVOLUCION_CONFIRMADA',
    'DEVOLUCION_CANCELADA',
    'REVERSA_TOTAL_CONFIRMADA',
    'REVERSA_PARCIAL_CONFIRMADA',
    'DIFERENCIA_POST_DESPACHO_REGISTRADA',
    'TOMA_FISICA_CREADA',
    'TOMA_FISICA_AJUSTADA',
    'STOCK_UBICACION_AJUSTADO',
    'ACCESO_NO_AUTORIZADO',
    'OPERACION_BLOQUEADA',
];

const severidades = ['INFO', 'WARNING', 'CRITICAL'];

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

const AuditoriaInventario = () => {
    const { tienePermiso } = usePermisos();
    const puedeVerDetalle = tienePermiso('inventario.auditoria.detalle');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [eventos, setEventos] = useState([]);
    const [resumen, setResumen] = useState(null);
    const [seleccionado, setSeleccionado] = useState(null);
    const [detalleLoading, setDetalleLoading] = useState(false);
    const [filtros, setFiltros] = useState({
        accion: '',
        severidad: '',
        entidad_tipo: '',
        entidad_id: '',
        usuario_id: '',
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
                inventarioApi.auditoria.listar({ ...filtrosConsulta, per_page: 100 }),
                inventarioApi.auditoria.resumen(filtrosConsulta),
            ]);

            setEventos(Array.isArray(listaResponse.data) ? listaResponse.data : []);
            setResumen(resumenResponse.data || null);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const aplicarFiltros = (event) => {
        event.preventDefault();
        cargarDatos(params);
    };

    const limpiarFiltros = () => {
        const vacios = { accion: '', severidad: '', entidad_tipo: '', entidad_id: '', usuario_id: '', fecha_desde: '', fecha_hasta: '' };
        setFiltros(vacios);
        cargarDatos({});
    };

    const verDetalle = async (evento) => {
        if (!puedeVerDetalle) {
            setSeleccionado(evento);
            return;
        }

        try {
            setDetalleLoading(true);
            const response = await inventarioApi.auditoria.obtener(evento.id);
            setSeleccionado(response.data || evento);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setDetalleLoading(false);
        }
    };

    if (!tienePermiso('inventario.auditoria.ver')) {
        return (
            <AlertBox tone="rose">
                No tienes permisos para consultar la auditoría operativa de Inventario.
            </AlertBox>
        );
    }

    if (loading) {
        return <LoadingState text="Cargando bitácora operativa de Inventario..." />;
    }

    return (
        <div className="space-y-6">
            <PageHeader
                eyebrow="Fase 17"
                title="Auditoría de Inventario"
                description="Bitácora operativa para trazabilidad por empresa, usuario, acción, entidad, severidad, IP, user-agent y metadata controlada. No corresponde a auditoría tributaria ni DTE/SII."
                actions={(
                    <SecondaryButton onClick={() => cargarDatos()}>
                        <i className="fas fa-rotate"></i>
                        Actualizar
                    </SecondaryButton>
                )}
            />

            <ErrorNotice error={error} />

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <StatCard title="Eventos" value={resumen?.total_eventos ?? eventos.length} icon="fas fa-clipboard-list" tone="emerald" />
                <StatCard title="Críticos" value={resumen?.por_severidad?.CRITICAL ?? 0} icon="fas fa-triangle-exclamation" tone="rose" />
                <StatCard title="Warnings" value={resumen?.por_severidad?.WARNING ?? 0} icon="fas fa-shield-halved" tone="amber" />
                <StatCard title="Info" value={resumen?.por_severidad?.INFO ?? 0} icon="fas fa-circle-info" tone="blue" />
            </div>

            <Panel title="Filtros de auditoría" subtitle="Usa filtros específicos para revisión técnica, soporte o análisis post-incidente.">
                <form onSubmit={aplicarFiltros} className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Field label="Acción">
                        <select className={inputClass} value={filtros.accion} onChange={(e) => setFiltros({ ...filtros, accion: e.target.value })}>
                            <option value="">Todas</option>
                            {acciones.map((accion) => <option key={accion} value={accion}>{pretty(accion)}</option>)}
                        </select>
                    </Field>
                    <Field label="Severidad">
                        <select className={inputClass} value={filtros.severidad} onChange={(e) => setFiltros({ ...filtros, severidad: e.target.value })}>
                            <option value="">Todas</option>
                            {severidades.map((sev) => <option key={sev} value={sev}>{sev}</option>)}
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
                    <Field label="Desde">
                        <input className={inputClass} type="date" value={filtros.fecha_desde} onChange={(e) => setFiltros({ ...filtros, fecha_desde: e.target.value })} />
                    </Field>
                    <Field label="Hasta">
                        <input className={inputClass} type="date" value={filtros.fecha_hasta} onChange={(e) => setFiltros({ ...filtros, fecha_hasta: e.target.value })} />
                    </Field>
                    <div className="flex items-end gap-2">
                        <SecondaryButton type="submit">
                            <i className="fas fa-filter"></i>
                            Aplicar
                        </SecondaryButton>
                        <SecondaryButton type="button" onClick={limpiarFiltros}>
                            Limpiar
                        </SecondaryButton>
                    </div>
                </form>
            </Panel>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <Panel title="Eventos registrados" subtitle="No se auditan consultas GET comunes para no generar ruido ni degradar rendimiento." className="xl:col-span-2">
                    {!eventos.length ? (
                        <EmptyState title="Sin eventos" description="Aún no hay eventos para los filtros seleccionados." icon="fas fa-clipboard-check" />
                    ) : (
                        <TableShell>
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Fecha</Th>
                                    <Th>Acción</Th>
                                    <Th>Severidad</Th>
                                    <Th>Entidad</Th>
                                    <Th>Usuario</Th>
                                    <Th align="right">Detalle</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {eventos.map((evento) => (
                                    <tr key={evento.id} className="hover:bg-slate-50/80 transition-colors">
                                        <Td>{formatDate(evento.created_at)}</Td>
                                        <Td className="font-black text-slate-700">{pretty(evento.accion)}</Td>
                                        <Td><EstadoBadge value={evento.severidad} /></Td>
                                        <Td>
                                            <p className="font-bold text-slate-700">{String(evento.entidad_tipo || '').split('\\').pop()}</p>
                                            <p className="text-xs text-slate-400">ID #{evento.entidad_id ?? '-'}</p>
                                        </Td>
                                        <Td>
                                            <p className="font-bold text-slate-700">{evento.usuario?.nombre || `Usuario #${evento.usuario_id ?? '-'}`}</p>
                                            {evento.ip && <p className="text-xs text-slate-400">IP {evento.ip}</p>}
                                        </Td>
                                        <Td align="right">
                                            <SecondaryButton type="button" onClick={() => verDetalle(evento)} disabled={detalleLoading}>
                                                Ver
                                            </SecondaryButton>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </TableShell>
                    )}
                </Panel>

                <Panel title="Detalle del evento" subtitle="Metadata saneada; no se almacenan tokens, passwords ni secretos.">
                    {!seleccionado ? (
                        <EmptyState title="Selecciona un evento" description="El detalle muestra contexto técnico, IP, user-agent y metadata relevante." icon="fas fa-magnifying-glass-chart" />
                    ) : (
                        <div className="space-y-5">
                            <div>
                                <EstadoBadge value={seleccionado.severidad} />
                                <h3 className="text-xl font-black text-slate-800 mt-3">{pretty(seleccionado.accion)}</h3>
                                <p className="text-sm text-slate-500 font-medium mt-1">{seleccionado.descripcion}</p>
                            </div>

                            <div className="grid grid-cols-1 gap-3 text-sm">
                                <div className="rounded-2xl bg-slate-50 p-4">
                                    <p className="text-xs font-black text-slate-400 uppercase tracking-widest">Entidad</p>
                                    <p className="font-bold text-slate-700">{seleccionado.entidad_tipo}</p>
                                    <p className="text-slate-500">ID #{seleccionado.entidad_id ?? '-'}</p>
                                </div>
                                <div className="rounded-2xl bg-slate-50 p-4">
                                    <p className="text-xs font-black text-slate-400 uppercase tracking-widest">Usuario / Origen</p>
                                    <p className="font-bold text-slate-700">{seleccionado.usuario?.nombre || `Usuario #${seleccionado.usuario_id ?? '-'}`}</p>
                                    <p className="text-slate-500">{seleccionado.ip || 'IP no registrada'}</p>
                                    <p className="text-xs text-slate-400 break-all">{seleccionado.user_agent || 'User-agent no registrado'}</p>
                                </div>
                            </div>

                            <JsonBlock title="Metadata" value={seleccionado.metadata_json} />
                            <JsonBlock title="Antes" value={seleccionado.antes_json} />
                            <JsonBlock title="Después" value={seleccionado.despues_json} />
                        </div>
                    )}
                </Panel>
            </div>
        </div>
    );
};

export default AuditoriaInventario;
