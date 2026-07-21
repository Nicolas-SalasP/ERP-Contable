import React, { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import { usePermisos } from '../../../Contextos/Permisos';
import rrhhApi from '../Servicios/rrhhApi';
import { colorEstado, formatFecha, formatPesos } from '../Utilidades/formato';
import PanelModal from '../Componentes/PanelModal';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';

const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const CAUSALES = [
    { v: 'NECESIDADES_EMPRESA', l: 'Necesidades de la empresa (Art. 161) — con indemnización' },
    { v: 'DESAHUCIO', l: 'Desahucio (Art. 161 inc. 2)' },
    { v: 'MUTUO_ACUERDO', l: 'Mutuo acuerdo (Art. 159 N°1)' },
    { v: 'RENUNCIA', l: 'Renuncia (Art. 159 N°2)' },
    { v: 'MUERTE', l: 'Muerte del trabajador (Art. 159 N°3)' },
    { v: 'VENCIMIENTO_PLAZO', l: 'Vencimiento del plazo (Art. 159 N°4)' },
    { v: 'FIN_OBRA', l: 'Conclusión obra/faena (Art. 159 N°5)' },
    { v: 'CASO_FORTUITO', l: 'Caso fortuito (Art. 159 N°6)' },
    { v: 'DESPIDO_CONDUCTA', l: 'Conducta (Art. 160)' },
];

const FORM_VACIO = {
    empleado_id: '', contrato_id: '', causal: 'NECESIDADES_EMPRESA', fecha_termino: '',
    promedio_variables: '', aviso_previo: false, haberes_pendientes: '', descuentos_pendientes: '', observaciones: '',
};

const Fila = ({ label, valor }) => (
    <div className="flex justify-between py-1.5 text-sm border-b border-slate-100 dark:border-slate-700 last:border-0">
        <span className="text-slate-600 dark:text-slate-400">{label}</span>
        <span className="font-medium text-slate-900 dark:text-slate-100">{valor}</span>
    </div>
);

const FiniquitosRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeProcesar = tienePermiso('rrhh.remuneraciones.procesar');

    const [finiquitos, setFiniquitos] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [empleados, setEmpleados] = useState([]);
    const [contratos, setContratos] = useState([]);

    const [modalCalc, setModalCalc] = useState(false);
    const [calculando, setCalculando] = useState(false);
    const [form, setForm] = useState(FORM_VACIO);
    const [detalle, setDetalle] = useState(null);

    const cargar = useCallback(async (signal) => {
        setCargando(true);
        try {
            const resp = await rrhhApi.finiquitos.listar({ signal });
            const data = resp?.data;
            setFiniquitos(data?.data ?? (Array.isArray(data) ? data : []));
            setCargando(false);
        } catch (err) {
            if (err?.name !== 'AbortError' && err?.code !== 'ERR_CANCELED') {
                setCargando(false);
            }
        }
    }, []);

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

    const onEmpleado = (() => {
        let abortController = null;
        return async (id) => {
            if (abortController) abortController.abort();
            abortController = new AbortController();
            const signal = abortController.signal;

            setForm((f) => ({ ...f, empleado_id: id, contrato_id: '' }));
            setContratos([]);
            if (!id) return;
            try {
                const resp = await rrhhApi.contratos.listarPorEmpleado(id, { signal });
                if (!signal.aborted) {
                    setContratos((resp?.data ?? []).filter((c) => c.estado === 'VIGENTE'));
                }
            } catch (err) {
                if (err?.name === 'AbortError' || err?.code === 'ERR_CANCELED') return;
            }
        };
    })();

    const calcular = async (e) => {
        e.preventDefault();
        setCalculando(true);
        try {
            // empleado_id solo sirve para filtrar contratos en la UI; el backend espera contrato_id.
            const payload = Object.fromEntries(
                Object.entries(form).filter(([k, v]) =>
                    k !== 'empleado_id' && k !== 'aviso_previo' && v !== '' && v !== null),
            );
            payload.aviso_previo = form.aviso_previo;
            const resp = await rrhhApi.finiquitos.calcular(payload);
            await Swal.fire({ icon: 'success', title: 'Finiquito calculado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            setModalCalc(false);
            setForm(FORM_VACIO);
            cargar();
            if (resp?.data) setDetalle(resp.data);
        } catch (_) { /* toast */ } finally {
            setCalculando(false);
        }
    };

    const verDetalle = async (id) => {
        try {
            const resp = await rrhhApi.finiquitos.obtener(id);
            setDetalle(resp?.data ?? null);
        } catch (_) { /* toast */ }
    };

    const firmar = async (fin) => {
        const r = await Swal.fire({
            icon: 'warning', title: '¿Firmar finiquito?',
            text: 'Al firmar se termina el contrato del trabajador. Esta acción es definitiva.',
            showCancelButton: true, confirmButtonText: 'Firmar', confirmButtonColor: '#059669', cancelButtonText: 'Cancelar',
        });
        if (!r.isConfirmed) return;
        try {
            await rrhhApi.finiquitos.firmar(fin.id);
            await Swal.fire({ icon: 'success', title: 'Finiquito firmado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            cargar();
            if (detalle?.id === fin.id) verDetalle(fin.id);
        } catch (_) { /* toast */ }
    };

    return (
        <div className="max-w-6xl mx-auto p-6 md:p-8">
            <header className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                            <i className="fas fa-handshake-slash text-emerald-600" />
                            Finiquitos
                        </h1>
                        <AyudaModulo moduloId="finiquitosRrhh" size={28} />
                    </div>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Indemnización por años de servicio, aviso previo y vacaciones proporcionales (Código del Trabajo).</p>
                </div>
                {puedeProcesar && (
                    <button onClick={() => { setForm(FORM_VACIO); setContratos([]); setModalCalc(true); }}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm">
                        <i className="fas fa-calculator" /> Calcular finiquito
                    </button>
                )}
            </header>

            <EstadoCarga cargando={cargando} mensajeCargando="Cargando finiquitos..." color="emerald" tamano="compacto">
                <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Empleado</th>
                                    <th className="px-4 py-3 text-left font-semibold">Causal</th>
                                    <th className="px-4 py-3 text-left font-semibold">Término</th>
                                    <th className="px-4 py-3 text-right font-semibold">Total neto</th>
                                    <th className="px-4 py-3 text-left font-semibold">Estado</th>
                                    <th className="px-4 py-3 text-right font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                {finiquitos.length === 0 && (
                                    <EstadoVacio mensaje="Sin finiquitos calculados." />
                                )}
                                {finiquitos.map((fin) => (
                                    <tr key={fin.id} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                                            {fin.empleado ? `${fin.empleado.nombres} ${fin.empleado.apellido_paterno}` : `#${fin.empleado_id}`}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs">{fin.causal?.replaceAll('_', ' ')}</td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-400">{formatFecha(fin.fecha_termino)}</td>
                                        <td className="px-4 py-3 text-right font-bold text-slate-900 dark:text-slate-100">{formatPesos(fin.total_neto)}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-bold ${colorEstado(fin.estado)}`}>{fin.estado}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <button onClick={() => verDetalle(fin.id)} className="text-slate-400 hover:text-emerald-600 px-2" title="Ver detalle">
                                                <i className="fas fa-eye" />
                                            </button>
                                            {puedeProcesar && fin.estado === 'BORRADOR' && (
                                                <button onClick={() => firmar(fin)} className="text-slate-400 hover:text-emerald-600 px-2" title="Firmar">
                                                    <i className="fas fa-signature" />
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

            <PanelModal abierto={modalCalc} titulo="Calcular finiquito" icono="fas fa-calculator" onClose={() => setModalCalc(false)}>
                <form onSubmit={calcular} className="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label htmlFor="finiquito-empleado" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Empleado *</label>
                        <select id="finiquito-empleado" required value={form.empleado_id} onChange={(e) => onEmpleado(e.target.value)} className={inputCls}>
                            <option value="">Selecciona...</option>
                            {empleados.map((emp) => <option key={emp.id} value={emp.id}>{emp.rut} — {emp.nombres} {emp.apellido_paterno}</option>)}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="finiquito-contrato" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Contrato vigente *</label>
                        <select id="finiquito-contrato" required value={form.contrato_id} onChange={(e) => setCampo('contrato_id', e.target.value)} className={inputCls} disabled={!form.empleado_id}>
                            <option value="">{form.empleado_id ? 'Selecciona...' : 'Elige empleado primero'}</option>
                            {contratos.map((c) => <option key={c.id} value={c.id}>{c.cargo || c.tipo} — desde {formatFecha(c.fecha_inicio)}</option>)}
                        </select>
                    </div>
                    <div className="sm:col-span-2">
                        <label htmlFor="finiquito-causal" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Causal de término *</label>
                        <select id="finiquito-causal" required value={form.causal} onChange={(e) => setCampo('causal', e.target.value)} className={inputCls}>
                            {CAUSALES.map((c) => <option key={c.v} value={c.v}>{c.l}</option>)}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="finiquito-fecha-termino" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Fecha de término *</label>
                        <input id="finiquito-fecha-termino" required type="date" value={form.fecha_termino} onChange={(e) => setCampo('fecha_termino', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label htmlFor="finiquito-promedio-variables" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Promedio remuneraciones variables</label>
                        <input id="finiquito-promedio-variables" type="number" min="0" value={form.promedio_variables} onChange={(e) => setCampo('promedio_variables', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label htmlFor="finiquito-haberes-pendientes" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Haberes pendientes</label>
                        <input id="finiquito-haberes-pendientes" type="number" min="0" value={form.haberes_pendientes} onChange={(e) => setCampo('haberes_pendientes', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label htmlFor="finiquito-descuentos-pendientes" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Descuentos pendientes</label>
                        <input id="finiquito-descuentos-pendientes" type="number" min="0" value={form.descuentos_pendientes} onChange={(e) => setCampo('descuentos_pendientes', e.target.value)} className={inputCls} />
                    </div>
                    <div className="sm:col-span-2">
                        <label htmlFor="finiquito-aviso-previo" className="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input id="finiquito-aviso-previo" type="checkbox" checked={form.aviso_previo} onChange={(e) => setCampo('aviso_previo', e.target.checked)}
                                className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            Se dio aviso previo de 30 días (no se paga mes sustitutivo)
                        </label>
                    </div>
                    <div className="sm:col-span-2">
                        <label htmlFor="finiquito-observaciones" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Observaciones</label>
                        <textarea id="finiquito-observaciones" rows={2} value={form.observaciones} onChange={(e) => setCampo('observaciones', e.target.value)} className={inputCls} />
                    </div>
                    <div className="sm:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" onClick={() => setModalCalc(false)} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 font-semibold text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="submit" disabled={calculando} className="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-60 inline-flex items-center gap-2">
                            {calculando && <i className="fas fa-spinner fa-spin" />} Calcular
                        </button>
                    </div>
                </form>
            </PanelModal>

            <PanelModal abierto={!!detalle} titulo="Detalle de finiquito" icono="fas fa-file-contract" onClose={() => setDetalle(null)}>
                {detalle && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <p className="font-bold text-slate-900 dark:text-slate-100">
                                {detalle.empleado ? `${detalle.empleado.nombres} ${detalle.empleado.apellido_paterno}` : `Empleado #${detalle.empleado_id}`}
                            </p>
                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold ${colorEstado(detalle.estado)}`}>{detalle.estado}</span>
                        </div>
                        <div className="bg-slate-50 dark:bg-slate-900 rounded-xl p-4">
                            <Fila label="Años de servicio" valor={`${detalle.anios_calculo} (${detalle.anios_servicio} completos + ${detalle.meses_fraccion} meses)`} />
                            <Fila label="Base de cálculo (tope 90 UF)" valor={formatPesos(detalle.remuneracion_base_calculo)} />
                            <Fila label="Indemnización años de servicio" valor={formatPesos(detalle.monto_indemnizacion_anos)} />
                            <Fila label="Aviso previo sustitutivo" valor={formatPesos(detalle.monto_aviso_previo)} />
                            <Fila label={`Vacaciones proporcionales (${detalle.dias_vacaciones_proporcionales} días)`} valor={formatPesos(detalle.monto_vacaciones_proporcionales)} />
                            <Fila label="Haberes pendientes" valor={formatPesos(detalle.haberes_pendientes)} />
                            <Fila label="Descuentos pendientes" valor={`-${formatPesos(detalle.descuentos_pendientes)}`} />
                        </div>
                        <div className="bg-emerald-50 rounded-lg p-3">
                            <p className="text-xs text-emerald-700">Total neto a pagar</p>
                            <p className="font-black text-emerald-700 text-lg">{formatPesos(detalle.total_neto)}</p>
                        </div>
                        {puedeProcesar && detalle.estado === 'BORRADOR' && (
                            <div className="flex justify-end pt-2 border-t border-slate-200 dark:border-slate-700">
                                <button onClick={() => firmar(detalle)} className="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm inline-flex items-center gap-2">
                                    <i className="fas fa-signature" /> Firmar finiquito
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </PanelModal>
        </div>
    );
};

export default FiniquitosRrhh;
