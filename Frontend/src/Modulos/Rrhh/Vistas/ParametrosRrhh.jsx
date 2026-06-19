import React, { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import { usePermisos } from '../../../Contextos/Permisos';
import rrhhApi from '../Servicios/rrhhApi';
import { formatFecha, formatNumero, formatPesos, MESES, nombreMes } from '../Utilidades/formato';
import PanelModal from '../Componentes/PanelModal';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';

const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';
const anioActual = new Date().getFullYear();
const ANIOS = Array.from({ length: 6 }, (_, i) => anioActual - i);

const TABS = [
    { id: 'previsionales', label: 'Previsionales', icon: 'fas fa-percent' },
    { id: 'indicadores', label: 'Indicadores UF/UTM', icon: 'fas fa-chart-line' },
    { id: 'impuesto', label: 'Tabla Impuesto Único', icon: 'fas fa-table' },
];

const ParametrosRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeEditar = tienePermiso('rrhh.parametros.editar');

    const [tab, setTab] = useState('previsionales');
    const [parametros, setParametros] = useState([]);
    const [indicadores, setIndicadores] = useState([]);
    const [tabla, setTabla] = useState([]);
    const [anioTabla, setAnioTabla] = useState(anioActual);
    const [cargando, setCargando] = useState(true);

    const [modalInd, setModalInd] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [formInd, setFormInd] = useState({ anio: anioActual, mes: new Date().getMonth() + 1, uf_valor: '', utm_valor: '', uta_valor: '', fuente: '' });

    const cargar = useCallback(async () => {
        setCargando(true);
        try {
            const [p, i, t] = await Promise.all([
                rrhhApi.parametros.listar(),
                rrhhApi.indicadores.listar(),
                rrhhApi.tablaImpuesto.listar(anioTabla),
            ]);
            setParametros(p?.data ?? []);
            setIndicadores(i?.data ?? []);
            setTabla(t?.data ?? []);
        } catch (_) { /* toast */ } finally {
            setCargando(false);
        }
    }, [anioTabla]);

    useEffect(() => { cargar(); }, [cargar]);

    const setFi = (k, v) => setFormInd((f) => ({ ...f, [k]: v }));

    const guardarIndicador = async (e) => {
        e.preventDefault();
        setGuardando(true);
        try {
            const payload = Object.fromEntries(Object.entries(formInd).filter(([, v]) => v !== '' && v !== null));
            await rrhhApi.indicadores.crear(payload);
            await Swal.fire({ icon: 'success', title: 'Indicador guardado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            setModalInd(false);
            cargar();
        } catch (_) { /* toast */ } finally {
            setGuardando(false);
        }
    };

    const vigente = parametros[0];

    return (
        <div className="max-w-6xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                        <i className="fas fa-scale-balanced text-emerald-600" />
                        Parámetros Previsionales
                    </h1>
                    <AyudaModulo moduloId="parametrosRrhh" size={28} />
                </div>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Tasas, topes e indicadores legales que alimentan el motor de liquidación. Cero números mágicos en el código.
                </p>
            </header>

            <div className="flex gap-1 mb-5 border-b border-slate-200 dark:border-slate-700">
                {TABS.map((t) => (
                    <button key={t.id} onClick={() => setTab(t.id)}
                        className={`px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors flex items-center gap-2 ${tab === t.id ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`}>
                        <i className={t.icon} /> {t.label}
                    </button>
                ))}
            </div>

            <EstadoCarga cargando={cargando} mensajeCargando="Cargando parámetros..." color="emerald" tamano="compacto">
                {tab === 'previsionales' && (
                    !vigente ? (
                        <div className="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-800 text-sm">
                            <i className="fas fa-triangle-exclamation mr-2" />
                            No hay parámetros previsionales cargados. Ejecuta el seeder <code className="bg-amber-100 px-1 rounded">RrhhParametrosLegalesSeeder</code> o créalos vía API.
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="font-bold text-slate-900 dark:text-slate-100">Vigente desde {formatFecha(vigente.vigente_desde)}</h3>
                                <span className="text-xs text-slate-400">{vigente.fuente}</span>
                            </div>
                            <div className="grid sm:grid-cols-3 gap-3 text-sm">
                                {[
                                    ['Cotización AFP', `${formatNumero(vigente.afp_cotizacion_pct)}%`],
                                    ['SIS (empleador)', `${formatNumero(vigente.afp_sis_pct)}%`],
                                    ['Salud FONASA', `${formatNumero(vigente.salud_fonasa_pct)}%`],
                                    ['Tope imponible', `${formatNumero(vigente.tope_imponible_uf)} UF`],
                                    ['AFC indefinido (trab.)', `${formatNumero(vigente.afc_indefinido_trabajador_pct)}%`],
                                    ['AFC indefinido (empl.)', `${formatNumero(vigente.afc_indefinido_empleador_pct)}%`],
                                    ['AFC plazo fijo (empl.)', `${formatNumero(vigente.afc_plazo_fijo_empleador_pct)}%`],
                                    ['Tope AFC', `${formatNumero(vigente.afc_tope_imponible_uf)} UF`],
                                    ['Ingreso Mínimo (IMM)', formatPesos(vigente.imm)],
                                    ['Factor gratificación', `${formatNumero(vigente.gratificacion_tope_mensual_factor)}`],
                                    ['Cotiz. adicional empl. (Ley 21.735)', `${formatNumero(vigente.cotizacion_adicional_empleador_pct)}%`],
                                    ['Mutual básica', `${formatNumero(vigente.mutual_cotizacion_basica_pct)}%`],
                                ].map(([k, v]) => (
                                    <div key={k} className="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                        <p className="text-xs text-slate-500 dark:text-slate-400">{k}</p>
                                        <p className="font-bold text-slate-900 dark:text-slate-100">{v}</p>
                                    </div>
                                ))}
                            </div>
                            {vigente.afp_comisiones_json && (
                                <div className="mt-4">
                                    <p className="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">Comisiones AFP</p>
                                    <div className="flex flex-wrap gap-2">
                                        {Object.entries(vigente.afp_comisiones_json).map(([afp, com]) => (
                                            <span key={afp} className="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">
                                                {afp}: {formatNumero(com)}%
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )
                )}

                {tab === 'indicadores' && (
                    <div>
                        {puedeEditar && (
                            <div className="flex justify-end mb-3">
                                <button onClick={() => setModalInd(true)}
                                    className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm">
                                    <i className="fas fa-plus" /> Registrar indicador
                                </button>
                            </div>
                        )}
                        <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                            <div className="overflow-x-auto custom-scrollbar">
                            <table className="min-w-full text-sm">
                                <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold">Período</th>
                                        <th className="px-4 py-3 text-right font-semibold">UF</th>
                                        <th className="px-4 py-3 text-right font-semibold">UTM</th>
                                        <th className="px-4 py-3 text-right font-semibold">UTA</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                    {indicadores.length === 0 && (
                                        <EstadoVacio mensaje="Sin indicadores cargados." />
                                    )}
                                    {indicadores.map((ind) => (
                                        <tr key={ind.id} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                            <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{nombreMes(ind.mes)} {ind.anio}</td>
                                            <td className="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{formatPesos(ind.uf_valor)}</td>
                                            <td className="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{formatPesos(ind.utm_valor)}</td>
                                            <td className="px-4 py-3 text-right text-slate-600 dark:text-slate-400">{formatPesos(ind.uta_valor)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                )}

                {tab === 'impuesto' && (
                    <div>
                        <div className="flex justify-end mb-3">
                            <select value={anioTabla} onChange={(e) => setAnioTabla(Number(e.target.value))} className={`${inputCls} max-w-[100px] sm:max-w-[140px]`}>
                                {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                            </select>
                        </div>
                        <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                            <div className="overflow-x-auto custom-scrollbar">
                            <table className="min-w-full text-sm">
                                <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold">Tramo</th>
                                        <th className="px-4 py-3 text-right font-semibold">Desde (UTM)</th>
                                        <th className="px-4 py-3 text-right font-semibold">Hasta (UTM)</th>
                                        <th className="px-4 py-3 text-right font-semibold">Tasa</th>
                                        <th className="px-4 py-3 text-right font-semibold">Factor rebaja (UTM)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                    {tabla.length === 0 && (
                                        <EstadoVacio mensaje={`Sin tabla de impuesto para ${anioTabla}.`} />
                                    )}
                                    {tabla.map((tr) => (
                                        <tr key={tr.id} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                            <td className="px-4 py-3 text-slate-700 dark:text-slate-300">{tr.orden}</td>
                                            <td className="px-4 py-3 text-right text-slate-600 dark:text-slate-400">{formatNumero(tr.desde_utm)}</td>
                                            <td className="px-4 py-3 text-right text-slate-600 dark:text-slate-400">{tr.hasta_utm ? formatNumero(tr.hasta_utm) : '∞'}</td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">{formatNumero(tr.tasa * 100, 1)}%</td>
                                            <td className="px-4 py-3 text-right text-slate-600 dark:text-slate-400">{formatNumero(tr.factor_deduccion_utm)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                )}
            </EstadoCarga>

            <PanelModal abierto={modalInd} titulo="Registrar indicador mensual" icono="fas fa-chart-line" onClose={() => setModalInd(false)}>
                <form onSubmit={guardarIndicador} className="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Año *</label>
                        <select value={formInd.anio} onChange={(e) => setFi('anio', e.target.value)} className={inputCls}>
                            {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Mes *</label>
                        <select value={formInd.mes} onChange={(e) => setFi('mes', e.target.value)} className={inputCls}>
                            {MESES.map((m) => <option key={m.valor} value={m.valor}>{m.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">UF *</label>
                        <input required type="number" step="0.01" min="0" value={formInd.uf_valor} onChange={(e) => setFi('uf_valor', e.target.value)} className={inputCls} />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">UTM *</label>
                        <input required type="number" step="0.01" min="0" value={formInd.utm_valor} onChange={(e) => setFi('utm_valor', e.target.value)} className={inputCls} />
                    </div>
                    <div className="sm:col-span-2">
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Fuente</label>
                        <input value={formInd.fuente} onChange={(e) => setFi('fuente', e.target.value)} placeholder="CMF (UF), SII (UTM)" className={inputCls} />
                    </div>
                    <div className="sm:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" onClick={() => setModalInd(false)} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 font-semibold text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="submit" disabled={guardando} className="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-60 inline-flex items-center gap-2">
                            {guardando && <i className="fas fa-spinner fa-spin" />} Guardar
                        </button>
                    </div>
                </form>
            </PanelModal>
        </div>
    );
};

export default ParametrosRrhh;
