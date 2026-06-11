import React, { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import { usePermisos } from '../../../Contextos/Permisos';
import rrhhApi from '../Servicios/rrhhApi';
import { colorEstado, formatFecha, formatPesos } from '../Utilidades/formato';
import PanelModal from '../Componentes/PanelModal';

const TIPOS = [
    { v: 'INDEFINIDO', l: 'Indefinido' },
    { v: 'PLAZO_FIJO', l: 'Plazo fijo' },
    { v: 'POR_OBRA', l: 'Por obra o faena' },
];

const CAUSALES = [
    { v: 'MUTUO_ACUERDO', l: 'Mutuo acuerdo (Art. 159 N°1)' },
    { v: 'RENUNCIA', l: 'Renuncia (Art. 159 N°2)' },
    { v: 'MUERTE', l: 'Muerte del trabajador (Art. 159 N°3)' },
    { v: 'VENCIMIENTO_PLAZO', l: 'Vencimiento del plazo (Art. 159 N°4)' },
    { v: 'FIN_OBRA', l: 'Conclusión obra/faena (Art. 159 N°5)' },
    { v: 'CASO_FORTUITO', l: 'Caso fortuito (Art. 159 N°6)' },
    { v: 'DESPIDO_CONDUCTA', l: 'Conducta (Art. 160)' },
    { v: 'NECESIDADES_EMPRESA', l: 'Necesidades de la empresa (Art. 161)' },
    { v: 'DESAHUCIO', l: 'Desahucio (Art. 161 inc. 2)' },
];

const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';
const FORM_VACIO = {
    tipo: 'INDEFINIDO', fecha_inicio: '', fecha_termino: '', cargo: '', departamento: '',
    horas_semana: 44, tipo_jornada: 'COMPLETA', sueldo_base: '', observaciones: '',
};

const ContratosRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeCrear = tienePermiso('rrhh.contratos.crear');

    const [empleados, setEmpleados] = useState([]);
    const [empleadoId, setEmpleadoId] = useState('');
    const [contratos, setContratos] = useState([]);
    const [cargandoEmpleados, setCargandoEmpleados] = useState(true);
    const [cargandoContratos, setCargandoContratos] = useState(false);

    const [modalAbierto, setModalAbierto] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [form, setForm] = useState(FORM_VACIO);

    useEffect(() => {
        (async () => {
            setCargandoEmpleados(true);
            try {
                const resp = await rrhhApi.empleados.listar();
                const data = resp?.data?.data ?? resp?.data ?? [];
                setEmpleados(Array.isArray(data) ? data : (data.data ?? []));
            } catch (_) { /* toast */ } finally {
                setCargandoEmpleados(false);
            }
        })();
    }, []);

    const cargarContratos = useCallback(async (id) => {
        if (!id) { setContratos([]); return; }
        setCargandoContratos(true);
        try {
            const resp = await rrhhApi.contratos.listarPorEmpleado(id);
            setContratos(resp?.data ?? []);
        } catch (_) { /* toast */ } finally {
            setCargandoContratos(false);
        }
    }, []);

    useEffect(() => { cargarContratos(empleadoId); }, [empleadoId, cargarContratos]);

    const setCampo = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const crear = async (e) => {
        e.preventDefault();
        setGuardando(true);
        try {
            const payload = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
            await rrhhApi.contratos.crear(empleadoId, payload);
            await Swal.fire({ icon: 'success', title: 'Contrato creado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            setModalAbierto(false);
            setForm(FORM_VACIO);
            cargarContratos(empleadoId);
        } catch (_) { /* toast */ } finally {
            setGuardando(false);
        }
    };

    const terminar = async (contrato) => {
        const { value: form } = await Swal.fire({
            title: 'Terminar contrato',
            html: `
                <select id="causal" class="swal2-select" style="width:100%">
                    ${CAUSALES.map((c) => `<option value="${c.v}">${c.l}</option>`).join('')}
                </select>
                <input id="fecha" type="date" class="swal2-input" placeholder="Fecha de término" />
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Terminar',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const causal = document.getElementById('causal').value;
                const fecha = document.getElementById('fecha').value;
                if (!fecha) { Swal.showValidationMessage('Indica la fecha de término'); return false; }
                return { causal_termino: causal, fecha_termino_real: fecha };
            },
        });
        if (!form) return;
        try {
            await rrhhApi.contratos.terminar(contrato.id, form);
            await Swal.fire({ icon: 'success', title: 'Contrato terminado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            cargarContratos(empleadoId);
        } catch (_) { /* toast */ }
    };

    return (
        <div className="max-w-6xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                    <i className="fas fa-file-signature text-emerald-600" />
                    Contratos
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    Histórico de contratos por empleado. Crear un contrato nuevo desactiva el vigente.
                </p>
            </header>

            <EstadoCarga cargando={cargandoEmpleados} mensajeCargando="Cargando empleados..." color="emerald" tamano="compacto">
                <div className="flex flex-wrap items-end gap-3 mb-5">
                    <div className="flex-1 min-w-[260px]">
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Empleado</label>
                        <select value={empleadoId} onChange={(e) => setEmpleadoId(e.target.value)} className={inputCls}>
                            <option value="">Selecciona un empleado...</option>
                            {empleados.map((emp) => (
                                <option key={emp.id} value={emp.id}>
                                    {emp.rut} — {emp.nombres} {emp.apellido_paterno}
                                </option>
                            ))}
                        </select>
                    </div>
                    {puedeCrear && empleadoId && (
                        <button onClick={() => { setForm(FORM_VACIO); setModalAbierto(true); }}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm">
                            <i className="fas fa-plus" /> Nuevo contrato
                        </button>
                    )}
                </div>

                {!empleadoId ? (
                    <div className="bg-slate-50 border border-dashed border-slate-300 rounded-2xl py-12 text-center text-slate-400">
                        <i className="fas fa-hand-pointer text-2xl mb-2 block" />
                        Selecciona un empleado para ver sus contratos.
                    </div>
                ) : (
                    <EstadoCarga cargando={cargandoContratos} mensajeCargando="Cargando contratos..." color="emerald" tamano="compacto">
                        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-slate-600 text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-semibold">Tipo</th>
                                            <th className="px-4 py-3 text-left font-semibold">Cargo</th>
                                            <th className="px-4 py-3 text-left font-semibold">Inicio</th>
                                            <th className="px-4 py-3 text-left font-semibold">Término</th>
                                            <th className="px-4 py-3 text-right font-semibold">Sueldo base</th>
                                            <th className="px-4 py-3 text-left font-semibold">Estado</th>
                                            <th className="px-4 py-3 text-right font-semibold">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {contratos.length === 0 && (
                                            <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400">
                                                Este empleado no tiene contratos.
                                            </td></tr>
                                        )}
                                        {contratos.map((c) => (
                                            <tr key={c.id} className="hover:bg-slate-50">
                                                <td className="px-4 py-3 text-slate-700">{TIPOS.find((t) => t.v === c.tipo)?.l || c.tipo}</td>
                                                <td className="px-4 py-3 text-slate-700">{c.cargo || '—'}</td>
                                                <td className="px-4 py-3 text-slate-600">{formatFecha(c.fecha_inicio)}</td>
                                                <td className="px-4 py-3 text-slate-600">{formatFecha(c.fecha_termino_real || c.fecha_termino)}</td>
                                                <td className="px-4 py-3 text-right font-medium text-slate-900">{formatPesos(c.sueldo_base)}</td>
                                                <td className="px-4 py-3">
                                                    <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-bold ${colorEstado(c.estado)}`}>{c.estado}</span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {puedeCrear && c.estado === 'VIGENTE' && (
                                                        <button onClick={() => terminar(c)}
                                                            className="text-slate-400 hover:text-red-600 transition-colors px-2" title="Terminar contrato">
                                                            <i className="fas fa-circle-stop" />
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
                )}
            </EstadoCarga>

            <PanelModal abierto={modalAbierto} titulo="Nuevo contrato" icono="fas fa-file-signature" onClose={() => setModalAbierto(false)}>
                <form onSubmit={crear} className="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Tipo de contrato *</label>
                        <select required value={form.tipo} onChange={(e) => setCampo('tipo', e.target.value)} className={inputCls}>
                            {TIPOS.map((t) => <option key={t.v} value={t.v}>{t.l}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Sueldo base *</label>
                        <input required type="number" min="0" value={form.sueldo_base} onChange={(e) => setCampo('sueldo_base', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Fecha inicio *</label>
                        <input required type="date" value={form.fecha_inicio} onChange={(e) => setCampo('fecha_inicio', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Fecha término {form.tipo !== 'INDEFINIDO' && '*'}</label>
                        <input type="date" value={form.fecha_termino} onChange={(e) => setCampo('fecha_termino', e.target.value)}
                            required={form.tipo !== 'INDEFINIDO'} className={inputCls} />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Cargo</label>
                        <input value={form.cargo} onChange={(e) => setCampo('cargo', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Departamento</label>
                        <input value={form.departamento} onChange={(e) => setCampo('departamento', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Horas semanales</label>
                        <input type="number" min="1" max="60" value={form.horas_semana} onChange={(e) => setCampo('horas_semana', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Jornada</label>
                        <select value={form.tipo_jornada} onChange={(e) => setCampo('tipo_jornada', e.target.value)} className={inputCls}>
                            <option value="COMPLETA">Completa</option>
                            <option value="PARCIAL">Parcial</option>
                            <option value="TURNO">Por turno</option>
                        </select>
                    </div>
                    <div className="sm:col-span-2">
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Observaciones</label>
                        <textarea rows={2} value={form.observaciones} onChange={(e) => setCampo('observaciones', e.target.value)} className={inputCls} />
                    </div>
                    <div className="sm:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-200">
                        <button type="button" onClick={() => setModalAbierto(false)}
                            className="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 font-semibold text-sm hover:bg-slate-50">Cancelar</button>
                        <button type="submit" disabled={guardando}
                            className="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-60 inline-flex items-center gap-2">
                            {guardando && <i className="fas fa-spinner fa-spin" />} Crear contrato
                        </button>
                    </div>
                </form>
            </PanelModal>
        </div>
    );
};

export default ContratosRrhh;
