import React, { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import { usePermisos } from '../../../Contextos/Permisos';
import rrhhApi from '../Servicios/rrhhApi';
import { colorEstado, formatPesos, MESES, nombreMes } from '../Utilidades/formato';
import PanelModal from '../Componentes/PanelModal';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';

const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';
const anioActual = new Date().getFullYear();
const ANIOS = Array.from({ length: 6 }, (_, i) => anioActual - i);

const tipoBadge = {
    HABER_IMPONIBLE: 'text-emerald-700',
    HABER_NO_IMPONIBLE: 'text-emerald-600',
    DESCUENTO_LEGAL: 'text-red-600',
    DESCUENTO_VOLUNTARIO: 'text-amber-600',
};

const LiquidacionesRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeProcesar = tienePermiso('rrhh.remuneraciones.procesar');

    const [empleados, setEmpleados] = useState([]);
    const [liquidaciones, setLiquidaciones] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [filtros, setFiltros] = useState({ anio: anioActual, mes: new Date().getMonth() + 1 });

    const [modalCalc, setModalCalc] = useState(false);
    const [calculando, setCalculando] = useState(false);
    const [formCalc, setFormCalc] = useState({ empleado_id: '', anio: anioActual, mes: new Date().getMonth() + 1, horas_extra: '', remuneraciones_variables: '', apv_voluntario: '' });

    const [detalle, setDetalle] = useState(null);

    const cargar = useCallback(async (signal) => {
        setCargando(true);
        try {
            const resp = await rrhhApi.liquidaciones.listar(filtros, { signal });
            const data = resp?.data;
            setLiquidaciones(data?.data ?? (Array.isArray(data) ? data : []));
            setCargando(false);
        } catch (err) {
            if (err?.name !== 'AbortError' && err?.code !== 'ERR_CANCELED') {
                setCargando(false);
            }
        }
    }, [filtros]);

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

    const setFc = (k, v) => setFormCalc((f) => ({ ...f, [k]: v }));

    const calcular = async (e) => {
        e.preventDefault();
        setCalculando(true);
        try {
            const payload = Object.fromEntries(Object.entries(formCalc).filter(([, v]) => v !== '' && v !== null));
            const resp = await rrhhApi.liquidaciones.calcular(payload);
            await Swal.fire({ icon: 'success', title: 'Liquidación calculada', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            setModalCalc(false);
            setFiltros({ anio: Number(formCalc.anio), mes: Number(formCalc.mes) });
            if (resp?.data) setDetalle(resp.data);
        } catch (_) { /* toast */ } finally {
            setCalculando(false);
        }
    };

    const verDetalle = async (id) => {
        try {
            const resp = await rrhhApi.liquidaciones.obtener(id);
            setDetalle(resp?.data ?? null);
        } catch (_) { /* toast */ }
    };

    const emitir = async (liq) => {
        const r = await Swal.fire({
            icon: 'question', title: '¿Emitir liquidación?',
            text: 'Una vez emitida no se puede recalcular.',
            showCancelButton: true, confirmButtonText: 'Emitir', confirmButtonColor: '#059669', cancelButtonText: 'Cancelar',
        });
        if (!r.isConfirmed) return;
        try {
            await rrhhApi.liquidaciones.emitir(liq.id);
            await Swal.fire({ icon: 'success', title: 'Liquidación emitida', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            cargar();
            if (detalle?.id === liq.id) verDetalle(liq.id);
        } catch (_) { /* toast */ }
    };

    const anular = async (liq) => {
        const r = await Swal.fire({
            icon: 'warning', title: '¿Anular liquidación?',
            showCancelButton: true, confirmButtonText: 'Anular', confirmButtonColor: '#dc2626', cancelButtonText: 'Volver',
        });
        if (!r.isConfirmed) return;
        try {
            await rrhhApi.liquidaciones.anular(liq.id);
            await Swal.fire({ icon: 'success', title: 'Liquidación anulada', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            cargar();
            if (detalle?.id === liq.id) verDetalle(liq.id);
        } catch (_) { /* toast */ }
    };

    return (
        <div className="max-w-7xl mx-auto p-6 md:p-8">
            <header className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                            <i className="fas fa-money-check-dollar text-emerald-600" />
                            Liquidaciones de Sueldo
                        </h1>
                        <AyudaModulo moduloId="liquidacionesRrhh" size={28} />
                    </div>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Cálculo mensual con AFP, salud, AFC, impuesto único y topes legales.</p>
                </div>
                {puedeProcesar && (
                    <button onClick={() => { setFormCalc((f) => ({ ...f, anio: filtros.anio, mes: filtros.mes })); setModalCalc(true); }}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm">
                        <i className="fas fa-calculator" /> Calcular liquidación
                    </button>
                )}
            </header>

            <div className="flex flex-wrap gap-3 mb-5">
                <div>
                    <label htmlFor="liquidaciones-filtro-anio" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Año</label>
                    <select id="liquidaciones-filtro-anio" value={filtros.anio} onChange={(e) => setFiltros((f) => ({ ...f, anio: Number(e.target.value) }))} className={inputCls}>
                        {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                    </select>
                </div>
                <div>
                    <label htmlFor="liquidaciones-filtro-mes" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Mes</label>
                    <select id="liquidaciones-filtro-mes" value={filtros.mes} onChange={(e) => setFiltros((f) => ({ ...f, mes: Number(e.target.value) }))} className={inputCls}>
                        {MESES.map((m) => <option key={m.valor} value={m.valor}>{m.label}</option>)}
                    </select>
                </div>
            </div>

            <EstadoCarga cargando={cargando} mensajeCargando="Cargando liquidaciones..." color="emerald" tamano="compacto">
                <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Empleado</th>
                                    <th className="px-4 py-3 text-left font-semibold">Período</th>
                                    <th className="px-4 py-3 text-right font-semibold">Imponible</th>
                                    <th className="px-4 py-3 text-right font-semibold">Descuentos</th>
                                    <th className="px-4 py-3 text-right font-semibold">Líquido</th>
                                    <th className="px-4 py-3 text-left font-semibold">Estado</th>
                                    <th className="px-4 py-3 text-right font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                {liquidaciones.length === 0 && (
                                    <EstadoVacio mensaje="Sin liquidaciones para mostrar." />
                                )}
                                {liquidaciones.map((liq) => (
                                    <tr key={liq.id} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                                            {liq.empleado ? `${liq.empleado.nombres} ${liq.empleado.apellido_paterno}` : `#${liq.empleado_id}`}
                                            {liq.empleado?.rut && <div className="text-xs text-slate-400 font-mono">{liq.empleado.rut}</div>}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-400">{nombreMes(liq.mes)} {liq.anio}</td>
                                        <td className="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{formatPesos(liq.total_haberes_imponibles)}</td>
                                        <td className="px-4 py-3 text-right text-red-600">{formatPesos(liq.total_descuentos)}</td>
                                        <td className="px-4 py-3 text-right font-bold text-slate-900 dark:text-slate-100">{formatPesos(liq.liquido_a_pagar)}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-bold ${colorEstado(liq.estado)}`}>{liq.estado}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <button onClick={() => verDetalle(liq.id)} className="text-slate-400 hover:text-emerald-600 px-2" title="Ver detalle">
                                                <i className="fas fa-eye" />
                                            </button>
                                            {puedeProcesar && liq.estado === 'BORRADOR' && (
                                                <button onClick={() => emitir(liq)} className="text-slate-400 hover:text-emerald-600 px-2" title="Emitir">
                                                    <i className="fas fa-paper-plane" />
                                                </button>
                                            )}
                                            {puedeProcesar && liq.estado === 'EMITIDA' && (
                                                <button onClick={() => anular(liq)} className="text-slate-400 hover:text-red-600 px-2" title="Anular">
                                                    <i className="fas fa-ban" />
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

            <PanelModal abierto={modalCalc} titulo="Calcular liquidación" icono="fas fa-calculator" onClose={() => setModalCalc(false)}>
                <form onSubmit={calcular} className="grid sm:grid-cols-2 gap-3">
                    <div className="sm:col-span-2">
                        <label htmlFor="liquidacion-empleado" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Empleado *</label>
                        <select id="liquidacion-empleado" required value={formCalc.empleado_id} onChange={(e) => setFc('empleado_id', e.target.value)} className={inputCls}>
                            <option value="">Selecciona...</option>
                            {empleados.map((emp) => <option key={emp.id} value={emp.id}>{emp.rut} — {emp.nombres} {emp.apellido_paterno}</option>)}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="liquidacion-anio" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Año *</label>
                        <select id="liquidacion-anio" value={formCalc.anio} onChange={(e) => setFc('anio', e.target.value)} className={inputCls}>
                            {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="liquidacion-mes" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Mes *</label>
                        <select id="liquidacion-mes" value={formCalc.mes} onChange={(e) => setFc('mes', e.target.value)} className={inputCls}>
                            {MESES.map((m) => <option key={m.valor} value={m.valor}>{m.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="liquidacion-horas-extra" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Horas extra</label>
                        <input id="liquidacion-horas-extra" type="number" min="0" step="0.5" value={formCalc.horas_extra} onChange={(e) => setFc('horas_extra', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label htmlFor="liquidacion-remuneraciones-variables" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Remuneraciones variables</label>
                        <input id="liquidacion-remuneraciones-variables" type="number" min="0" value={formCalc.remuneraciones_variables} onChange={(e) => setFc('remuneraciones_variables', e.target.value)} className={inputCls} />
                    </div>
                    <div className="sm:col-span-2">
                        <label htmlFor="liquidacion-apv-voluntario" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">APV voluntario</label>
                        <input id="liquidacion-apv-voluntario" type="number" min="0" value={formCalc.apv_voluntario} onChange={(e) => setFc('apv_voluntario', e.target.value)} className={inputCls} />
                    </div>
                    <div className="sm:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" onClick={() => setModalCalc(false)} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 font-semibold text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="submit" disabled={calculando} className="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-60 inline-flex items-center gap-2">
                            {calculando && <i className="fas fa-spinner fa-spin" />} Calcular
                        </button>
                    </div>
                </form>
            </PanelModal>

            <PanelModal abierto={!!detalle} titulo="Detalle de liquidación" icono="fas fa-receipt" ancho="max-w-3xl" onClose={() => setDetalle(null)}>
                {detalle && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-bold text-slate-900 dark:text-slate-100">
                                    {detalle.empleado ? `${detalle.empleado.nombres} ${detalle.empleado.apellido_paterno}` : `Empleado #${detalle.empleado_id}`}
                                </p>
                                <p className="text-sm text-slate-500 dark:text-slate-400">{nombreMes(detalle.mes)} {detalle.anio}</p>
                            </div>
                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold ${colorEstado(detalle.estado)}`}>{detalle.estado}</span>
                        </div>

                        <div className="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                            <table className="w-full text-sm">
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                    {(detalle.detalles ?? []).map((d) => (
                                        <tr key={d.id}>
                                            <td className="px-4 py-2 text-slate-700 dark:text-slate-300">{d.nombre_concepto}</td>
                                            <td className={`px-4 py-2 text-right font-medium ${tipoBadge[d.tipo] || 'text-slate-700'}`}>
                                                {d.tipo?.startsWith('DESCUENTO') ? '-' : ''}{formatPesos(d.monto)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                            <div className="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                <p className="text-xs text-slate-500 dark:text-slate-400">Total haberes</p>
                                <p className="font-bold text-slate-900 dark:text-slate-100">{formatPesos(detalle.total_haberes)}</p>
                            </div>
                            <div className="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                <p className="text-xs text-slate-500 dark:text-slate-400">Total descuentos</p>
                                <p className="font-bold text-red-600">{formatPesos(detalle.total_descuentos)}</p>
                            </div>
                            <div className="bg-emerald-50 rounded-lg p-3 col-span-2">
                                <p className="text-xs text-emerald-700">Líquido a pagar</p>
                                <p className="font-black text-emerald-700 text-lg">{formatPesos(detalle.liquido_a_pagar)}</p>
                            </div>
                        </div>

                        {puedeProcesar && (
                            <div className="flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                                {detalle.estado === 'BORRADOR' && (
                                    <button onClick={() => emitir(detalle)} className="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm inline-flex items-center gap-2">
                                        <i className="fas fa-paper-plane" /> Emitir
                                    </button>
                                )}
                                {detalle.estado === 'EMITIDA' && (
                                    <button onClick={() => anular(detalle)} className="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold text-sm inline-flex items-center gap-2">
                                        <i className="fas fa-ban" /> Anular
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </PanelModal>
        </div>
    );
};

export default LiquidacionesRrhh;
