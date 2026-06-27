import React, { useState, useEffect, useCallback } from 'react';
import { api } from '../../../Configuracion/api';
import { usePermisos } from '../../../Contextos/Permisos';

const MESES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

function badgePeriodo(anio, mes, cerrados) {
    return cerrados.some(p => p.anio === anio && p.mes === mes);
}

export default function CierrePeriodo() {
    const { tienePermiso } = usePermisos();
    const puedeGestionar = tienePermiso('contabilidad.cerrar_periodo');

    const anioActual = new Date().getFullYear();
    const [anioSeleccionado, setAnioSeleccionado] = useState(anioActual);
    const [cerrados, setCerrados] = useState([]);
    const [cargando, setCargando] = useState(false);

    // Modal cierre/reapertura
    const [modal, setModal] = useState(null); // { tipo: 'cerrar'|'reabrir', mes, anio }
    const [motivo, setMotivo] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');

    const cargar = useCallback(async () => {
        setCargando(true);
        try {
            const res = await api.get('/contabilidad/periodos', { params: { anio: anioSeleccionado } });
            setCerrados(res.data.data ?? []);
        } catch {
            // silencioso — la tabla queda vacía
        } finally {
            setCargando(false);
        }
    }, [anioSeleccionado]);

    useEffect(() => { cargar(); }, [cargar]);

    const abrirModal = (tipo, mes) => {
        setModal({ tipo, mes, anio: anioSeleccionado });
        setMotivo('');
        setError('');
    };

    const cerrarModal = () => { setModal(null); setMotivo(''); setError(''); };

    const confirmar = async () => {
        if (!modal) return;
        if (modal.tipo === 'reabrir' && !motivo.trim()) {
            setError('El motivo es obligatorio para reabrir un período.');
            return;
        }
        setProcesando(true);
        setError('');
        try {
            const endpoint = modal.tipo === 'cerrar'
                ? '/contabilidad/periodos/cerrar'
                : '/contabilidad/periodos/reabrir';
            await api.post(endpoint, { anio: modal.anio, mes: modal.mes, motivo: motivo.trim() || undefined });
            cerrarModal();
            cargar();
        } catch (e) {
            setError(e.response?.data?.message ?? 'Error al procesar la solicitud.');
        } finally {
            setProcesando(false);
        }
    };

    const periodoData = cerrados.find(p => p.mes === modal?.mes && p.anio === modal?.anio);

    return (
        <div className="p-6 max-w-4xl mx-auto">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-slate-800 dark:text-slate-100">
                    Cierre de Períodos Contables
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Un período cerrado impide crear, modificar o eliminar asientos contables en ese mes.
                    Solo administradores pueden reabrirlos.
                </p>
            </div>

            {/* Selector de año */}
            <div className="flex items-center gap-3 mb-6">
                <button
                    onClick={() => setAnioSeleccionado(a => a - 1)}
                    className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    <i className="fas fa-chevron-left text-slate-500" />
                </button>
                <span className="text-xl font-bold text-slate-700 dark:text-slate-200 min-w-[5rem] text-center">
                    {anioSeleccionado}
                </span>
                <button
                    onClick={() => setAnioSeleccionado(a => a + 1)}
                    className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    <i className="fas fa-chevron-right text-slate-500" />
                </button>
            </div>

            {/* Grilla de meses */}
            {cargando ? (
                <div className="text-center py-12 text-slate-400">
                    <i className="fas fa-spinner fa-spin text-2xl" />
                </div>
            ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    {MESES.map((nombre, idx) => {
                        const mes = idx + 1;
                        const esCerrado = badgePeriodo(anioSeleccionado, mes, cerrados);
                        const periodoData = cerrados.find(p => p.mes === mes);
                        const esFuturo = anioSeleccionado > anioActual
                            || (anioSeleccionado === anioActual && mes > new Date().getMonth() + 1);

                        return (
                            <div
                                key={mes}
                                className={`rounded-xl border p-4 flex flex-col gap-2 transition-all ${
                                    esCerrado
                                        ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/30'
                                        : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800'
                                } ${esFuturo ? 'opacity-40' : ''}`}
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold text-sm text-slate-700 dark:text-slate-200">
                                        {nombre}
                                    </span>
                                    {esCerrado ? (
                                        <span className="inline-flex items-center gap-1 text-xs font-bold text-red-600 dark:text-red-400">
                                            <i className="fas fa-lock" /> Cerrado
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                            <i className="fas fa-lock-open" /> Abierto
                                        </span>
                                    )}
                                </div>

                                {esCerrado && periodoData && (
                                    <p className="text-xs text-slate-400 dark:text-slate-500 truncate">
                                        Por {periodoData.cerrado_por?.name ?? '—'}
                                    </p>
                                )}

                                {puedeGestionar && !esFuturo && (
                                    <button
                                        onClick={() => abrirModal(esCerrado ? 'reabrir' : 'cerrar', mes)}
                                        className={`mt-1 w-full text-xs font-semibold py-1.5 rounded-lg transition-colors ${
                                            esCerrado
                                                ? 'bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-800/40'
                                                : 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-800/40'
                                        }`}
                                    >
                                        {esCerrado ? 'Reabrir período' : 'Cerrar período'}
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Leyenda */}
            <div className="mt-6 p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 rounded-xl text-xs text-amber-800 dark:text-amber-400">
                <strong>Efectos del cierre:</strong> ningún asiento contable puede crearse, modificarse ni eliminarse
                en un período cerrado. La reapertura requiere jerarquía de Administrador y motivo obligatorio.
            </div>

            {/* Historial de períodos cerrados */}
            {cerrados.length > 0 && (
                <div className="mt-8">
                    <h2 className="text-sm font-bold text-slate-600 dark:text-slate-300 mb-3 uppercase tracking-wide">
                        Historial de cierres — {anioSeleccionado}
                    </h2>
                    <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 text-left">Mes</th>
                                    <th className="px-4 py-3 text-left">Estado</th>
                                    <th className="px-4 py-3 text-left">Cerrado por</th>
                                    <th className="px-4 py-3 text-left">Fecha cierre</th>
                                    <th className="px-4 py-3 text-left">Motivo cierre</th>
                                    <th className="px-4 py-3 text-left">Reabierto por</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                {cerrados.map(p => (
                                    <tr key={`${p.anio}-${p.mes}`} className="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                                            {MESES[p.mes - 1]}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full ${
                                                p.estado === 'CERRADO'
                                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            }`}>
                                                <i className={`fas ${p.estado === 'CERRADO' ? 'fa-lock' : 'fa-lock-open'}`} />
                                                {p.estado}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-300">
                                            {p.cerrado_por?.name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-500 dark:text-slate-400">
                                            {p.cerrado_at ? new Date(p.cerrado_at).toLocaleDateString('es-CL') : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                            {p.motivo ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-500 dark:text-slate-400">
                                            {p.reabierto_por?.name ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Modal confirmación */}
            {modal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md p-6">
                        <div className="flex items-start gap-3 mb-4">
                            <div className={`rounded-full p-2 ${modal.tipo === 'cerrar' ? 'bg-red-100 dark:bg-red-900/40' : 'bg-amber-100 dark:bg-amber-900/40'}`}>
                                <i className={`fas ${modal.tipo === 'cerrar' ? 'fa-lock text-red-600 dark:text-red-400' : 'fa-lock-open text-amber-600 dark:text-amber-400'} text-lg`} />
                            </div>
                            <div>
                                <h3 className="font-bold text-slate-800 dark:text-slate-100 text-lg">
                                    {modal.tipo === 'cerrar' ? 'Cerrar período' : 'Reabrir período'}
                                </h3>
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    {MESES[modal.mes - 1]} {modal.anio}
                                </p>
                            </div>
                        </div>

                        {modal.tipo === 'cerrar' && (
                            <div className="mb-4 p-3 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-400">
                                <strong>Advertencia:</strong> ningún asiento podrá crearse, modificarse ni eliminarse
                                en este período hasta que sea reabierto por un administrador.
                            </div>
                        )}

                        {periodoData && modal.tipo === 'reabrir' && (
                            <div className="mb-4 p-3 bg-slate-50 dark:bg-slate-800 rounded-lg text-xs text-slate-600 dark:text-slate-400">
                                Cerrado por <strong>{periodoData.cerrado_por?.name ?? '—'}</strong>
                                {periodoData.cerrado_at && ` el ${new Date(periodoData.cerrado_at).toLocaleDateString('es-CL')}`}
                                {periodoData.motivo && `. Motivo: "${periodoData.motivo}"`}
                            </div>
                        )}

                        <div className="mb-4">
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Motivo {modal.tipo === 'reabrir' ? <span className="text-red-500">*</span> : '(opcional)'}
                            </label>
                            <textarea
                                value={motivo}
                                onChange={e => setMotivo(e.target.value)}
                                maxLength={500}
                                rows={3}
                                className="w-full border border-slate-300 dark:border-slate-600 rounded-lg p-2.5 text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 resize-none outline-none focus:ring-2 focus:ring-blue-400/30 focus:border-blue-500"
                                placeholder={modal.tipo === 'reabrir' ? 'Indique el motivo de reapertura...' : 'Indique el motivo del cierre (opcional)...'}
                            />
                            {error && (
                                <p className="text-xs text-red-600 dark:text-red-400 mt-1">{error}</p>
                            )}
                        </div>

                        <div className="flex gap-3 justify-end">
                            <button
                                onClick={cerrarModal}
                                disabled={procesando}
                                className="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={confirmar}
                                disabled={procesando}
                                className={`px-4 py-2 text-sm font-bold text-white rounded-lg transition-colors disabled:opacity-50 ${
                                    modal.tipo === 'cerrar'
                                        ? 'bg-red-600 hover:bg-red-700'
                                        : 'bg-amber-500 hover:bg-amber-600'
                                }`}
                            >
                                {procesando
                                    ? <><i className="fas fa-spinner fa-spin mr-2" />Procesando...</>
                                    : modal.tipo === 'cerrar' ? 'Cerrar período' : 'Reabrir período'
                                }
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
