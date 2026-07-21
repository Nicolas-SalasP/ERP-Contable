import React, { useState, useEffect, useCallback } from 'react';
import { api } from '../../../Configuracion/api';
import { logger } from '../../../Configuracion/logger';
import { X } from 'lucide-react';
import { formatearMoneda } from '../../../Utilidades/formato';

const formatMoney = (amount) => {
    if (!amount || parseFloat(amount) === 0) return '-';
    return formatearMoneda(amount);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        const soloFecha = dateString.split('T')[0];
        const [year, month, day] = soloFecha.split('-');
        return `${day}-${month}-${year}`;
    } catch {
        return dateString;
    }
};

const TIPO_BADGE = {
    ANULADO: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400',
    RECLASIFICADO: 'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-400',
};

const ModalDetalleAsiento = ({ isOpen, asientoId, onClose }) => {
    const [cargando, setCargando] = useState(false);
    const [asiento, setAsiento] = useState(null);
    const [error, setError] = useState(null);

    const cargarDetalle = useCallback(async () => {
        if (!asientoId) return;
        setCargando(true);
        setError(null);
        setAsiento(null);
        try {
            const res = await api.get(`/contabilidad/asientos/${asientoId}`);
            if (res.success) {
                setAsiento(res.data);
            } else {
                setError(res.message || 'No se pudo cargar el comprobante.');
            }
        } catch (err) {
            logger.error('ModalDetalleAsiento: error al cargar asiento', err);
            setError('Error de conexión al cargar el comprobante.');
        } finally {
            setCargando(false);
        }
    }, [asientoId]);

    useEffect(() => {
        if (isOpen && asientoId) {
            cargarDetalle();
        }
    }, [isOpen, asientoId, cargarDetalle]);

    // Cierre con tecla Escape
    useEffect(() => {
        if (!isOpen) return;
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    const cabecera = asiento?.cabecera ?? {};
    const detalles = asiento?.detalles ?? [];
    const totalDebe = detalles.reduce((acc, d) => acc + parseFloat(d.debe || 0), 0);
    const totalHaber = detalles.reduce((acc, d) => acc + parseFloat(d.haber || 0), 0);
    const estadoBadge = TIPO_BADGE[cabecera.estado] ?? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-400';

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onClick={onClose}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClose(); } }}
            role="button"
            tabIndex={0}
        >
            <div
                className="bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-3xl max-h-[90vh] flex flex-col animate-fade-in"
                onClick={(e) => e.stopPropagation()}
                onKeyDown={(e) => e.stopPropagation()}
                role="presentation"
            >
                <div className="bg-slate-50 dark:bg-slate-900 p-5 border-b border-slate-200 dark:border-slate-700 flex justify-between items-start rounded-t-xl">
                    <div>
                        <h2 className="text-lg font-bold text-slate-800 dark:text-slate-200">
                            Comprobante Contable
                        </h2>
                        {cabecera.codigo_unico || cabecera.numero_comprobante ? (
                            <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                                N°{' '}
                                <span className="font-mono font-bold text-slate-700 dark:text-slate-300">
                                    {cabecera.codigo_unico || cabecera.numero_comprobante}
                                </span>
                                {cabecera.fecha && (
                                    <span className="ml-3 text-slate-400">
                                        — {formatDate(cabecera.fecha)}
                                    </span>
                                )}
                            </p>
                        ) : null}
                    </div>
                    <div className="flex items-center gap-3">
                        {cabecera.estado && (
                            <span className={`px-2.5 py-1 rounded-full text-xs font-bold uppercase ${estadoBadge}`}>
                                {cabecera.tipo_asiento || cabecera.tipo || cabecera.estado}
                            </span>
                        )}
                        <button
                            onClick={onClose}
                            className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"
                            aria-label="Cerrar"
                        >
                            <X size={18} strokeWidth={2} />
                        </button>
                    </div>
                </div>

                <div className="overflow-y-auto flex-1">
                    {cargando && (
                        <div className="flex flex-col items-center justify-center py-16 gap-3">
                            <div className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin" />
                            <p className="text-sm text-slate-500 dark:text-slate-400">Cargando comprobante…</p>
                        </div>
                    )}

                    {error && !cargando && (
                        <div className="flex flex-col items-center justify-center py-16 gap-2 text-center px-6">
                            <p className="text-red-600 dark:text-red-400 font-medium">{error}</p>
                            <button
                                onClick={cargarDetalle}
                                className="mt-2 text-sm text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                Reintentar
                            </button>
                        </div>
                    )}

                    {asiento && !cargando && (
                        <>
                            {cabecera.glosa && (
                                <div className="px-5 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                                    <span className="text-[10px] font-bold text-slate-400 uppercase block mb-0.5">
                                        Glosa / Descripción
                                    </span>
                                    <p className="text-sm text-slate-700 dark:text-slate-300 italic">
                                        "{cabecera.glosa}"
                                    </p>
                                </div>
                            )}

                            <table className="w-full text-left">
                                <thead className="bg-slate-100 dark:bg-slate-900 text-[11px] font-bold uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th className="px-5 py-3">Cuenta</th>
                                        <th className="px-5 py-3">Descripción</th>
                                        <th className="px-5 py-3 text-right text-emerald-600 dark:text-emerald-400">Debe</th>
                                        <th className="px-5 py-3 text-right">Haber</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                                    {detalles.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-5 py-8 text-center text-slate-400 dark:text-slate-500">
                                                Sin líneas de detalle
                                            </td>
                                        </tr>
                                    ) : (
                                        detalles.map((det, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                                <td className="px-5 py-2.5">
                                                    <div className="font-mono font-bold text-slate-700 dark:text-slate-300 text-xs">
                                                        {det.cuenta_contable}
                                                    </div>
                                                    <div className="text-slate-400 dark:text-slate-500 text-xs truncate max-w-[150px]">
                                                        {det.cuenta_nombre || det.cuenta?.nombre}
                                                    </div>
                                                </td>
                                                <td className="px-5 py-2.5 text-slate-600 dark:text-slate-300 text-xs max-w-[180px]">
                                                    {det.descripcion || det.glosa || '-'}
                                                </td>
                                                <td className="px-5 py-2.5 text-right font-mono text-emerald-600 dark:text-emerald-400 font-medium text-xs">
                                                    {parseFloat(det.debe) > 0 ? formatMoney(det.debe) : '-'}
                                                </td>
                                                <td className="px-5 py-2.5 text-right font-mono text-slate-600 dark:text-slate-300 font-medium text-xs">
                                                    {parseFloat(det.haber) > 0 ? formatMoney(det.haber) : '-'}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                                <tfoot className="bg-slate-50 dark:bg-slate-900 border-t-2 border-slate-200 dark:border-slate-600">
                                    <tr>
                                        <td colSpan={2} className="px-5 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">
                                            Totales
                                        </td>
                                        <td className="px-5 py-3 text-right font-bold font-mono text-emerald-700 dark:text-emerald-400 text-sm">
                                            {formatMoney(totalDebe)}
                                        </td>
                                        <td className="px-5 py-3 text-right font-bold font-mono text-slate-700 dark:text-slate-300 text-sm">
                                            {formatMoney(totalHaber)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </>
                    )}
                </div>

                <div className="px-5 py-3 border-t border-slate-200 dark:border-slate-700 flex justify-end bg-slate-50 dark:bg-slate-900 rounded-b-xl">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-medium hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ModalDetalleAsiento;
