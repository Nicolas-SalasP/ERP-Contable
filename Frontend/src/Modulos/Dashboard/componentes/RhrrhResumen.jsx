import React from 'react';
import { Link } from 'react-router-dom';
import { formatearMoneda } from '../../../Utilidades/formato';

/* Convierte "2025-07" → "Julio 2025" */
const formatMesReferencia = (mesRef) => {
    if (!mesRef) return '';
    const [anio, mes] = mesRef.split('-');
    const fecha = new Date(Number(anio), Number(mes) - 1, 1);
    return fecha.toLocaleDateString('es-CL', { month: 'long', year: 'numeric' });
};

const formatMoneda = formatearMoneda;

const RhrrhResumen = ({ datos }) => {
    const sinPendientes = !datos || datos.liquidaciones_pendientes === 0;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-slate-700 h-full flex flex-col gap-4">
            {/* Título */}
            <div className="flex items-center gap-2">
                <div className="bg-purple-50 text-purple-600 w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
                    <i className="fas fa-users text-sm" />
                </div>
                <h3 className="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">
                    RRHH — Remuneraciones
                </h3>
            </div>

            {/* Contenido */}
            {sinPendientes ? (
                <div className="flex items-center gap-2 text-emerald-600">
                    <i className="fas fa-check-circle text-emerald-500" />
                    <span className="text-sm font-medium">Sin liquidaciones pendientes este mes</span>
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {/* Cantidad pendiente */}
                    <div className="flex items-center gap-2">
                        <i className="fas fa-exclamation-circle text-amber-500" />
                        <span className="text-sm font-bold text-amber-700">
                            {datos.liquidaciones_pendientes}{' '}
                            {datos.liquidaciones_pendientes === 1
                                ? 'liquidación pendiente'
                                : 'liquidaciones pendientes'}
                        </span>
                    </div>

                    {/* Total a pagar */}
                    <div>
                        <p className="text-xs text-slate-500 mb-0.5">Total a pagar</p>
                        <p className="text-2xl font-black text-slate-800 dark:text-slate-200">
                            {formatMoneda(datos.total_liquido_pendiente)}
                        </p>
                    </div>

                    {/* Mes de referencia */}
                    {datos.mes_referencia && (
                        <p className="text-xs text-slate-400">
                            Mes: {formatMesReferencia(datos.mes_referencia)}
                        </p>
                    )}

                    {/* Enlace */}
                    <Link
                        to="/rrhh/liquidaciones"
                        className="mt-1 inline-flex items-center gap-1.5 text-xs font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 px-3 py-1.5 rounded-lg transition-colors self-start"
                    >
                        <i className="fas fa-arrow-right text-[10px]" />
                        Ver liquidaciones
                    </Link>
                </div>
            )}
        </div>
    );
};

export default RhrrhResumen;
