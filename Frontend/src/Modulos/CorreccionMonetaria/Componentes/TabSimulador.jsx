import React, { useState } from 'react';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api';

const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const formatCLP = (n) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(n ?? 0);

const COLORES_ROL = {
    ACTIVO_NO_MONETARIO:    'blue',
    DEPRECIACION_ACUMULADA: 'orange',
    INVENTARIO:             'green',
    PATRIMONIO_CAPITAL:     'purple',
    PASIVO_NO_MONETARIO:    'red',
};

const badgeColor = {
    blue:   'bg-blue-100 text-blue-700',
    orange: 'bg-orange-100 text-orange-700',
    green:  'bg-emerald-100 text-emerald-700',
    purple: 'bg-violet-100 text-violet-700',
    red:    'bg-rose-100 text-rose-700',
};

const TabSimulador = ({ config }) => {
    const hoy = new Date();
    const [mes, setMes]           = useState(hoy.getMonth() + 1);
    const [anio, setAnio]         = useState(hoy.getFullYear());
    const [resultado, setResultado] = useState(null);
    const [estado, setEstado]     = useState(null);
    const [loadingSim, setLoadingSim] = useState(false);
    const [ejecutando, setEjecutando] = useState(false);
    const anioActual = hoy.getFullYear();

    const simular = async () => {
        setLoadingSim(true);
        setResultado(null);
        try {
            const [resSim, resEst] = await Promise.all([
                api.get(`/correccion-monetaria/simular/${mes}/${anio}`),
                api.get(`/correccion-monetaria/estado/${mes}/${anio}`),
            ]);
            if (resSim.success) setResultado(resSim.data);
            if (resEst.success) setEstado(resEst.data);
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err?.message || 'No se pudo simular.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        } finally {
            setLoadingSim(false);
        }
    };

    const ejecutar = async () => {
        const confirmResult = await Swal.fire({
            title: '¿Ejecutar Corrección Monetaria?',
            html: `<p class="text-slate-600">Se generará un asiento contable de <strong>${formatCLP(resultado?.totales?.neto)}</strong> para <strong>${MESES[mes-1]} ${anio}</strong>.</p><p class="text-sm text-slate-500 mt-2">Esta acción no puede deshacerse directamente.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, ejecutar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'bg-violet-600 text-white font-bold py-2.5 px-6 rounded-lg mx-2 hover:bg-violet-700',
                cancelButton:  'bg-slate-200 text-slate-800 font-bold py-2.5 px-6 rounded-lg mx-2 hover:bg-slate-300',
            },
        });

        if (!confirmResult.isConfirmed) return;

        setEjecutando(true);
        try {
            const res = await api.post('/correccion-monetaria/ejecutar', { mes, anio });
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡CM Ejecutada!',
                    html: `<p>Comprobante: <strong>${res.data.asiento_comprobante}</strong></p><p class="text-sm text-slate-500 mt-1">Total ajustado: ${formatCLP(res.data.total_cm_neto)}</p>`,
                    confirmButtonText: 'Listo',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'bg-violet-600 text-white font-bold py-2 px-6 rounded-lg' },
                });
                await simular();
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error al ejecutar', text: err?.message || 'No se pudo ejecutar la CM.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        } finally {
            setEjecutando(false);
        }
    };

    return (
        <div className="space-y-6">
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col sm:flex-row items-start sm:items-end gap-5">
                <div>
                    <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Mes</label>
                    <select
                        value={mes}
                        onChange={e => { setMes(parseInt(e.target.value)); setResultado(null); setEstado(null); }}
                        className="border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-700 bg-slate-50 focus:ring-2 focus:ring-violet-500 outline-none cursor-pointer"
                    >
                        {MESES.map((m, i) => <option key={i + 1} value={i + 1}>{m}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Año</label>
                    <select
                        value={anio}
                        onChange={e => { setAnio(parseInt(e.target.value)); setResultado(null); setEstado(null); }}
                        className="border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-700 bg-slate-50 focus:ring-2 focus:ring-violet-500 outline-none cursor-pointer"
                    >
                        {[anioActual - 2, anioActual - 1, anioActual, anioActual + 1].map(y => (
                            <option key={y} value={y}>{y}</option>
                        ))}
                    </select>
                </div>
                <button
                    onClick={simular}
                    disabled={loadingSim}
                    className="bg-violet-600 hover:bg-violet-700 text-white font-black px-6 py-2.5 rounded-xl shadow-lg shadow-violet-200 transition-all disabled:opacity-60 flex items-center gap-2"
                >
                    {loadingSim
                        ? <><i className="fas fa-spinner fa-spin"></i> Calculando...</>
                        : <><i className="fas fa-calculator"></i> Simular</>
                    }
                </button>
            </div>

            {resultado && estado && (
                <>
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                        {[
                            { label: 'Activos',      val: resultado.totales?.activos,      color: 'blue' },
                            { label: 'Existencias',  val: resultado.totales?.existencias,  color: 'green' },
                            { label: 'Depreciación', val: resultado.totales?.depreciacion, color: 'orange' },
                            { label: 'Patrimonio',   val: resultado.totales?.patrimonio,   color: 'purple' },
                            { label: 'Pasivos',      val: resultado.totales?.pasivos,      color: 'red' },
                        ].map(item => (
                            <div key={item.label} className={`bg-white rounded-xl border border-slate-200 p-4 ${item.val <= 0 ? 'opacity-50' : ''}`}>
                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">{item.label}</p>
                                <p className={`text-lg font-black mt-1 ${badgeColor[item.color]?.split(' ')[1] || 'text-slate-800'}`}>
                                    {formatCLP(item.val)}
                                </p>
                            </div>
                        ))}
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="bg-slate-50 px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center gap-3">
                            <div>
                                <h3 className="font-black text-slate-800">
                                    Vista Previa del Asiento
                                    <span className="ml-2 text-[11px] bg-violet-100 text-violet-700 px-2 py-0.5 rounded font-black border border-violet-200">
                                        IPC {resultado.variacion_pct?.toFixed(4)}% ({resultado.tipo})
                                    </span>
                                </h3>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    {MESES[mes - 1]} {anio} · {resultado.modalidad} · {resultado.proveedor_ipc}
                                </p>
                            </div>
                            <div className="sm:ml-auto text-right">
                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total CM Neto</p>
                                <p className="text-2xl font-black text-violet-700">{formatCLP(resultado.totales?.neto)}</p>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                    <tr>
                                        <th className="px-5 py-3 text-left">Cuenta</th>
                                        <th className="px-5 py-3 text-right text-emerald-600">Debe</th>
                                        <th className="px-5 py-3 text-right text-rose-600">Haber</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {resultado.asiento_preview?.map((linea, i) => (
                                        <tr key={i} className="hover:bg-slate-50">
                                            <td className="px-5 py-3 font-mono text-slate-600 text-xs">{linea.cuenta_contable}</td>
                                            <td className="px-5 py-3 text-right font-mono font-bold">
                                                {linea.debe > 0 ? <span className="text-emerald-700">{formatCLP(linea.debe)}</span> : <span className="text-slate-300">—</span>}
                                            </td>
                                            <td className="px-5 py-3 text-right font-mono font-bold">
                                                {linea.haber > 0 ? <span className="text-rose-600">{formatCLP(linea.haber)}</span> : <span className="text-slate-300">—</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t-2 border-slate-200 bg-slate-50">
                                    <tr>
                                        <td className="px-5 py-3 font-black text-slate-700 text-xs uppercase tracking-widest">Total</td>
                                        <td className="px-5 py-3 text-right font-black text-emerald-700">{formatCLP(resultado.totales?.neto)}</td>
                                        <td className="px-5 py-3 text-right font-black text-rose-600">{formatCLP(resultado.totales?.neto)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div className="px-6 py-5 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                            {estado.ya_ejecutada && (
                                <div className="flex items-center gap-2 bg-emerald-50 text-emerald-700 border border-emerald-200 px-4 py-2.5 rounded-xl">
                                    <i className="fas fa-check-circle"></i>
                                    <span className="font-bold text-sm">Ya ejecutada en este período</span>
                                </div>
                            )}
                            {estado.bloqueado_por_modalidad && !estado.ya_ejecutada && (
                                <div className="flex items-center gap-2 bg-amber-50 text-amber-700 border border-amber-200 px-4 py-2.5 rounded-xl">
                                    <i className="fas fa-lock text-sm"></i>
                                    <span className="font-bold text-sm">
                                        Modalidad anual: ejecución habilitada solo en {config?.nombre_mes_cierre}
                                    </span>
                                </div>
                            )}
                            {!estado.ya_ejecutada && !estado.bloqueado_por_modalidad && (
                                <button
                                    onClick={ejecutar}
                                    disabled={ejecutando || !estado.puede_ejecutar}
                                    className="ml-auto bg-violet-600 hover:bg-violet-700 disabled:bg-slate-300 text-white font-black px-8 py-3 rounded-xl shadow-lg shadow-violet-200 transition-all flex items-center gap-2"
                                >
                                    {ejecutando
                                        ? <><i className="fas fa-spinner fa-spin"></i> Ejecutando...</>
                                        : <><i className="fas fa-play-circle"></i> Ejecutar y Contabilizar</>
                                    }
                                </button>
                            )}
                        </div>
                    </div>

                    {resultado.lineas?.length > 0 && (
                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                            <div className="bg-slate-50 px-6 py-4 border-b border-slate-200">
                                <h3 className="font-black text-slate-800">Detalle por Cuenta</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{resultado.lineas.length} cuentas incluidas en el cálculo.</p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                        <tr>
                                            <th className="px-5 py-3 text-left">Cuenta</th>
                                            <th className="px-5 py-3 text-left">Rol</th>
                                            <th className="px-5 py-3 text-right">Saldo Ajustable</th>
                                            <th className="px-5 py-3 text-right">Variación %</th>
                                            <th className="px-5 py-3 text-right">Ajuste CM</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {resultado.lineas.map((linea, i) => (
                                            <tr key={i} className="hover:bg-slate-50">
                                                <td className="px-5 py-3">
                                                    <span className="font-mono text-xs text-slate-500 mr-2">{linea.cuenta_codigo}</span>
                                                    <span className="font-bold text-slate-800">{linea.nombre_cuenta}</span>
                                                </td>
                                                <td className="px-5 py-3">
                                                    <span className={`text-[10px] px-2 py-0.5 rounded font-black uppercase ${badgeColor[COLORES_ROL[linea.rol_cm]] || 'bg-slate-100 text-slate-600'}`}>
                                                        {linea.label_rol}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-3 text-right font-mono text-slate-600">{formatCLP(linea.saldo_ajustable)}</td>
                                                <td className="px-5 py-3 text-right font-mono text-slate-500 text-xs">{Number(linea.variacion_usada).toFixed(4)}%</td>
                                                <td className="px-5 py-3 text-right font-black text-violet-700">{formatCLP(linea.ajuste)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </>
            )}

            {!resultado && !loadingSim && (
                <div className="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
                    <i className="fas fa-calculator text-5xl text-violet-200"></i>
                    <p className="font-bold">Selecciona un período y presiona <span className="text-violet-600">Simular</span> para ver el cálculo.</p>
                </div>
            )}
        </div>
    );
};

export default TabSimulador;
