import React, { useState, useCallback } from 'react';
import * as XLSX from '@e965/xlsx';
import { api } from '../../../Configuracion/api';
import { MESES, nombreMes } from '../Utilidades/formato';

const anioActual = new Date().getFullYear();
const ANIOS = Array.from({ length: 6 }, (_, i) => anioActual - i);

const inputCls =
    'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

import { formatearMoneda } from '../../../Utilidades/formato';

const pesos = formatearMoneda;

const thCls = 'px-4 py-2 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide bg-slate-50 dark:bg-slate-900';
const tdCls = 'px-4 py-3 text-sm text-slate-700 dark:text-slate-300';
const tdNumCls = 'px-4 py-3 text-sm text-slate-700 dark:text-slate-300 text-right font-mono';

const EmrclRrhh = () => {
    const [filtros, setFiltros] = useState({ anio: anioActual, mes: new Date().getMonth() + 1 });
    const [datos, setDatos] = useState(null);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);

    const cargar = useCallback(async () => {
        setCargando(true);
        setError(null);
        setDatos(null);
        try {
            const resp = await api.get(`/rrhh/emrcl/${filtros.anio}/${filtros.mes}`);
            setDatos(resp?.data ?? resp);
        } catch (err) {
            setError(err?.message ?? 'Error al cargar el reporte.');
        } finally {
            setCargando(false);
        }
    }, [filtros]);

    const exportarExcel = () => {
        if (!datos) return;

        const wb = XLSX.utils.book_new();

        const ws1 = XLSX.utils.aoa_to_sheet([
            ['Concepto', 'Mujeres', 'Hombres', 'Total'],
            [
                'Trabajadores con contrato directo',
                datos.cuadro1_trabajadores.mujeres,
                datos.cuadro1_trabajadores.hombres,
                datos.cuadro1_trabajadores.total,
            ],
        ]);
        XLSX.utils.book_append_sheet(wb, ws1, 'Cuadro1');

        const ws2 = XLSX.utils.aoa_to_sheet([
            ['Concepto', 'Mujeres', 'Hombres'],
            ['Remuneraciones ordinarias', datos.cuadro2_remuneraciones.mujeres.remuneraciones_ordinarias, datos.cuadro2_remuneraciones.hombres.remuneraciones_ordinarias],
            ['Aportes patronales obligatorios', datos.cuadro2_remuneraciones.mujeres.aportes_patronales, datos.cuadro2_remuneraciones.hombres.aportes_patronales],
        ]);
        XLSX.utils.book_append_sheet(wb, ws2, 'Cuadro2');

        const ws3 = XLSX.utils.aoa_to_sheet([
            ['Concepto', 'Mujeres', 'Hombres'],
            ['Cotización AFP (incluye comisión)', datos.cuadro3_deducciones.mujeres.afp, datos.cuadro3_deducciones.hombres.afp],
            ['Cotización salud obligatoria', datos.cuadro3_deducciones.mujeres.salud, datos.cuadro3_deducciones.hombres.salud],
            ['Cotización AFC', datos.cuadro3_deducciones.mujeres.afc, datos.cuadro3_deducciones.hombres.afc],
            ['Impuesto único de segunda categoría', datos.cuadro3_deducciones.mujeres.impuesto, datos.cuadro3_deducciones.hombres.impuesto],
        ]);
        XLSX.utils.book_append_sheet(wb, ws3, 'Cuadro3');

        const ws4 = XLSX.utils.aoa_to_sheet([
            ['Concepto', 'Mujeres', 'Hombres'],
            ['Total horas pagadas', datos.cuadro4_horas.mujeres.horas_pagadas, datos.cuadro4_horas.hombres.horas_pagadas],
        ]);
        XLSX.utils.book_append_sheet(wb, ws4, 'Cuadro4');

        XLSX.writeFile(wb, `EMRCL_${filtros.anio}_${filtros.mes}.xlsx`);
    };

    return (
        <div className="max-w-5xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                    <i className="fas fa-building-columns text-emerald-600" />
                    Reporte EMRCL — INE
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Encuesta de Remuneraciones y Costo de la Mano de Obra. Período {nombreMes(filtros.mes)} {filtros.anio}.
                </p>
            </header>

            <div className="bg-amber-50 border border-amber-300 rounded-xl px-5 py-4 mb-5 flex gap-3">
                <i className="fas fa-triangle-exclamation text-amber-500 mt-0.5 shrink-0" />
                <p className="text-sm text-amber-800 font-medium">
                    Este reporte es de apoyo. La EMRCL se responde en el formulario web del INE. Copie estos valores al formulario oficial.
                </p>
            </div>

            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 mb-6">
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label htmlFor="emrcl-anio" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Año</label>
                        <select
                            id="emrcl-anio"
                            value={filtros.anio}
                            onChange={(e) => setFiltros((f) => ({ ...f, anio: Number(e.target.value) }))}
                            className={inputCls}
                        >
                            {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="emrcl-mes" className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Mes</label>
                        <select
                            id="emrcl-mes"
                            value={filtros.mes}
                            onChange={(e) => setFiltros((f) => ({ ...f, mes: Number(e.target.value) }))}
                            className={inputCls}
                        >
                            {MESES.map((m) => <option key={m.valor} value={m.valor}>{m.label}</option>)}
                        </select>
                    </div>
                    <button
                        onClick={cargar}
                        disabled={cargando}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50"
                    >
                        {cargando
                            ? <><i className="fas fa-spinner fa-spin" /> Generando reporte...</>
                            : <><i className="fas fa-magnifying-glass" /> Generar</>
                        }
                    </button>
                    {datos && (
                        <button
                            onClick={exportarExcel}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 font-bold text-sm"
                        >
                            <i className="fas fa-file-excel" /> Exportar Excel
                        </button>
                    )}
                </div>
            </div>

            {error && (
                <div className="bg-red-50 border border-red-200 rounded-xl px-5 py-4 mb-5 flex gap-3 text-sm text-red-700">
                    <i className="fas fa-circle-xmark mt-0.5 shrink-0" />
                    {error}
                </div>
            )}

            {datos?.advertencias?.length > 0 && (
                <div className="bg-amber-50 border border-amber-200 rounded-xl px-5 py-3 mb-5 space-y-1">
                    {datos.advertencias.map((adv, i) => (
                        <p key={i} className="text-sm text-amber-700 flex gap-2">
                            <i className="fas fa-triangle-exclamation mt-0.5 shrink-0" />
                            {adv}
                        </p>
                    ))}
                </div>
            )}

            {!datos && !cargando && !error && (
                <div className="bg-slate-50 dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-600 rounded-2xl py-12 text-center text-slate-400">
                    <i className="fas fa-building-columns text-3xl mb-3 block" />
                    Selecciona un período y genera el reporte.
                </div>
            )}

            {datos && (
                <div className="space-y-6">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div className="px-5 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                            <h2 className="font-bold text-slate-800 dark:text-slate-200 text-sm">Cuadro N°1 — Trabajadores</h2>
                        </div>
                        <table className="w-full">
                            <thead>
                                <tr>
                                    <th className={thCls}>Concepto</th>
                                    <th className={`${thCls} text-right`}>Mujeres</th>
                                    <th className={`${thCls} text-right`}>Hombres</th>
                                    <th className={`${thCls} text-right`}>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="border-t border-slate-100">
                                    <td className={tdCls}>Trabajadores con contrato directo</td>
                                    <td className={tdNumCls}>{datos.cuadro1_trabajadores.mujeres}</td>
                                    <td className={tdNumCls}>{datos.cuadro1_trabajadores.hombres}</td>
                                    <td className={`${tdNumCls} font-bold text-slate-900`}>{datos.cuadro1_trabajadores.total}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div className="px-5 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                            <h2 className="font-bold text-slate-800 dark:text-slate-200 text-sm">Cuadro N°2 — Remuneraciones y costos <span className="text-slate-400 font-normal">(en pesos)</span></h2>
                        </div>
                        <table className="w-full">
                            <thead>
                                <tr>
                                    <th className={thCls}>Concepto</th>
                                    <th className={`${thCls} text-right`}>Mujeres</th>
                                    <th className={`${thCls} text-right`}>Hombres</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr>
                                    <td className={tdCls}>Remuneraciones ordinarias</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro2_remuneraciones.mujeres.remuneraciones_ordinarias)}</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro2_remuneraciones.hombres.remuneraciones_ordinarias)}</td>
                                </tr>
                                <tr>
                                    <td className={tdCls}>Aportes patronales obligatorios</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro2_remuneraciones.mujeres.aportes_patronales)}</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro2_remuneraciones.hombres.aportes_patronales)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div className="px-5 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                            <h2 className="font-bold text-slate-800 dark:text-slate-200 text-sm">Cuadro N°3 — Deducciones legales <span className="text-slate-400 font-normal">(en pesos)</span></h2>
                        </div>
                        <table className="w-full">
                            <thead>
                                <tr>
                                    <th className={thCls}>Concepto</th>
                                    <th className={`${thCls} text-right`}>Mujeres</th>
                                    <th className={`${thCls} text-right`}>Hombres</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr>
                                    <td className={tdCls}>Cotización AFP (incluye comisión)</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro3_deducciones.mujeres.afp)}</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro3_deducciones.hombres.afp)}</td>
                                </tr>
                                <tr>
                                    <td className={tdCls}>Cotización salud obligatoria</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro3_deducciones.mujeres.salud)}</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro3_deducciones.hombres.salud)}</td>
                                </tr>
                                <tr>
                                    <td className={tdCls}>Cotización AFC</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro3_deducciones.mujeres.afc)}</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro3_deducciones.hombres.afc)}</td>
                                </tr>
                                <tr>
                                    <td className={tdCls}>Impuesto único de segunda categoría</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro3_deducciones.mujeres.impuesto)}</td>
                                    <td className={tdNumCls}>{pesos(datos.cuadro3_deducciones.hombres.impuesto)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div className="px-5 py-3 border-b border-slate-200 bg-slate-50 flex items-center gap-2">
                            <h2 className="font-bold text-slate-800 dark:text-slate-200 text-sm">Cuadro N°4 — Horas pagadas</h2>
                            {(datos.cuadro4_horas.mujeres.derivado || datos.cuadro4_horas.hombres.derivado) && (
                                <span className="text-amber-500 text-xs flex items-center gap-1">
                                    <i className="fas fa-triangle-exclamation" /> Horas derivadas
                                </span>
                            )}
                        </div>
                        <table className="w-full">
                            <thead>
                                <tr>
                                    <th className={thCls}>Concepto</th>
                                    <th className={`${thCls} text-right`}>Mujeres</th>
                                    <th className={`${thCls} text-right`}>Hombres</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="border-t border-slate-100">
                                    <td className={tdCls}>
                                        Total horas pagadas
                                        {(datos.cuadro4_horas.mujeres.derivado || datos.cuadro4_horas.hombres.derivado) && (
                                            <span className="ml-2 text-amber-500" title="Valor derivado">⚠</span>
                                        )}
                                    </td>
                                    <td className={tdNumCls}>{datos.cuadro4_horas.mujeres.horas_pagadas}</td>
                                    <td className={tdNumCls}>{datos.cuadro4_horas.hombres.horas_pagadas}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EmrclRrhh;
