import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';

const formatCLP = (n) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(n ?? 0);

const BADGE_ESTADO = {
    ejecutada: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    simulada:  'bg-blue-100 text-blue-700 border-blue-200',
    anulada:   'bg-rose-100 text-rose-700 border-rose-200',
};

const BADGE_TIPO = {
    mensual: 'bg-violet-100 text-violet-700 border-violet-200',
    anual:   'bg-slate-100 text-slate-600 border-slate-200',
};

const TabHistorial = ({ anioInicial }) => {
    const [anio, setAnio]           = useState(anioInicial ?? null);
    const [ejecuciones, setEjecuciones] = useState([]);
    const [loading, setLoading]     = useState(true);
    const [expandido, setExpandido] = useState(null);
    const anioActual = new Date().getFullYear();

    useEffect(() => {
        cargar();
    }, [anio]);

    const cargar = async () => {
        setLoading(true);
        try {
            const url = anio ? `/correccion-monetaria/historial?anio=${anio}` : '/correccion-monetaria/historial';
            const res = await api.get(url);
            if (res.success) setEjecuciones(res.data);
        } catch (_) {
        } finally {
            setLoading(false);
        }
    };

    const toggleExpandido = (id) => setExpandido(prev => prev === id ? null : id);

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 className="text-xl font-black text-slate-800">Historial de Ejecuciones</h2>
                    <p className="text-sm text-slate-500 mt-0.5">
                        {ejecuciones.length} ejecuciones registradas {anio ? `en ${anio}` : 'en total'}.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest">Filtrar año</label>
                    <select
                        value={anio ?? ''}
                        onChange={e => setAnio(e.target.value ? parseInt(e.target.value) : null)}
                        className="border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-slate-700 bg-white focus:ring-2 focus:ring-violet-500 outline-none cursor-pointer"
                    >
                        <option value="">Todos</option>
                        {[anioActual - 2, anioActual - 1, anioActual, anioActual + 1].map(y => (
                            <option key={y} value={y}>{y}</option>
                        ))}
                    </select>
                </div>
            </div>

            {loading ? (
                <div className="flex items-center justify-center py-20 text-slate-400">
                    <i className="fas fa-spinner fa-spin text-2xl mr-3"></i> Cargando historial...
                </div>
            ) : ejecuciones.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
                    <i className="fas fa-history text-5xl text-slate-200"></i>
                    <p className="font-bold">No hay ejecuciones registradas {anio ? `para ${anio}` : ''}.</p>
                    <p className="text-sm">Usa el Simulador para ejecutar la primera corrección monetaria.</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {ejecuciones.map(ej => (
                        <div key={ej.id} className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                            <button
                                onClick={() => toggleExpandido(ej.id)}
                                className="w-full flex flex-col sm:flex-row sm:items-center justify-between px-6 py-4 text-left hover:bg-slate-50 transition-colors gap-3"
                            >
                                <div className="flex items-center gap-4 flex-wrap">
                                    <div className="flex items-center gap-2">
                                        <span className={`text-[10px] px-2 py-0.5 rounded border font-black uppercase ${BADGE_ESTADO[ej.estado] || ''}`}>
                                            {ej.estado}
                                        </span>
                                        <span className={`text-[10px] px-2 py-0.5 rounded border font-black uppercase ${BADGE_TIPO[ej.tipo] || ''}`}>
                                            {ej.tipo}
                                        </span>
                                    </div>
                                    <div>
                                        <p className="font-black text-slate-900">{ej.periodo}</p>
                                        <p className="text-xs text-slate-400">{ej.fecha} · {ej.usuario}</p>
                                    </div>
                                    {ej.asiento_comprobante && (
                                        <span className="font-mono text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-lg border border-slate-200">
                                            {ej.asiento_comprobante}
                                        </span>
                                    )}
                                </div>

                                <div className="flex items-center gap-6 sm:ml-auto">
                                    <div className="text-right">
                                        <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">IPC Aplicado</p>
                                        <p className="font-bold text-slate-700">{Number(ej.variacion_pct).toFixed(4)}%</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total CM</p>
                                        <p className="font-black text-violet-700 text-lg">{formatCLP(ej.total_cm_neto)}</p>
                                    </div>
                                    <i className={`fas fa-chevron-down text-slate-400 transition-transform ${expandido === ej.id ? 'rotate-180' : ''}`}></i>
                                </div>
                            </button>

                            {expandido === ej.id && (
                                <div className="border-t border-slate-100 px-6 py-5 bg-slate-50/50">
                                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                        {[
                                            { label: 'Activos',      val: ej.total_activos,      color: 'text-blue-700' },
                                            { label: 'Existencias',  val: ej.total_existencias,  color: 'text-emerald-700' },
                                            { label: 'Depreciación', val: ej.total_depreciacion, color: 'text-orange-700' },
                                            { label: 'Patrimonio',   val: ej.total_patrimonio,   color: 'text-violet-700' },
                                            { label: 'Pasivos',      val: ej.total_pasivos,      color: 'text-rose-700' },
                                        ].map(item => (
                                            <div key={item.label} className={`bg-white rounded-xl border border-slate-200 px-4 py-3 ${parseFloat(item.val) <= 0 ? 'opacity-40' : ''}`}>
                                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">{item.label}</p>
                                                <p className={`font-black text-sm mt-0.5 ${item.color}`}>{formatCLP(item.val)}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

export default TabHistorial;
