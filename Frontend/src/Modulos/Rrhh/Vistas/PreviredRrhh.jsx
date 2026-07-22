import React, { useState } from 'react';
import Swal from 'sweetalert2';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';
import { usePermisos } from '../../../Contextos/Permisos';
import rrhhApi from '../Servicios/rrhhApi';
import { MESES, nombreMes } from '../Utilidades/formato';

const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';
const anioActual = new Date().getFullYear();
const ANIOS = Array.from({ length: 6 }, (_, i) => anioActual - i);

const PreviredRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeProcesar = tienePermiso('rrhh.remuneraciones.procesar');

    const [periodo, setPeriodo] = useState({ anio: anioActual, mes: new Date().getMonth() + 1 });
    const [preview, setPreview] = useState(null);
    const [cargando, setCargando] = useState(false);
    const [descargando, setDescargando] = useState(false);

    const previsualizar = async () => {
        setCargando(true);
        setPreview(null);
        try {
            const resp = await rrhhApi.previred.previsualizar(periodo.anio, periodo.mes);
            setPreview(resp ?? null);
        } catch (_) { /* toast */ } finally {
            setCargando(false);
        }
    };

    const descargar = async () => {
        setDescargando(true);
        try {
            await rrhhApi.previred.descargar(periodo.anio, periodo.mes);
        } catch (_) {
            // toast global; si falla por no haber liquidaciones, el backend responde 422.
        } finally {
            setDescargando(false);
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                        <i className="fas fa-file-csv text-emerald-600" />
                        Archivo Previred
                    </h1>
                    <AyudaModulo moduloId="previredRrhh" size={28} />
                </div>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Genera la planilla previsional mensual (CSV) desde las liquidaciones emitidas, para subir a previred.com.
                </p>
            </header>

            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 mb-6">
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label htmlFor="previred-anio" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Año</label>
                        <select id="previred-anio" value={periodo.anio} onChange={(e) => { setPeriodo((p) => ({ ...p, anio: Number(e.target.value) })); setPreview(null); }} className={inputCls}>
                            {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="previred-mes" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Mes</label>
                        <select id="previred-mes" value={periodo.mes} onChange={(e) => { setPeriodo((p) => ({ ...p, mes: Number(e.target.value) })); setPreview(null); }} className={inputCls}>
                            {MESES.map((m) => <option key={m.valor} value={m.valor}>{m.label}</option>)}
                        </select>
                    </div>
                    <button onClick={previsualizar} disabled={cargando}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 font-bold text-sm disabled:opacity-50">
                        {cargando ? <i className="fas fa-spinner fa-spin" /> : <i className="fas fa-eye" />} Previsualizar
                    </button>
                    {puedeProcesar && (
                        <button onClick={descargar} disabled={descargando}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50">
                            {descargando ? <i className="fas fa-spinner fa-spin" /> : <i className="fas fa-download" />} Descargar CSV
                        </button>
                    )}
                </div>
            </div>

            {preview && (
                <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 className="font-bold text-slate-900 dark:text-slate-100">
                            {nombreMes(periodo.mes)} {periodo.anio}
                        </h3>
                        <span className="text-sm text-slate-500 dark:text-slate-400">{preview.total_trabajadores} trabajador(es)</span>
                    </div>
                    <div className="overflow-x-auto">
                        {/* Filas posicionales del archivo de 105 campos (sin encabezado) */}
                        <table className="w-full text-xs whitespace-nowrap">
                            <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 uppercase">
                                <tr>
                                    {(preview.filas?.[0] ?? []).map((_, idx) => (
                                        <th key={idx} className="px-3 py-2 text-left font-semibold">{idx + 1}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                {(preview.filas ?? []).map((fila, i) => (
                                    <tr key={i} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        {fila.map((valor, idx) => (
                                            <td key={idx} className="px-3 py-2 text-slate-700 dark:text-slate-300 font-mono">{valor}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {!preview && !cargando && (
                <div className="bg-slate-50 dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-600 rounded-2xl py-12 text-center text-slate-400">
                    <i className="fas fa-file-csv text-3xl mb-3 block" />
                    Selecciona un período y previsualiza la planilla antes de descargar.
                </div>
            )}
        </div>
    );
};

export default PreviredRrhh;
