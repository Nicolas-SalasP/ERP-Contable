import React from 'react';
import AyudaModulo from '../../../Componentes/AyudaModulo';

export const formatNumber = (value, decimals = 0) => {
    const number = Number(value ?? 0);

    return new Intl.NumberFormat('es-CL', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(Number.isFinite(number) ? number : 0);
};

export const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
};

export const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleDateString('es-CL');
};

export const getProductoNombre = (item) => {
    return item?.producto?.nombre
        || item?.nombre_producto
        || item?.producto_nombre
        || `Producto #${item?.producto_id ?? '-'}`;
};

export const getBodegaNombre = (item) => {
    return item?.bodega?.nombre
        || item?.bodega_destino?.nombre
        || item?.bodegaDestino?.nombre
        || item?.nombre_bodega
        || `Bodega #${item?.bodega_id ?? item?.bodega_destino_id ?? '-'}`;
};

const toneMap = {
    BORRADOR: 'bg-slate-100 text-slate-700 border-slate-200',
    EN_CONTEO: 'bg-blue-50 text-blue-700 border-blue-200',
    CERRADA: 'bg-amber-50 text-amber-700 border-amber-200',
    AJUSTADA: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    CANCELADA: 'bg-rose-50 text-rose-700 border-rose-200',

    ACTIVA: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    INACTIVA: 'bg-slate-100 text-slate-500 border-slate-200',

    ACTIVA_RESERVA: 'bg-blue-50 text-blue-700 border-blue-200',
    CONSUMIDA: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    LIBERADA: 'bg-slate-100 text-slate-700 border-slate-200',
    VENCIDA: 'bg-orange-50 text-orange-700 border-orange-200',
};

export const EstadoBadge = ({ value }) => {
    const estado = value || '-';
    const tone = toneMap[estado] || 'bg-indigo-50 text-indigo-700 border-indigo-200';

    return (
        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wide border ${tone}`}>
            {String(estado).replaceAll('_', ' ')}
        </span>
    );
};

export const PageHeader = ({ eyebrow = 'Inventario', title, description, actions, helpModuloId }) => {
    return (
        <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 mb-8">
            <div>
                <span className="inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-[0.2em]">
                    <i className="fas fa-boxes-stacked"></i>
                    {eyebrow}
                </span>

                <div className="flex items-center gap-3 mt-3">
                    <h1 className="text-3xl md:text-4xl font-black text-slate-800 tracking-tight">
                        {title}
                    </h1>
                    {helpModuloId && <AyudaModulo moduloId={helpModuloId} size={28} />}
                </div>

                {description && (
                    <p className="text-slate-500 font-medium mt-2 max-w-3xl">
                        {description}
                    </p>
                )}
            </div>

            {actions && (
                <div className="flex flex-wrap gap-3">
                    {actions}
                </div>
            )}
        </div>
    );
};

export const StatCard = ({ title, value, icon = 'fas fa-chart-simple', helper, tone = 'emerald' }) => {
    const tones = {
        emerald: 'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-500 group-hover:text-white',
        blue: 'bg-blue-50 text-blue-600 group-hover:bg-blue-500 group-hover:text-white',
        amber: 'bg-amber-50 text-amber-600 group-hover:bg-amber-500 group-hover:text-white',
        rose: 'bg-rose-50 text-rose-600 group-hover:bg-rose-500 group-hover:text-white',
        indigo: 'bg-indigo-50 text-indigo-600 group-hover:bg-indigo-500 group-hover:text-white',
    };

    return (
        <div className="group bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-black text-slate-400 uppercase tracking-widest">
                        {title}
                    </p>

                    <h3 className="text-3xl font-black text-slate-800 mt-2">
                        {value}
                    </h3>

                    {helper && (
                        <p className="text-sm text-slate-500 mt-2 font-medium">
                            {helper}
                        </p>
                    )}
                </div>

                <div className={`w-12 h-12 rounded-2xl flex items-center justify-center transition-colors ${tones[tone] || tones.emerald}`}>
                    <i className={`${icon} text-xl`}></i>
                </div>
            </div>
        </div>
    );
};

export const Panel = ({ title, subtitle, children, actions, className = '' }) => {
    return (
        <div className={`bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden ${className}`}>
            {(title || actions) && (
                <div className="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        {title && (
                            <h2 className="text-lg font-black text-slate-800">
                                {title}
                            </h2>
                        )}

                        {subtitle && (
                            <p className="text-sm text-slate-500 font-medium mt-1">
                                {subtitle}
                            </p>
                        )}
                    </div>

                    {actions && (
                        <div className="flex flex-wrap gap-2">
                            {actions}
                        </div>
                    )}
                </div>
            )}

            <div className="p-6">
                {children}
            </div>
        </div>
    );
};

export const LoadingState = ({ text = 'Cargando información...' }) => {
    return (
        <div className="bg-white border border-slate-200 rounded-3xl p-12 text-center shadow-sm">
            <div className="w-12 h-12 rounded-full border-4 border-emerald-100 border-t-emerald-500 animate-spin mx-auto"></div>

            <p className="mt-4 text-slate-500 font-bold">
                {text}
            </p>
        </div>
    );
};

export const EmptyState = ({
    title = 'Sin datos',
    description = 'No hay registros para mostrar todavía.',
    icon = 'fas fa-inbox',
}) => {
    return (
        <div className="text-center py-12 text-slate-400">
            <div className="w-16 h-16 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto mb-4">
                <i className={`${icon} text-2xl`}></i>
            </div>

            <h3 className="text-lg font-black text-slate-700">
                {title}
            </h3>

            <p className="text-sm text-slate-500 mt-1">
                {description}
            </p>
        </div>
    );
};

export const AlertBox = ({ children, tone = 'blue' }) => {
    const tones = {
        blue: 'bg-blue-50 border-blue-200 text-blue-800',
        emerald: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        amber: 'bg-amber-50 border-amber-200 text-amber-800',
        rose: 'bg-rose-50 border-rose-200 text-rose-800',
        slate: 'bg-slate-50 border-slate-200 text-slate-700',
    };

    return (
        <div className={`rounded-2xl border p-4 text-sm font-bold ${tones[tone] || tones.blue}`}>
            {children}
        </div>
    );
};

export const PrimaryButton = ({ children, className = '', ...props }) => {
    return (
        <button
            {...props}
            className={`inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-black py-2.5 px-5 rounded-xl shadow-lg shadow-emerald-200 transition-all text-sm ${className}`}
        >
            {children}
        </button>
    );
};

export const SecondaryButton = ({ children, className = '', ...props }) => {
    return (
        <button
            {...props}
            className={`inline-flex items-center justify-center gap-2 bg-white hover:bg-slate-50 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-slate-700 border border-slate-200 font-black py-2.5 px-5 rounded-xl transition-all text-sm ${className}`}
        >
            {children}
        </button>
    );
};

export const DangerButton = ({ children, className = '', ...props }) => {
    return (
        <button
            {...props}
            className={`inline-flex items-center justify-center gap-2 bg-rose-500 hover:bg-rose-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-black py-2.5 px-5 rounded-xl shadow-lg shadow-rose-100 transition-all text-sm ${className}`}
        >
            {children}
        </button>
    );
};

export const Field = ({ label, children }) => {
    return (
        <label className="block">
            <span className="text-xs font-black text-slate-500 uppercase tracking-widest">
                {label}
            </span>

            <div className="mt-1">
                {children}
            </div>
        </label>
    );
};

export const ErrorNotice = ({ error }) => {
    if (!error) {
        return null;
    }

    const messages = [];

    if (error?.message) {
        messages.push(error.message);
    }

    if (error?.errors) {
        Object.values(error.errors).flat().forEach((item) => {
            messages.push(item);
        });
    }

    return (
        <div className="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 font-bold">
            {messages.length
                ? messages.map((message, index) => (
                    <p key={`${message}-${index}`}>
                        {message}
                    </p>
                ))
                : 'Ocurrió un error al procesar la solicitud.'}
        </div>
    );
};

export const TableShell = ({ children }) => {
    return (
        <div className="overflow-x-auto custom-scrollbar rounded-2xl border border-slate-100">
            <table className="min-w-full text-left">
                {children}
            </table>
        </div>
    );
};

export const Th = ({ children, align = 'left' }) => {
    return (
        <th className={`px-5 py-4 text-xs font-black text-slate-500 uppercase tracking-wider ${align === 'right' ? 'text-right' : align === 'center' ? 'text-center' : 'text-left'}`}>
            {children}
        </th>
    );
};

export const Td = ({ children, align = 'left', className = '' }) => {
    return (
        <td className={`px-5 py-4 text-sm ${align === 'right' ? 'text-right' : align === 'center' ? 'text-center' : 'text-left'} ${className}`}>
            {children}
        </td>
    );
};