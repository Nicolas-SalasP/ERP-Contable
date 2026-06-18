import React from 'react';
import { Loader2 } from 'lucide-react';

const COLORES = {
    emerald: {
        normal: 'bg-emerald-600 hover:bg-emerald-700 text-white',
        disabled: 'bg-emerald-300 cursor-not-allowed text-white',
    },
    blue: {
        normal: 'bg-blue-600 hover:bg-blue-700 text-white',
        disabled: 'bg-blue-300 cursor-not-allowed text-white',
    },
    indigo: {
        normal: 'bg-indigo-600 hover:bg-indigo-700 text-white',
        disabled: 'bg-indigo-300 cursor-not-allowed text-white',
    },
    rose: {
        normal: 'bg-rose-600 hover:bg-rose-700 text-white',
        disabled: 'bg-rose-300 cursor-not-allowed text-white',
    },
    slate: {
        normal: 'bg-slate-600 hover:bg-slate-700 text-white',
        disabled: 'bg-slate-300 cursor-not-allowed text-white',
    },
};

const TAMANOS = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-5 py-2.5 text-base',
};

const Spinner = ({ className = 'h-4 w-4 text-white' }) => (
    <Loader2 className={`animate-spin ${className}`} aria-hidden="true" strokeWidth={1.75} />
);

const BotonAccion = ({
    cargando = false,
    disabled = false,
    onClick,
    children,
    textoCargando = 'Procesando...',
    icono = null,
    color = 'emerald',
    tamano = 'md',
    type = 'button',
    className = '',
    title,
    ...rest
}) => {
    const colorClases = COLORES[color] || COLORES.emerald;
    const tamanoClases = TAMANOS[tamano] || TAMANOS.md;
    const estaDeshabilitado = cargando || disabled;

    return (
        <button
            type={type}
            onClick={onClick}
            disabled={estaDeshabilitado}
            title={title}
            aria-busy={cargando}
            className={`inline-flex items-center justify-center gap-2 font-bold rounded-lg transition-colors shadow-sm ${tamanoClases} ${
                estaDeshabilitado ? colorClases.disabled : colorClases.normal
            } ${className}`}
            {...rest}
        >
            {cargando ? (
                <>
                    <Spinner />
                    <span>{textoCargando}</span>
                </>
            ) : (
                <>
                    {icono &&
                        (typeof icono === 'string' ? (
                            <i className={icono} aria-hidden="true"></i>
                        ) : (
                            icono
                        ))}
                    <span>{children}</span>
                </>
            )}
        </button>
    );
};

export default BotonAccion;
