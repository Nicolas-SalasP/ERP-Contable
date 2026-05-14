import React from 'react';
const COLORES = {
    emerald: {
        spinner: 'border-emerald-500',
        boton: 'bg-emerald-600 hover:bg-emerald-700',
        textoBoton: 'text-white',
    },
    blue: {
        spinner: 'border-blue-500',
        boton: 'bg-blue-600 hover:bg-blue-700',
        textoBoton: 'text-white',
    },
    indigo: {
        spinner: 'border-indigo-500',
        boton: 'bg-indigo-600 hover:bg-indigo-700',
        textoBoton: 'text-white',
    },
    rose: {
        spinner: 'border-rose-500',
        boton: 'bg-rose-600 hover:bg-rose-700',
        textoBoton: 'text-white',
    },
};

const TAMANOS = {
    completo: {
        contenedor: 'min-h-[60vh] py-12',
        spinner: 'h-12 w-12 border-b-4',
        texto: 'text-xl font-bold',
        icono: 'text-5xl',
    },
    compacto: {
        contenedor: 'min-h-[20vh] py-6',
        spinner: 'h-8 w-8 border-b-2',
        texto: 'text-base font-semibold',
        icono: 'text-3xl',
    },
    inline: {
        contenedor: 'py-3',
        spinner: 'h-5 w-5 border-b-2',
        texto: 'text-sm',
        icono: 'text-2xl',
    },
};

const extraerMensajeError = (error) => {
    if (!error) return null;
    if (typeof error === 'string') return error;
    if (error.response?.data?.message) return error.response.data.message;
    if (error.message) return error.message;
    return 'Ocurrio un error inesperado.';
};

const EstadoCarga = ({
    cargando = false,
    error = null,
    vacio = false,
    onReintentar = null,
    children,
    mensajeCargando = 'Cargando...',
    tituloVacio = null,
    mensajeVacio = 'No hay datos para mostrar.',
    iconoVacio = '📭',
    tamano = 'completo',
    color = 'emerald',
    className = '',
    classNameVacio = '',
}) => {
    const colorClases = COLORES[color] || COLORES.emerald;
    const tamanoClases = TAMANOS[tamano] || TAMANOS.completo;

    // Estado: ERROR (prioridad maxima)
    if (error) {
        const mensajeError = extraerMensajeError(error);
        return (
            <div
                role="alert"
                aria-live="assertive"
                className={`flex flex-col items-center justify-center text-center ${tamanoClases.contenedor} ${className}`}
            >
                <div className={`${tamanoClases.icono} mb-3`}>⚠️</div>
                <h3 className={`${tamanoClases.texto} text-rose-700 mb-2`}>
                    Algo salio mal
                </h3>
                <p className="text-sm text-slate-600 max-w-md mb-4 px-4">
                    {mensajeError}
                </p>
                {onReintentar && (
                    <button
                        type="button"
                        onClick={onReintentar}
                        className={`px-5 py-2 rounded-lg font-bold text-sm uppercase tracking-wide shadow-sm transition-colors ${colorClases.boton} ${colorClases.textoBoton}`}
                    >
                        Reintentar
                    </button>
                )}
            </div>
        );
    }

    if (cargando) {
        return (
            <div
                role="status"
                aria-live="polite"
                aria-busy="true"
                className={`flex flex-col items-center justify-center text-slate-400 ${tamanoClases.contenedor} ${className}`}
            >
                <div
                    className={`animate-spin rounded-full ${tamanoClases.spinner} ${colorClases.spinner} mb-4`}
                    aria-hidden="true"
                ></div>
                <h2 className={tamanoClases.texto}>{mensajeCargando}</h2>
            </div>
        );
    }

    if (vacio) {
        return (
            <div
                className={`flex flex-col items-center justify-center text-center text-slate-400 ${tamanoClases.contenedor} ${className} ${classNameVacio}`}
            >
                <div className={`${tamanoClases.icono} mb-3`} aria-hidden="true">
                    {iconoVacio}
                </div>
                {tituloVacio && (
                    <h3 className={`${tamanoClases.texto} text-slate-700 mb-1`}>
                        {tituloVacio}
                    </h3>
                )}
                <p className={`${tituloVacio ? 'text-sm' : tamanoClases.texto} text-slate-500`}>
                    {mensajeVacio}
                </p>
            </div>
        );
    }

    return <>{children}</>;
};

export default EstadoCarga;
