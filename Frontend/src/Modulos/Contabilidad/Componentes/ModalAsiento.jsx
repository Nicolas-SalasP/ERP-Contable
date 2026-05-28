import React from 'react';

const formatCurrency = (amount) =>
    new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-CL');
};

const ModalAsiento = ({ isOpen, onClose, data, loading }) => {
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 bg-slate-900/80 flex items-center justify-center z-50 p-4 backdrop-blur-sm animate-fade-in">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh] border border-slate-200">
                <div className="bg-slate-900 text-white px-4 md:px-8 py-5 flex justify-between items-center">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">
                                Contabilidad
                            </span>
                            {loading && (
                                <span className="text-xs text-blue-300 animate-pulse">
                                    <i className="fas fa-circle-notch fa-spin mr-1"></i> Cargando...
                                </span>
                            )}
                        </div>
                        <h3 className="text-lg md:text-xl font-bold mt-1">
                            {loading
                                ? 'Cargando Asiento...'
                                : `Asiento Contable N° ${data?.cabecera?.numero_asiento || '---'}`}
                        </h3>
                    </div>
                    <button
                        onClick={onClose}
                        className="text-slate-400 hover:text-white transition-colors p-2 rounded-full hover:bg-slate-800"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                {loading ? (
                    <div className="p-16 text-center text-slate-400">
                        <p>Recuperando información contable...</p>
                    </div>
                ) : (
                    data && (
                        <>
                            <div className="bg-slate-50 px-4 md:px-8 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div className="flex-1">
                                    <p className="text-xs text-slate-500 font-bold uppercase tracking-wide">
                                        Glosa / Descripción
                                    </p>
                                    <p className="text-slate-800 italic font-medium mt-1 text-sm md:text-base">
                                        "{data.cabecera.glosa}"
                                    </p>
                                </div>
                                <div className="text-left md:text-right w-full md:w-auto">
                                    <p className="text-xs text-slate-500 font-bold uppercase tracking-wide">
                                        Fecha Contable
                                    </p>
                                    <p className="text-slate-800 font-mono font-bold mt-1">
                                        {formatDate(data.cabecera.fecha_contable)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex-1 overflow-auto custom-scrollbar">
                                <table className="min-w-full divide-y divide-slate-100">
                                    <thead className="bg-white sticky top-0 z-10 shadow-sm">
                                        <tr>
                                            <th className="px-4 md:px-8 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">
                                                Cuenta Contable
                                            </th>
                                            <th className="px-4 md:px-8 py-3 text-right text-xs font-bold text-emerald-600 uppercase tracking-wider w-32 md:w-40 whitespace-nowrap">
                                                Debe
                                            </th>
                                            <th className="px-4 md:px-8 py-3 text-right text-xs font-bold text-red-600 uppercase tracking-wider w-32 md:w-40 whitespace-nowrap">
                                                Haber
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-slate-50 text-sm">
                                        {data.detalles.map((det, idx) => (
                                            <tr key={idx} className="hover:bg-blue-50/50 transition-colors">
                                                <td className="px-4 md:px-8 py-4">
                                                    <div className="font-bold text-slate-800">
                                                        {det.nombre_cuenta}
                                                    </div>
                                                    <div className="text-xs text-slate-500 font-mono mt-1 bg-slate-100 px-2 py-0.5 rounded inline-block border border-slate-200">
                                                        {det.cuenta_contable}
                                                    </div>
                                                </td>
                                                <td className="px-4 md:px-8 py-4 text-right font-mono font-medium text-emerald-700 bg-emerald-50/10 border-l border-white whitespace-nowrap">
                                                    {parseFloat(det.debe) > 0 ? formatCurrency(det.debe) : '-'}
                                                </td>
                                                <td className="px-4 md:px-8 py-4 text-right font-mono font-medium text-red-700 bg-red-50/10 border-l border-white whitespace-nowrap">
                                                    {parseFloat(det.haber) > 0 ? formatCurrency(det.haber) : '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-slate-50 font-bold text-slate-800 border-t border-slate-200">
                                        <tr>
                                            <td className="px-4 md:px-8 py-4 text-right uppercase text-xs tracking-wider text-slate-500">
                                                Totales
                                            </td>
                                            <td className="px-4 md:px-8 py-4 text-right text-emerald-700 bg-emerald-100/50 whitespace-nowrap">
                                                {formatCurrency(
                                                    data.detalles.reduce(
                                                        (acc, i) => acc + parseFloat(i.debe),
                                                        0
                                                    )
                                                )}
                                            </td>
                                            <td className="px-4 md:px-8 py-4 text-right text-red-700 bg-red-100/50 whitespace-nowrap">
                                                {formatCurrency(
                                                    data.detalles.reduce(
                                                        (acc, i) => acc + parseFloat(i.haber),
                                                        0
                                                    )
                                                )}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </>
                    )
                )}
                <div className="p-4 bg-gray-50 border-t border-slate-200 flex justify-end">
                    <button
                        onClick={onClose}
                        className="px-6 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-900 font-bold transition-colors shadow-sm text-sm w-full md:w-auto"
                    >
                        Cerrar Detalle
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ModalAsiento;
