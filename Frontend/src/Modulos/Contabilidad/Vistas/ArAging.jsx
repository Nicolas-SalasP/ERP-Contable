import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import { logger } from '../../../Configuracion/logger';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';
import { useToast } from '../../../Contextos/ToastContext';

const formatMoney = (valor) => {
    const n = parseFloat(valor);
    if (!n || n === 0) return <span className="text-slate-300 dark:text-slate-600">-</span>;
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(n);
};

const TARJETAS = [
    { key: 'corriente', label: 'Corriente',   bg: 'bg-emerald-50 dark:bg-emerald-900/20', borde: 'border-emerald-200 dark:border-emerald-700', texto: 'text-emerald-700 dark:text-emerald-400', sub: 'text-emerald-500 dark:text-emerald-500' },
    { key: 'd30',       label: '1 – 30 días',  bg: 'bg-yellow-50 dark:bg-yellow-900/20',   borde: 'border-yellow-200 dark:border-yellow-700',  texto: 'text-yellow-700 dark:text-yellow-400',  sub: 'text-yellow-500 dark:text-yellow-500'  },
    { key: 'd60',       label: '31 – 60 días', bg: 'bg-orange-50 dark:bg-orange-900/20',   borde: 'border-orange-200 dark:border-orange-700',  texto: 'text-orange-700 dark:text-orange-400',  sub: 'text-orange-500 dark:text-orange-500'  },
    { key: 'd90',       label: '61 – 90 días', bg: 'bg-red-50 dark:bg-red-900/20',         borde: 'border-red-200 dark:border-red-700',        texto: 'text-red-700 dark:text-red-400',        sub: 'text-red-500 dark:text-red-500'        },
    { key: 'd90plus',   label: '+90 días',     bg: 'bg-rose-50 dark:bg-rose-900/20',       borde: 'border-rose-300 dark:border-rose-700',      texto: 'text-rose-700 dark:text-rose-400',      sub: 'text-rose-500 dark:text-rose-500'      },
];

const ArAging = () => {
    const { toast } = useToast();
    const [datos, setDatos] = useState(null);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(false);

    useEffect(() => {
        const cargar = async () => {
            setCargando(true);
            setError(false);
            try {
                const res = await api.get('/contabilidad/ar-aging');
                if (res.success) {
                    setDatos(res.data);
                } else {
                    setError(true);
                    toast(res.message || 'No se pudieron cargar los datos.', 'error');
                }
            } catch (err) {
                logger.error('Error cargando AR Aging', err);
                setError(true);
                toast('No se pudo conectar con el servidor.', 'error');
            } finally {
                setCargando(false);
            }
        };
        cargar();
    }, []);

    const resumen = datos?.resumen;
    const detalle = datos?.detalle ?? [];

    return (
        <div className="max-w-full mx-auto p-6 font-sans text-slate-800 dark:text-slate-200">

            {/* Encabezado */}
            <div className="mb-6">
                <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">
                    CxC por Antigüedad
                </h1>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Saldos pendientes de clientes agrupados por tramo de antigüedad al día de hoy.
                </p>
            </div>

            {/* Tarjetas resumen */}
            {(cargando || resumen) && (
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                    {TARJETAS.map(t => (
                        <div
                            key={t.key}
                            className={`rounded-xl border p-4 shadow-sm ${t.bg} ${t.borde}`}
                        >
                            <p className={`text-[10px] font-bold uppercase tracking-wide mb-1 ${t.sub}`}>
                                {t.label}
                            </p>
                            <p className={`text-lg font-bold font-mono ${t.texto}`}>
                                {cargando ? '…' : (
                                    resumen
                                        ? new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(resumen[t.key] ?? 0)
                                        : '-'
                                )}
                            </p>
                        </div>
                    ))}
                </div>
            )}

            {/* Total general */}
            {!cargando && resumen && (
                <div className="mb-6 bg-slate-100 dark:bg-slate-700 rounded-xl border border-slate-200 dark:border-slate-600 p-4 flex items-center justify-between">
                    <span className="text-sm font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Total General</span>
                    <span className="text-xl font-bold font-mono text-slate-900 dark:text-slate-100">
                        {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(resumen.total ?? 0)}
                    </span>
                </div>
            )}

            {/* Tabla detalle */}
            <div className="bg-white dark:bg-slate-800 rounded-lg shadow border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="text-[10px] uppercase font-bold bg-slate-100 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-600">
                                <th className="px-3 py-2 text-slate-500 dark:text-slate-400 whitespace-nowrap">RUT</th>
                                <th className="px-3 py-2 text-slate-500 dark:text-slate-400">Razón Social</th>
                                <th className="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400 whitespace-nowrap">Corriente</th>
                                <th className="px-3 py-2 text-right text-yellow-600 dark:text-yellow-400 whitespace-nowrap">1-30 d</th>
                                <th className="px-3 py-2 text-right text-orange-600 dark:text-orange-400 whitespace-nowrap">31-60 d</th>
                                <th className="px-3 py-2 text-right text-red-600 dark:text-red-400 whitespace-nowrap">61-90 d</th>
                                <th className="px-3 py-2 text-right text-rose-600 dark:text-rose-400 whitespace-nowrap">+90 d</th>
                                <th className="px-3 py-2 text-right text-slate-600 dark:text-slate-300 whitespace-nowrap">Total</th>
                            </tr>
                        </thead>
                        <tbody className="text-xs divide-y divide-slate-100 dark:divide-slate-700">
                            {cargando ? (
                                <TablaSkeleton filas={5} columnas={8} />
                            ) : error ? (
                                <EstadoVacio mensaje="Error al cargar los datos. Intente recargar la página." />
                            ) : detalle.length === 0 ? (
                                <EstadoVacio mensaje="No hay saldos pendientes de clientes." />
                            ) : (
                                detalle.map((fila, idx) => (
                                    <tr key={fila.cliente_id ?? idx} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                        <td className="px-3 py-2 font-mono text-slate-500 dark:text-slate-400 whitespace-nowrap">{fila.rut}</td>
                                        <td className="px-3 py-2 text-slate-700 dark:text-slate-300">{fila.razon_social}</td>
                                        <td className="px-3 py-2 text-right font-mono whitespace-nowrap">{formatMoney(fila.corriente)}</td>
                                        <td className="px-3 py-2 text-right font-mono whitespace-nowrap">{formatMoney(fila.d30)}</td>
                                        <td className="px-3 py-2 text-right font-mono whitespace-nowrap">{formatMoney(fila.d60)}</td>
                                        <td className="px-3 py-2 text-right font-mono whitespace-nowrap">{formatMoney(fila.d90)}</td>
                                        <td className="px-3 py-2 text-right font-mono whitespace-nowrap">{formatMoney(fila.d90plus)}</td>
                                        <td className="px-3 py-2 text-right font-mono font-bold text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                            {formatMoney(fila.total)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        {!cargando && detalle.length > 0 && resumen && (
                            <tfoot>
                                <tr className="text-xs font-bold border-t-2 border-slate-300 dark:border-slate-500 bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                    <td className="px-3 py-3" colSpan={2}>TOTALES</td>
                                    <td className="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(resumen.corriente ?? 0)}
                                    </td>
                                    <td className="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(resumen.d30 ?? 0)}
                                    </td>
                                    <td className="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(resumen.d60 ?? 0)}
                                    </td>
                                    <td className="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(resumen.d90 ?? 0)}
                                    </td>
                                    <td className="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(resumen.d90plus ?? 0)}
                                    </td>
                                    <td className="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(resumen.total ?? 0)}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </div>
    );
};

export default ArAging;
