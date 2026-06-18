import React from 'react';

/* Mapas de estilo por urgencia y por tipo */
const ESTILO_URGENCIA = {
    alta: {
        borde: 'border-l-rose-500',
        badge: 'bg-rose-50 text-rose-700 border-rose-200',
        icono: 'text-rose-500',
    },
    media: {
        borde: 'border-l-amber-400',
        badge: 'bg-amber-50 text-amber-700 border-amber-200',
        icono: 'text-amber-500',
    },
    baja: {
        borde: 'border-l-blue-400',
        badge: 'bg-blue-50 text-blue-700 border-blue-200',
        icono: 'text-blue-500',
    },
};

const ICONO_TIPO = {
    dj: 'fas fa-file-alt',
    f29: 'fas fa-calculator',
    rrhh: 'fas fa-users',
};

const AlertasPendientes = ({ alertas }) => {
    if (!alertas || alertas.length === 0) return null;

    return (
        <div className="mb-8">
            {/* Título con divisor */}
            <div className="flex items-center gap-3 mb-4">
                <h3 className="text-base font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap flex items-center gap-2">
                    <i className="fas fa-exclamation-triangle text-amber-500" />
                    Recordatorios pendientes
                </h3>
                <div className="flex-1 h-px bg-slate-200 dark:bg-slate-700" />
                <span className="text-xs font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">
                    {alertas.length}
                </span>
            </div>

            {/* Tarjetas de alertas en fila con scroll horizontal en móvil */}
            <div className="flex flex-wrap gap-3">
                {alertas.map((alerta, idx) => {
                    const urgencia = alerta.urgencia ?? 'baja';
                    const estilos = ESTILO_URGENCIA[urgencia] ?? ESTILO_URGENCIA.baja;
                    const iconoClase = ICONO_TIPO[alerta.tipo] ?? 'fas fa-bell';

                    return (
                        <div
                            key={idx}
                            className={`bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 border-l-4 ${estilos.borde} shadow-sm p-4 flex items-start gap-3 min-w-[240px] max-w-[320px] flex-1`}
                        >
                            <div className={`mt-0.5 shrink-0 ${estilos.icono}`}>
                                <i className={`${iconoClase} text-base`} />
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-bold text-slate-800 dark:text-slate-200 leading-snug truncate">
                                    {alerta.titulo}
                                </p>
                                <p className="text-xs text-slate-500 mt-0.5 leading-snug">
                                    {alerta.descripcion}
                                </p>
                            </div>
                            <span className={`shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border ${estilos.badge}`}>
                                {urgencia}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default AlertasPendientes;
