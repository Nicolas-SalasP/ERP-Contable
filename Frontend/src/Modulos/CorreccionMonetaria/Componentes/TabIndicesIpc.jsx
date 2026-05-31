import React, { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api';

const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const TabIndicesIpc = ({ anioInicial }) => {
    const [anio, setAnio]         = useState(anioInicial);
    const [indices, setIndices]   = useState([]);
    const [loading, setLoading]   = useState(true);
    const [guardando, setGuardando] = useState(null);
    const [editando, setEditando] = useState(null);
    const [formValues, setFormValues] = useState({ variacion: '', observacion: '' });
    const anioActual = new Date().getFullYear();

    useEffect(() => {
        cargar();
    }, [anio]);

    const cargar = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/correccion-monetaria/indices/${anio}`);
            if (res.success) setIndices(res.data);
        } catch (_) {
        } finally {
            setLoading(false);
        }
    };

    const iniciarEdicion = (indice) => {
        setEditando(indice.mes);
        setFormValues({
            variacion:    indice.variacion_mensual !== null ? String(indice.variacion_mensual) : '',
            observacion:  indice.observacion || '',
        });
    };

    const cancelarEdicion = () => {
        setEditando(null);
        setFormValues({ variacion: '', observacion: '' });
    };

    const guardar = async (mes) => {
        const variacion = parseFloat(formValues.variacion);
        if (isNaN(variacion)) {
            Swal.fire({ icon: 'error', title: 'Valor inválido', text: 'Ingrese un número válido para la variación.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
            return;
        }

        setGuardando(mes);
        try {
            const res = await api.post('/correccion-monetaria/indices', {
                anio, mes, variacion,
                observacion: formValues.observacion || null,
            });
            if (res.success) {
                cancelarEdicion();
                await cargar();
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'No se pudo guardar el índice.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        } finally {
            setGuardando(null);
        }
    };

    const mesesCargados = indices.filter(i => i.cargado).length;
    const acumuladoFinal = indices.findLast?.(i => i.cargado)?.variacion_acumulada ?? null;

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 className="text-xl font-black text-slate-800">Índices IPC Mensuales</h2>
                    <p className="text-sm text-slate-500 mt-0.5">
                        Ingrese la variación mensual publicada por el INE.
                        {mesesCargados > 0 && <span className="ml-2 font-bold text-violet-600">{mesesCargados}/12 meses cargados</span>}
                    </p>
                </div>
                <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-2 shadow-sm">
                    <button onClick={() => setAnio(a => a - 1)} className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-500 font-bold">
                        <i className="fas fa-chevron-left text-xs"></i>
                    </button>
                    <span className="font-black text-slate-800 w-12 text-center">{anio}</span>
                    <button
                        onClick={() => setAnio(a => a + 1)}
                        disabled={anio >= anioActual + 1}
                        className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-500 font-bold disabled:opacity-40"
                    >
                        <i className="fas fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>

            {acumuladoFinal !== null && (
                <div className="bg-violet-50 border border-violet-200 rounded-xl px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    <i className="fas fa-percentage text-violet-500 text-xl"></i>
                    <div>
                        <p className="text-xs font-black text-violet-600 uppercase tracking-widest">IPC Acumulado {anio}</p>
                        <p className="text-2xl font-black text-violet-800">{Number(acumuladoFinal).toFixed(4)}%</p>
                    </div>
                    <p className="text-xs text-violet-500 sm:ml-auto max-w-xs">
                        Factor acumulado enero → {MESES[indices.findLastIndex?.(i => i.cargado)]}. Se usa para corrección monetaria anual.
                    </p>
                </div>
            )}

            {loading ? (
                <div className="flex items-center justify-center py-20 text-slate-400">
                    <i className="fas fa-spinner fa-spin text-2xl mr-3"></i> Cargando índices...
                </div>
            ) : (
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-900 text-white">
                            <tr>
                                <th className="px-5 py-3.5 text-left text-[10px] font-black uppercase tracking-widest first:rounded-tl-xl">Mes</th>
                                <th className="px-5 py-3.5 text-right text-[10px] font-black uppercase tracking-widest">Variación Mensual</th>
                                <th className="px-5 py-3.5 text-right text-[10px] font-black uppercase tracking-widest">Factor</th>
                                <th className="px-5 py-3.5 text-right text-[10px] font-black uppercase tracking-widest">Acumulado Anual</th>
                                <th className="px-5 py-3.5 text-right text-[10px] font-black uppercase tracking-widest last:rounded-tr-xl">Acción</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {indices.map(indice => (
                                <tr key={indice.mes} className={`transition-colors ${!indice.cargado ? 'bg-amber-50/30' : 'hover:bg-slate-50'}`}>
                                    <td className="px-5 py-3.5">
                                        <div className="flex items-center gap-2.5">
                                            <span className={`w-2 h-2 rounded-full flex-shrink-0 ${indice.cargado ? 'bg-emerald-400' : 'bg-amber-400'}`}></span>
                                            <span className="font-bold text-slate-800">{MESES[indice.mes - 1]}</span>
                                            {indice.fuente === 'api_ine' && (
                                                <span className="text-[9px] bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded font-black uppercase">INE</span>
                                            )}
                                        </div>
                                    </td>

                                    {editando === indice.mes ? (
                                        <>
                                            <td className="px-5 py-3 text-right" colSpan={3}>
                                                <div className="flex items-center justify-end gap-3 flex-wrap">
                                                    <div className="flex items-center gap-1.5">
                                                        <input
                                                            type="number"
                                                            step="0.0001"
                                                            value={formValues.variacion}
                                                            onChange={e => setFormValues(v => ({ ...v, variacion: e.target.value }))}
                                                            placeholder="0.4200"
                                                            className="w-28 border border-violet-300 rounded-lg px-3 py-1.5 text-right font-mono text-sm focus:ring-2 focus:ring-violet-500 outline-none"
                                                            autoFocus
                                                        />
                                                        <span className="text-slate-500 font-bold text-sm">%</span>
                                                    </div>
                                                    <input
                                                        type="text"
                                                        value={formValues.observacion}
                                                        onChange={e => setFormValues(v => ({ ...v, observacion: e.target.value }))}
                                                        placeholder="Observación (opcional)"
                                                        className="w-44 border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none"
                                                    />
                                                </div>
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        onClick={() => guardar(indice.mes)}
                                                        disabled={guardando === indice.mes}
                                                        className="bg-violet-600 hover:bg-violet-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors disabled:opacity-60"
                                                    >
                                                        {guardando === indice.mes ? <i className="fas fa-spinner fa-spin"></i> : 'Guardar'}
                                                    </button>
                                                    <button onClick={cancelarEdicion} className="text-slate-400 hover:text-slate-700 font-bold text-xs px-2 py-1.5 rounded-lg">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </td>
                                        </>
                                    ) : (
                                        <>
                                            <td className="px-5 py-3.5 text-right font-mono font-bold">
                                                {indice.cargado ? (
                                                    <span className={`${parseFloat(indice.variacion_mensual) >= 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                                                        {parseFloat(indice.variacion_mensual) >= 0 ? '+' : ''}{Number(indice.variacion_mensual).toFixed(4)}%
                                                    </span>
                                                ) : (
                                                    <span className="text-amber-500 font-bold text-xs">Sin dato</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-mono text-slate-500 text-xs">
                                                {indice.cargado ? Number(indice.factor_multiplicador).toFixed(6) : '—'}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-mono">
                                                {indice.cargado ? (
                                                    <span className="font-bold text-slate-700">
                                                        {Number(indice.variacion_acumulada).toFixed(4)}%
                                                    </span>
                                                ) : '—'}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <button
                                                    onClick={() => iniciarEdicion(indice)}
                                                    className="text-violet-600 hover:text-violet-800 hover:bg-violet-50 font-bold text-xs px-3 py-1.5 rounded-lg transition-colors border border-transparent hover:border-violet-200"
                                                >
                                                    <i className={`fas ${indice.cargado ? 'fa-pencil-alt' : 'fa-plus'} mr-1 text-[10px]`}></i>
                                                    {indice.cargado ? 'Editar' : 'Ingresar'}
                                                </button>
                                            </td>
                                        </>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <p className="text-xs text-slate-400 flex items-center gap-1.5">
                <i className="fas fa-info-circle"></i>
                El INE publica el IPC mensual entre el 8 y 10 del mes siguiente.
                La integración automática con la API del INE está preparada para activarse en una versión futura.
            </p>
        </div>
    );
};

export default TabIndicesIpc;
