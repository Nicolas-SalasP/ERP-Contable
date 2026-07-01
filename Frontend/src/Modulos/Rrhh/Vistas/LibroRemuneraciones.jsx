import React, { useState } from 'react';
import { usePermisos } from '../../../Contextos/Permisos';
import { MESES, nombreMes, formatPesos } from '../Utilidades/formato';
import rrhhApi from '../Servicios/rrhhApi';

const anioActual = new Date().getFullYear();
const ANIOS = Array.from({ length: 6 }, (_, i) => anioActual - i);

const inputCls =
    'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const thCls = 'px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide whitespace-nowrap';
const tdCls = 'px-3 py-2 text-sm text-slate-800 dark:text-slate-200 whitespace-nowrap';
const tdNumCls = 'px-3 py-2 text-sm text-right text-slate-800 dark:text-slate-200 whitespace-nowrap';

const LibroRemuneraciones = () => {
    const { tienePermiso } = usePermisos();
    const puedeVer = tienePermiso('rrhh.remuneraciones.ver');

    const [periodo, setPeriodo]       = useState({ anio: anioActual, mes: new Date().getMonth() + 1 });
    const [datos, setDatos]           = useState(null);
    const [cargando, setCargando]     = useState(false);
    const [descargando, setDescarg]   = useState(null); // 'excel' | 'pdf' | null
    const [mensaje, setMensaje]       = useState(null);

    const mostrarMensaje = (tipo, texto) => {
        setMensaje({ tipo, texto });
        setTimeout(() => setMensaje(null), 5000);
    };

    const handlePrevisualizar = async () => {
        if (!puedeVer) return;
        setCargando(true);
        setDatos(null);
        try {
            const resp = await rrhhApi.libroRemuneraciones.simular(periodo.anio, periodo.mes);
            const payload = resp?.data ?? resp;
            setDatos(payload);
            if ((payload?.cantidad_trabajadores ?? 0) === 0) {
                mostrarMensaje('info', `Sin liquidaciones emitidas para ${nombreMes(periodo.mes)} ${periodo.anio}.`);
            }
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al cargar el libro.';
            mostrarMensaje('error', msg);
        } finally {
            setCargando(false);
        }
    };

    const handleDescargar = async (formato) => {
        if (!puedeVer) return;
        setDescarg(formato);
        try {
            await rrhhApi.libroRemuneraciones.descargar(periodo.anio, periodo.mes, formato);
        } catch (_) {
            // el interceptor global muestra el toast
        } finally {
            setDescarg(null);
        }
    };

    const filas    = datos?.filas ?? [];
    const totales  = datos?.totales ?? null;
    const cantidad = datos?.cantidad_trabajadores ?? 0;

    return (
        <div className="max-w-7xl mx-auto p-6 md:p-8">
            {/* Encabezado */}
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex flex-wrap items-center gap-3">
                    <i className="fas fa-book-open text-emerald-600" />
                    Libro de Remuneraciones
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    DFL-1 Art. 62 Código del Trabajo — obligatorio para empresas con 5 o más trabajadores.
                </p>
            </header>

            {/* Mensaje de estado */}
            {mensaje && (
                <div className={`mb-4 px-4 py-3 rounded-lg text-sm font-medium flex items-center gap-2 ${
                    mensaje.tipo === 'error'
                        ? 'bg-red-50 text-red-800 border border-red-200'
                        : mensaje.tipo === 'exito'
                            ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                            : 'bg-blue-50 text-blue-800 border border-blue-200'
                }`}>
                    <i className={
                        mensaje.tipo === 'error' ? 'fas fa-exclamation-circle' :
                        mensaje.tipo === 'exito' ? 'fas fa-check-circle' :
                        'fas fa-info-circle'
                    } />
                    {mensaje.texto}
                </div>
            )}

            {/* Selector de período y botón previsualizar */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 mb-6">
                <h2 className="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">Período</h2>
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Año</label>
                        <select
                            value={periodo.anio}
                            onChange={(e) => setPeriodo((p) => ({ ...p, anio: Number(e.target.value) }))}
                            className={inputCls}
                        >
                            {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Mes</label>
                        <select
                            value={periodo.mes}
                            onChange={(e) => setPeriodo((p) => ({ ...p, mes: Number(e.target.value) }))}
                            className={inputCls}
                        >
                            {MESES.map((m) => <option key={m.valor} value={m.valor}>{m.label}</option>)}
                        </select>
                    </div>
                    <button
                        onClick={handlePrevisualizar}
                        disabled={cargando || !puedeVer}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50"
                    >
                        {cargando
                            ? <><i className="fas fa-spinner fa-spin" /> Cargando...</>
                            : <><i className="fas fa-eye" /> Previsualizar</>
                        }
                    </button>
                </div>
            </div>

            {/* Resultados */}
            {datos !== null && (
                <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden mb-6">
                    {/* Barra de resumen y exportación */}
                    <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <span className="font-bold text-slate-900 dark:text-slate-100">
                                {nombreMes(periodo.mes)} {periodo.anio}
                            </span>
                            <span className="ml-3 text-sm text-slate-500 dark:text-slate-400">
                                {cantidad} trabajador{cantidad !== 1 ? 'es' : ''}
                            </span>
                        </div>
                        {cantidad > 0 && (
                            <div className="flex gap-2">
                                <button
                                    onClick={() => handleDescargar('excel')}
                                    disabled={descargando !== null}
                                    className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 font-semibold text-sm disabled:opacity-50"
                                >
                                    {descargando === 'excel'
                                        ? <><i className="fas fa-spinner fa-spin" /> Descargando...</>
                                        : <><i className="fas fa-file-excel" /> Descargar Excel</>
                                    }
                                </button>
                                <button
                                    onClick={() => handleDescargar('pdf')}
                                    disabled={descargando !== null}
                                    className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-red-500 text-red-700 hover:bg-red-50 font-semibold text-sm disabled:opacity-50"
                                >
                                    {descargando === 'pdf'
                                        ? <><i className="fas fa-spinner fa-spin" /> Descargando...</>
                                        : <><i className="fas fa-file-pdf" /> Descargar PDF</>
                                    }
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Tabla */}
                    {filas.length === 0 ? (
                        <div className="py-12 text-center text-slate-400">
                            <i className="fas fa-inbox text-3xl mb-3 block" />
                            <p>Sin liquidaciones emitidas para el período.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th className={thCls}>RUT</th>
                                        <th className={thCls}>Nombre</th>
                                        <th className={thCls}>Cargo</th>
                                        <th className={`${thCls} text-center`}>Días</th>
                                        <th className={`${thCls} text-right`}>Sueldo Base</th>
                                        <th className={`${thCls} text-right`}>H. Extra</th>
                                        <th className={`${thCls} text-right`}>Total Haberes</th>
                                        <th className={`${thCls} text-right`}>Desc. Prev.</th>
                                        <th className={`${thCls} text-right`}>Desc. Legal</th>
                                        <th className={`${thCls} text-right`}>Otros Desc.</th>
                                        <th className={`${thCls} text-right`}>Total Desc.</th>
                                        <th className={`${thCls} text-right`}>Líquido</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                    {filas.map((fila, i) => (
                                        <tr key={i} className="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                            <td className={tdCls}>{fila.rut}</td>
                                            <td className={tdCls}>{fila.nombre}</td>
                                            <td className={`${tdCls} text-slate-500 dark:text-slate-400`}>{fila.cargo}</td>
                                            <td className={`${tdCls} text-center`}>{fila.dias_trabajados}</td>
                                            <td className={tdNumCls}>{formatPesos(fila.sueldo_base)}</td>
                                            <td className={tdNumCls}>{formatPesos(fila.horas_extras)}</td>
                                            <td className={`${tdNumCls} font-semibold`}>{formatPesos(fila.total_haberes)}</td>
                                            <td className={`${tdNumCls} text-amber-700 dark:text-amber-400`}>{formatPesos(fila.descuento_previsional)}</td>
                                            <td className={`${tdNumCls} text-amber-700 dark:text-amber-400`}>{formatPesos(fila.descuento_legal)}</td>
                                            <td className={`${tdNumCls} text-amber-700 dark:text-amber-400`}>{formatPesos(fila.otros_descuentos)}</td>
                                            <td className={`${tdNumCls} font-semibold text-red-700 dark:text-red-400`}>{formatPesos(fila.total_descuentos)}</td>
                                            <td className={`${tdNumCls} font-bold text-emerald-700 dark:text-emerald-400`}>{formatPesos(fila.liquido)}</td>
                                        </tr>
                                    ))}
                                </tbody>

                                {/* Fila de totales */}
                                {totales && (
                                    <tfoot>
                                        <tr className="bg-slate-900 dark:bg-slate-950 text-white">
                                            <td className="px-3 py-3 text-sm font-bold" colSpan={4}>TOTALES</td>
                                            <td className="px-3 py-3 text-sm text-right font-bold">{formatPesos(totales.sueldo_base)}</td>
                                            <td className="px-3 py-3 text-sm text-right font-bold">{formatPesos(totales.horas_extras)}</td>
                                            <td className="px-3 py-3 text-sm text-right font-bold">{formatPesos(totales.total_haberes)}</td>
                                            <td className="px-3 py-3 text-sm text-right font-bold">{formatPesos(totales.descuento_previsional)}</td>
                                            <td className="px-3 py-3 text-sm text-right font-bold">{formatPesos(totales.descuento_legal)}</td>
                                            <td className="px-3 py-3 text-sm text-right font-bold">{formatPesos(totales.otros_descuentos)}</td>
                                            <td className="px-3 py-3 text-sm text-right font-bold">{formatPesos(totales.total_descuentos)}</td>
                                            <td className="px-3 py-3 text-sm text-right font-bold text-emerald-400">{formatPesos(totales.liquido)}</td>
                                        </tr>
                                    </tfoot>
                                )}
                            </table>
                        </div>
                    )}
                </div>
            )}

            {/* Estado inicial: guía al usuario */}
            {datos === null && !cargando && (
                <div className="bg-slate-50 dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-600 rounded-2xl py-12 text-center text-slate-400">
                    <i className="fas fa-book-open text-4xl mb-3 block" />
                    <p className="text-base font-medium">Selecciona un período y presiona "Previsualizar".</p>
                    <p className="text-sm mt-1">Se mostrarán todas las liquidaciones emitidas del mes.</p>
                </div>
            )}
        </div>
    );
};

export default LibroRemuneraciones;
