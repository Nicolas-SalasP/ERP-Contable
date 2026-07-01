import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';

const formatCLP = (amount) =>
    new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount ?? 0);

const TablaAmortizacion = ({ activoId, activoNombre, onCerrar }) => {
    const [datos, setDatos] = useState(null);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!activoId) return;
        setCargando(true);
        setError(null);
        setDatos(null);
        api.get(`/activos/${activoId}/amortizacion`)
            .then((res) => {
                if (res.success) setDatos(res.data);
                else setError('No se pudo cargar la tabla de amortización.');
            })
            .catch(() => setError('Error al conectar con el servidor.'))
            .finally(() => setCargando(false));
    }, [activoId]);

    if (!activoId) return null;

    return (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-5xl my-8 overflow-hidden border border-slate-200 dark:border-slate-700 animate-fade-in">
                {/* Header */}
                <div className="bg-gradient-to-br from-indigo-600 to-violet-700 px-6 py-4 flex justify-between items-center">
                    <div>
                        <h3 className="font-black text-xl text-white">Tabla de Amortización</h3>
                        <p className="text-indigo-100 text-xs mt-0.5">{activoNombre}</p>
                    </div>
                    <button
                        onClick={onCerrar}
                        className="text-white/80 hover:text-white text-2xl leading-none transition-colors"
                        aria-label="Cerrar"
                    >
                        ×
                    </button>
                </div>

                <div className="p-6">
                    {cargando && (
                        <div className="flex flex-col items-center justify-center py-16 gap-3">
                            <div className="w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                            <p className="text-slate-500 text-sm font-medium">Cargando tabla de amortización...</p>
                        </div>
                    )}

                    {error && !cargando && (
                        <div className="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 text-sm font-medium">
                            <i className="fas fa-exclamation-circle mr-2"></i>{error}
                        </div>
                    )}

                    {datos && !cargando && (
                        <>
                            {/* Tarjetas resumen */}
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                <div className="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Valor Adquisición</p>
                                    <p className="text-lg font-black text-slate-800 dark:text-slate-200">{formatCLP(datos.resumen.valor_adquisicion)}</p>
                                </div>
                                <div className="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Valor Residual</p>
                                    <p className="text-lg font-black text-slate-800 dark:text-slate-200">{formatCLP(datos.resumen.valor_residual)}</p>
                                </div>
                                <div className="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Dep. Acumulada</p>
                                    <div className="flex items-center gap-2">
                                        <p className="text-lg font-black text-amber-600">{formatCLP(datos.resumen.depreciacion_acumulada_real)}</p>
                                        <span className="text-[10px] font-black bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">
                                            {datos.resumen.porcentaje_depreciado}%
                                        </span>
                                    </div>
                                </div>
                                <div className="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-4 border border-emerald-200 dark:border-emerald-800">
                                    <p className="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Valor Libro Actual</p>
                                    <p className="text-lg font-black text-emerald-700 dark:text-emerald-400">{formatCLP(datos.resumen.valor_libro_actual)}</p>
                                </div>
                            </div>

                            {/* Tabla */}
                            <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-left border-collapse">
                                        <thead className="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-xs uppercase text-slate-500 dark:text-slate-400">
                                            <tr>
                                                <th className="px-4 py-3 font-bold">N° Mes</th>
                                                <th className="px-4 py-3 font-bold">Período</th>
                                                <th className="px-4 py-3 font-bold text-right">Cuota</th>
                                                <th className="px-4 py-3 font-bold text-right">Dep. Acumulada</th>
                                                <th className="px-4 py-3 font-bold text-right">Valor Libro</th>
                                                <th className="px-4 py-3 font-bold text-center">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                                            {datos.filas.map((fila) => (
                                                <tr
                                                    key={fila.numero_mes}
                                                    className={`transition-colors ${
                                                        fila.ya_ejecutado
                                                            ? 'bg-green-50 dark:bg-green-900/10'
                                                            : 'hover:bg-slate-50 dark:hover:bg-slate-700'
                                                    }`}
                                                >
                                                    <td className="px-4 py-2.5 font-bold text-slate-600 dark:text-slate-400">{fila.numero_mes}</td>
                                                    <td className="px-4 py-2.5 font-mono text-slate-700 dark:text-slate-300">{fila.periodo}</td>
                                                    <td className="px-4 py-2.5 text-right font-semibold text-slate-700 dark:text-slate-300">{formatCLP(fila.cuota)}</td>
                                                    <td className="px-4 py-2.5 text-right text-slate-600 dark:text-slate-400">{formatCLP(fila.depreciacion_acumulada)}</td>
                                                    <td className="px-4 py-2.5 text-right font-semibold text-emerald-700 dark:text-emerald-400">{formatCLP(fila.valor_libro)}</td>
                                                    <td className="px-4 py-2.5 text-center">
                                                        {fila.ya_ejecutado ? (
                                                            <span className="inline-flex items-center gap-1 bg-emerald-100 text-emerald-800 text-[10px] font-black px-2 py-1 rounded-full">
                                                                <i className="fas fa-check-circle"></i> Ejecutado
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 bg-slate-100 text-slate-500 text-[10px] font-black px-2 py-1 rounded-full dark:bg-slate-700 dark:text-slate-400">
                                                                <i className="fas fa-clock"></i> Pendiente
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};

export default TablaAmortizacion;
