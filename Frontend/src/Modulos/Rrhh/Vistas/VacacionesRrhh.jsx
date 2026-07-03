import React, { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import { usePermisos } from '../../../Contextos/Permisos';
import rrhhApi from '../Servicios/rrhhApi';
import { colorEstado, formatFecha } from '../Utilidades/formato';
import PanelModal from '../Componentes/PanelModal';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';

const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const FORM_VACIO = { empleado_id: '', fecha_desde: '', fecha_hasta: '', observacion: '' };

const VacacionesRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeProcesar = tienePermiso('rrhh.remuneraciones.procesar');

    const [solicitudes, setSolicitudes] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [empleados, setEmpleados] = useState([]);
    const [filtroEstado, setFiltroEstado] = useState('');

    const [modalSolicitar, setModalSolicitar] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [form, setForm] = useState(FORM_VACIO);
    const [saldoPreview, setSaldoPreview] = useState(null);

    const cargar = useCallback(async (signal) => {
        setCargando(true);
        try {
            const params = filtroEstado ? { estado: filtroEstado } : {};
            const resp = await rrhhApi.vacaciones.listar(params, { signal });
            const data = resp?.data;
            setSolicitudes(data?.data ?? (Array.isArray(data) ? data : []));
            setCargando(false);
        } catch (err) {
            if (err?.name !== 'AbortError' && err?.code !== 'ERR_CANCELED') {
                setCargando(false);
            }
        }
    }, [filtroEstado]);

    useEffect(() => {
        const controller = new AbortController();
        cargar(controller.signal);
        return () => controller.abort();
    }, [cargar]);

    useEffect(() => {
        (async () => {
            try {
                const resp = await rrhhApi.empleados.listar();
                const data = resp?.data?.data ?? resp?.data ?? [];
                setEmpleados(Array.isArray(data) ? data : (data.data ?? []));
            } catch (_) { /* toast */ }
        })();
    }, []);

    const setCampo = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const onEmpleado = async (id) => {
        setForm((f) => ({ ...f, empleado_id: id }));
        setSaldoPreview(null);
        if (!id) return;
        try {
            const resp = await rrhhApi.vacaciones.saldo(id);
            setSaldoPreview(resp?.data ?? null);
        } catch (_) { /* toast */ }
    };

    const solicitar = async (e) => {
        e.preventDefault();
        setEnviando(true);
        try {
            await rrhhApi.vacaciones.solicitar(form);
            await Swal.fire({ icon: 'success', title: 'Solicitud registrada', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            setModalSolicitar(false);
            setForm(FORM_VACIO);
            setSaldoPreview(null);
            cargar();
        } catch (_) { /* toast */ } finally {
            setEnviando(false);
        }
    };

    const aprobar = async (sol) => {
        const r = await Swal.fire({
            icon: 'question', title: '¿Aprobar solicitud?',
            text: `${sol.dias_habiles} días hábiles se descontarán del saldo disponible.`,
            showCancelButton: true, confirmButtonText: 'Aprobar', confirmButtonColor: '#059669', cancelButtonText: 'Cancelar',
        });
        if (!r.isConfirmed) return;
        try {
            await rrhhApi.vacaciones.aprobar(sol.id);
            await Swal.fire({ icon: 'success', title: 'Solicitud aprobada', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            cargar();
        } catch (_) { /* toast */ }
    };

    const rechazar = async (sol) => {
        const r = await Swal.fire({
            icon: 'warning', title: 'Rechazar solicitud', input: 'text',
            inputLabel: 'Motivo del rechazo', inputPlaceholder: 'Ej: sin cobertura en el equipo',
            showCancelButton: true, confirmButtonText: 'Rechazar', confirmButtonColor: '#dc2626', cancelButtonText: 'Cancelar',
            inputValidator: (v) => (!v || v.trim().length < 5) ? 'El motivo debe tener al menos 5 caracteres' : undefined,
        });
        if (!r.isConfirmed) return;
        try {
            await rrhhApi.vacaciones.rechazar(sol.id, r.value);
            await Swal.fire({ icon: 'success', title: 'Solicitud rechazada', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            cargar();
        } catch (_) { /* toast */ }
    };

    const anular = async (sol) => {
        const r = await Swal.fire({
            icon: 'warning', title: '¿Anular solicitud aprobada?', input: 'text',
            text: 'El saldo consumido se repone.',
            inputLabel: 'Motivo de la anulación', inputPlaceholder: 'Ej: el trabajador no pudo tomar los días',
            showCancelButton: true, confirmButtonText: 'Anular', confirmButtonColor: '#dc2626', cancelButtonText: 'Cancelar',
            inputValidator: (v) => (!v || v.trim().length < 5) ? 'El motivo debe tener al menos 5 caracteres' : undefined,
        });
        if (!r.isConfirmed) return;
        try {
            await rrhhApi.vacaciones.anular(sol.id, r.value);
            await Swal.fire({ icon: 'success', title: 'Solicitud anulada', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            cargar();
        } catch (_) { /* toast */ }
    };

    return (
        <div className="max-w-6xl mx-auto p-6 md:p-8">
            <header className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                            <i className="fas fa-umbrella-beach text-emerald-600" />
                            Vacaciones
                        </h1>
                        <AyudaModulo moduloId="vacacionesRrhh" size={28} />
                    </div>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Solicitud y aprobación de feriado legal (Art. 67-70 Código del Trabajo).</p>
                </div>
                <div className="flex items-center gap-2">
                    <select value={filtroEstado} onChange={(e) => setFiltroEstado(e.target.value)} className={inputCls + ' w-auto'}>
                        <option value="">Todos los estados</option>
                        <option value="PENDIENTE">Pendiente</option>
                        <option value="APROBADA">Aprobada</option>
                        <option value="RECHAZADA">Rechazada</option>
                        <option value="ANULADA">Anulada</option>
                    </select>
                    {puedeProcesar && (
                        <button onClick={() => { setForm(FORM_VACIO); setSaldoPreview(null); setModalSolicitar(true); }}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm">
                            <i className="fas fa-calendar-plus" /> Solicitar vacaciones
                        </button>
                    )}
                </div>
            </header>

            <EstadoCarga cargando={cargando} mensajeCargando="Cargando solicitudes..." color="emerald" tamano="compacto">
                <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Empleado</th>
                                    <th className="px-4 py-3 text-left font-semibold">Desde</th>
                                    <th className="px-4 py-3 text-left font-semibold">Hasta</th>
                                    <th className="px-4 py-3 text-right font-semibold">Días hábiles</th>
                                    <th className="px-4 py-3 text-left font-semibold">Estado</th>
                                    <th className="px-4 py-3 text-right font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                {solicitudes.length === 0 && (
                                    <EstadoVacio mensaje="Sin solicitudes de vacaciones." />
                                )}
                                {solicitudes.map((sol) => (
                                    <tr key={sol.id} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                                            {sol.empleado ? `${sol.empleado.nombres} ${sol.empleado.apellido_paterno}` : `#${sol.empleado_id}`}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-400">{formatFecha(sol.fecha_desde)}</td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-400">{formatFecha(sol.fecha_hasta)}</td>
                                        <td className="px-4 py-3 text-right font-bold text-slate-900 dark:text-slate-100">{sol.dias_habiles}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-bold ${colorEstado(sol.estado)}`}>{sol.estado}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            {puedeProcesar && sol.estado === 'PENDIENTE' && (
                                                <>
                                                    <button onClick={() => aprobar(sol)} className="text-slate-400 hover:text-emerald-600 px-2" title="Aprobar">
                                                        <i className="fas fa-check" />
                                                    </button>
                                                    <button onClick={() => rechazar(sol)} className="text-slate-400 hover:text-red-600 px-2" title="Rechazar">
                                                        <i className="fas fa-times" />
                                                    </button>
                                                </>
                                            )}
                                            {puedeProcesar && sol.estado === 'APROBADA' && (
                                                <button onClick={() => anular(sol)} className="text-slate-400 hover:text-red-600 px-2" title="Anular">
                                                    <i className="fas fa-undo" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </EstadoCarga>

            <PanelModal abierto={modalSolicitar} titulo="Solicitar vacaciones" icono="fas fa-calendar-plus" onClose={() => setModalSolicitar(false)}>
                <form onSubmit={solicitar} className="grid sm:grid-cols-2 gap-3">
                    <div className="sm:col-span-2">
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Empleado *</label>
                        <select required value={form.empleado_id} onChange={(e) => onEmpleado(e.target.value)} className={inputCls}>
                            <option value="">Selecciona...</option>
                            {empleados.map((emp) => <option key={emp.id} value={emp.id}>{emp.rut} — {emp.nombres} {emp.apellido_paterno}</option>)}
                        </select>
                        {saldoPreview && (
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Saldo disponible: <strong>{saldoPreview.dias_disponibles}</strong> días hábiles
                                ({saldoPreview.dias_devengados} devengados − {saldoPreview.dias_tomados} tomados)
                            </p>
                        )}
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Fecha desde *</label>
                        <input required type="date" value={form.fecha_desde} onChange={(e) => setCampo('fecha_desde', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Fecha hasta *</label>
                        <input required type="date" value={form.fecha_hasta} onChange={(e) => setCampo('fecha_hasta', e.target.value)} className={inputCls} />
                    </div>
                    <div className="sm:col-span-2">
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Observación</label>
                        <textarea rows={2} value={form.observacion} onChange={(e) => setCampo('observacion', e.target.value)} className={inputCls} />
                    </div>
                    <div className="sm:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" onClick={() => setModalSolicitar(false)} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 font-semibold text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="submit" disabled={enviando} className="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-60 inline-flex items-center gap-2">
                            {enviando && <i className="fas fa-spinner fa-spin" />} Solicitar
                        </button>
                    </div>
                </form>
            </PanelModal>
        </div>
    );
};

export default VacacionesRrhh;
