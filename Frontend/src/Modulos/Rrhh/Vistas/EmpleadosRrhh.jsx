import React, { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import { usePermisos } from '../../../Contextos/Permisos';
import rrhhApi from '../Servicios/rrhhApi';
import { colorEstado, formatFecha } from '../Utilidades/formato';
import PanelModal from '../Componentes/PanelModal';

const AFPS = ['Capital', 'Cuprum', 'Habitat', 'Modelo', 'PlanVital', 'ProVida', 'Uno'];

const FORM_VACIO = {
    rut: '', nombres: '', apellido_paterno: '', apellido_materno: '',
    fecha_nacimiento: '', sexo: '', email: '', telefono: '',
    direccion: '', ciudad: '', region: '',
    afp: '', tipo_salud: 'FONASA', isapre_nombre: '', isapre_plan_uf: '',
    banco_nombre: '', banco_tipo_cuenta: '', banco_numero_cuenta: '',
    fecha_ingreso_empresa: '', estado: 'ACTIVO', observaciones: '',
};

const Campo = ({ label, children, full = false }) => (
    <div className={full ? 'sm:col-span-2' : ''}>
        <label className="block text-xs font-semibold text-slate-600 mb-1">{label}</label>
        {children}
    </div>
);

const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const EmpleadosRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeCrear = tienePermiso('rrhh.empleados.crear');
    const puedeEditar = tienePermiso('rrhh.empleados.editar');

    const [empleados, setEmpleados] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [busqueda, setBusqueda] = useState('');
    const [modalAbierto, setModalAbierto] = useState(false);
    const [editandoId, setEditandoId] = useState(null);
    const [guardando, setGuardando] = useState(false);
    const [form, setForm] = useState(FORM_VACIO);

    const cargar = useCallback(async () => {
        setCargando(true);
        try {
            const resp = await rrhhApi.empleados.listar(busqueda ? { buscar: busqueda } : {});
            const data = resp?.data?.data ?? resp?.data ?? [];
            setEmpleados(Array.isArray(data) ? data : (data.data ?? []));
        } catch (_) {
            // api.js ya notificó al usuario.
        } finally {
            setCargando(false);
        }
    }, [busqueda]);

    useEffect(() => { cargar(); }, [cargar]);

    const abrirCrear = () => {
        setForm(FORM_VACIO);
        setEditandoId(null);
        setModalAbierto(true);
    };

    const abrirEditar = (emp) => {
        setForm({
            ...FORM_VACIO,
            ...emp,
            fecha_nacimiento: emp.fecha_nacimiento?.slice(0, 10) || '',
            fecha_ingreso_empresa: emp.fecha_ingreso_empresa?.slice(0, 10) || '',
            banco_numero_cuenta: '', // nunca viene del backend (cifrado/oculto)
        });
        setEditandoId(emp.id);
        setModalAbierto(true);
    };

    const setCampo = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const guardar = async (e) => {
        e.preventDefault();
        setGuardando(true);
        try {
            // Limpia campos vacíos para no enviar strings vacíos donde el backend espera null.
            const payload = Object.fromEntries(
                Object.entries(form).filter(([, v]) => v !== '' && v !== null),
            );
            if (editandoId) {
                await rrhhApi.empleados.actualizar(editandoId, payload);
            } else {
                await rrhhApi.empleados.crear(payload);
            }
            await Swal.fire({
                icon: 'success',
                title: editandoId ? 'Empleado actualizado' : 'Empleado creado',
                toast: true, position: 'top-end', showConfirmButton: false, timer: 2000,
            });
            setModalAbierto(false);
            cargar();
        } catch (_) {
            // toast global
        } finally {
            setGuardando(false);
        }
    };

    return (
        <div className="max-w-7xl mx-auto p-6 md:p-8">
            <header className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                            <i className="fas fa-id-card-clip text-emerald-600" />
                            Ficha de Personal
                        </h1>
                        <AyudaModulo moduloId="empleadosRrhh" size={28} />
                    </div>
                    <p className="text-sm text-slate-500 mt-1">
                        Empleados de la empresa: datos personales, previsionales y bancarios (cifrados).
                    </p>
                </div>
                {puedeCrear && (
                    <button onClick={abrirCrear}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm transition-colors">
                        <i className="fas fa-plus" /> Nuevo empleado
                    </button>
                )}
            </header>

            <div className="mb-4">
                <div className="relative max-w-md">
                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
                    <input
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Buscar por nombre o RUT..."
                        className="w-full pl-9 pr-3 py-2.5 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 outline-none"
                    />
                </div>
            </div>

            <EstadoCarga cargando={cargando} mensajeCargando="Cargando empleados..." color="emerald" tamano="compacto">
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-slate-600 text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">RUT</th>
                                    <th className="px-4 py-3 text-left font-semibold">Nombre</th>
                                    <th className="px-4 py-3 text-left font-semibold">AFP</th>
                                    <th className="px-4 py-3 text-left font-semibold">Salud</th>
                                    <th className="px-4 py-3 text-left font-semibold">Ingreso</th>
                                    <th className="px-4 py-3 text-left font-semibold">Estado</th>
                                    <th className="px-4 py-3 text-right font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {empleados.length === 0 && (
                                    <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400">
                                        <i className="fas fa-users-slash text-2xl mb-2 block" />
                                        No hay empleados registrados.
                                    </td></tr>
                                )}
                                {empleados.map((emp) => (
                                    <tr key={emp.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3 font-mono text-slate-700">{emp.rut}</td>
                                        <td className="px-4 py-3 font-medium text-slate-900">
                                            {emp.nombres} {emp.apellido_paterno} {emp.apellido_materno || ''}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{emp.afp || '—'}</td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {emp.tipo_salud === 'ISAPRE' ? (emp.isapre_nombre || 'ISAPRE') : (emp.tipo_salud || '—')}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{formatFecha(emp.fecha_ingreso_empresa)}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-bold ${colorEstado(emp.estado)}`}>
                                                {emp.estado || 'ACTIVO'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {puedeEditar && (
                                                <button onClick={() => abrirEditar(emp)}
                                                    className="text-slate-400 hover:text-emerald-600 transition-colors px-2" title="Editar">
                                                    <i className="fas fa-pen" />
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

            <PanelModal
                abierto={modalAbierto}
                titulo={editandoId ? 'Editar empleado' : 'Nuevo empleado'}
                icono="fas fa-id-card-clip"
                onClose={() => setModalAbierto(false)}
            >
                <form onSubmit={guardar} className="space-y-5">
                    <fieldset>
                        <legend className="text-xs font-bold uppercase text-emerald-700 mb-2">Identificación</legend>
                        <div className="grid sm:grid-cols-2 gap-3">
                            <Campo label="RUT *">
                                <input required value={form.rut} onChange={(e) => setCampo('rut', e.target.value)}
                                    placeholder="12.345.678-9" className={inputCls} />
                            </Campo>
                            <Campo label="Nombres *">
                                <input required value={form.nombres} onChange={(e) => setCampo('nombres', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Apellido paterno *">
                                <input required value={form.apellido_paterno} onChange={(e) => setCampo('apellido_paterno', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Apellido materno">
                                <input value={form.apellido_materno} onChange={(e) => setCampo('apellido_materno', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Fecha nacimiento">
                                <input type="date" value={form.fecha_nacimiento} onChange={(e) => setCampo('fecha_nacimiento', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Sexo">
                                <select value={form.sexo} onChange={(e) => setCampo('sexo', e.target.value)} className={inputCls}>
                                    <option value="">—</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                    <option value="O">Otro</option>
                                </select>
                            </Campo>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend className="text-xs font-bold uppercase text-emerald-700 mb-2">Contacto</legend>
                        <div className="grid sm:grid-cols-2 gap-3">
                            <Campo label="Email">
                                <input type="email" value={form.email} onChange={(e) => setCampo('email', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Teléfono">
                                <input value={form.telefono} onChange={(e) => setCampo('telefono', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Dirección" full>
                                <input value={form.direccion} onChange={(e) => setCampo('direccion', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Ciudad">
                                <input value={form.ciudad} onChange={(e) => setCampo('ciudad', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Región">
                                <input value={form.region} onChange={(e) => setCampo('region', e.target.value)} className={inputCls} />
                            </Campo>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend className="text-xs font-bold uppercase text-emerald-700 mb-2">Previsión y salud</legend>
                        <div className="grid sm:grid-cols-2 gap-3">
                            <Campo label="AFP">
                                <select value={form.afp} onChange={(e) => setCampo('afp', e.target.value)} className={inputCls}>
                                    <option value="">—</option>
                                    {AFPS.map((a) => <option key={a} value={a}>{a}</option>)}
                                </select>
                            </Campo>
                            <Campo label="Sistema de salud">
                                <select value={form.tipo_salud} onChange={(e) => setCampo('tipo_salud', e.target.value)} className={inputCls}>
                                    <option value="FONASA">FONASA</option>
                                    <option value="ISAPRE">ISAPRE</option>
                                </select>
                            </Campo>
                            {form.tipo_salud === 'ISAPRE' && (
                                <>
                                    <Campo label="ISAPRE">
                                        <input value={form.isapre_nombre} onChange={(e) => setCampo('isapre_nombre', e.target.value)} className={inputCls} />
                                    </Campo>
                                    <Campo label="Plan pactado (UF)">
                                        <input type="number" step="0.01" value={form.isapre_plan_uf} onChange={(e) => setCampo('isapre_plan_uf', e.target.value)} className={inputCls} />
                                    </Campo>
                                </>
                            )}
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend className="text-xs font-bold uppercase text-emerald-700 mb-2">
                            Datos bancarios <span className="font-normal normal-case text-slate-400">(se almacenan cifrados — Ley 21.719)</span>
                        </legend>
                        <div className="grid sm:grid-cols-3 gap-3">
                            <Campo label="Banco">
                                <input value={form.banco_nombre} onChange={(e) => setCampo('banco_nombre', e.target.value)} className={inputCls} />
                            </Campo>
                            <Campo label="Tipo de cuenta">
                                <input value={form.banco_tipo_cuenta} onChange={(e) => setCampo('banco_tipo_cuenta', e.target.value)} placeholder="Corriente / Vista" className={inputCls} />
                            </Campo>
                            <Campo label="N° de cuenta">
                                <input value={form.banco_numero_cuenta} onChange={(e) => setCampo('banco_numero_cuenta', e.target.value)}
                                    placeholder={editandoId ? '••••• (sin cambios)' : ''} className={inputCls} />
                            </Campo>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend className="text-xs font-bold uppercase text-emerald-700 mb-2">Laboral</legend>
                        <div className="grid sm:grid-cols-2 gap-3">
                            <Campo label="Fecha ingreso a la empresa">
                                <input type="date" value={form.fecha_ingreso_empresa} onChange={(e) => setCampo('fecha_ingreso_empresa', e.target.value)} className={inputCls} />
                            </Campo>
                            {editandoId && (
                                <Campo label="Estado">
                                    <select value={form.estado} onChange={(e) => setCampo('estado', e.target.value)} className={inputCls}>
                                        <option value="ACTIVO">Activo</option>
                                        <option value="INACTIVO">Inactivo</option>
                                        <option value="LICENCIA">Licencia</option>
                                    </select>
                                </Campo>
                            )}
                            <Campo label="Observaciones" full>
                                <textarea rows={2} value={form.observaciones} onChange={(e) => setCampo('observaciones', e.target.value)} className={inputCls} />
                            </Campo>
                        </div>
                    </fieldset>

                    <div className="flex justify-end gap-2 pt-2 border-t border-slate-200">
                        <button type="button" onClick={() => setModalAbierto(false)}
                            className="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 font-semibold text-sm hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" disabled={guardando}
                            className="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-60 inline-flex items-center gap-2">
                            {guardando && <i className="fas fa-spinner fa-spin" />}
                            {editandoId ? 'Guardar cambios' : 'Crear empleado'}
                        </button>
                    </div>
                </form>
            </PanelModal>
        </div>
    );
};

export default EmpleadosRrhh;
