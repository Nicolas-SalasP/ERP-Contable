import React, { useState, useEffect, useRef } from 'react';
import { obtenerModulo } from '../Utilidades/glosario';
import { logger } from '../Configuracion/logger';

const AyudaModulo = ({ moduloId, size = 24, className = '' }) => {
    const [abierto, setAbierto] = useState(false);
    const modalRef = useRef(null);
    const botonRef = useRef(null);
    const modulo = obtenerModulo(moduloId);

    useEffect(() => {
        if (!abierto) return;
        const handler = (e) => {
            if (e.key === 'Escape') setAbierto(false);
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [abierto]);

    useEffect(() => {
        if (abierto) {
            const originalOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            return () => {
                document.body.style.overflow = originalOverflow;
            };
        }
    }, [abierto]);

    if (!modulo) {
        logger.warn(`[AyudaModulo] moduloId "${moduloId}" no existe en el glosario.`);
        return null;
    }

    const cerrar = () => setAbierto(false);

    return (
        <>
            <button
                ref={botonRef}
                type="button"
                onClick={() => setAbierto(true)}
                aria-label={`Ayuda sobre ${modulo.titulo}`}
                title={`¿Que es ${modulo.titulo}?`}
                className={`inline-flex items-center justify-center rounded-full bg-blue-600 text-white font-bold shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-1 transition-colors ${className}`}
                style={{ width: size, height: size, fontSize: size * 0.55 }}
                data-testid="ayuda-modulo-boton"
            >
                <span aria-hidden="true">i</span>
            </button>

            {abierto && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
                    onClick={cerrar}
                    role="presentation"
                    data-testid="ayuda-modulo-overlay"
                >
                    <div
                        ref={modalRef}
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby={`ayuda-titulo-${modulo.id}`}
                        className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-hidden flex flex-col"
                        onClick={(e) => e.stopPropagation()}
                        data-testid="ayuda-modulo-modal"
                    >
                        {/* Header */}
                        <div className="bg-gradient-to-br from-blue-600 to-indigo-700 text-white px-6 py-5 flex items-start justify-between gap-4">
                            <div className="flex items-start gap-4">
                                <div className="text-4xl leading-none" aria-hidden="true">
                                    {modulo.icono}
                                </div>
                                <div>
                                    <h2 id={`ayuda-titulo-${modulo.id}`} className="text-2xl font-black tracking-tight">
                                        {modulo.titulo}
                                    </h2>
                                    <p className="text-blue-100 text-sm mt-1">{modulo.resumen}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={cerrar}
                                aria-label="Cerrar ayuda"
                                className="text-white/80 hover:text-white text-2xl leading-none flex-shrink-0"
                                data-testid="ayuda-modulo-cerrar"
                            >
                                ×
                            </button>
                        </div>

                        {/* Body con scroll */}
                        <div className="overflow-y-auto p-6 space-y-5 text-slate-700">
                            <Seccion titulo="¿Que es?" emoji="📖">
                                <p className="leading-relaxed">{modulo.queEs}</p>
                            </Seccion>

                            {modulo.conceptos && modulo.conceptos.length > 0 && (
                                <Seccion titulo="Conceptos clave" emoji="🔑">
                                    <dl className="space-y-3">
                                        {modulo.conceptos.map((c, i) => (
                                            <div key={i} className="bg-slate-50 rounded-lg p-3 border border-slate-100">
                                                <dt className="font-bold text-slate-900">{c.termino}</dt>
                                                <dd className="text-sm text-slate-600 mt-1 leading-relaxed">
                                                    {c.definicion}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </Seccion>
                            )}

                            {modulo.comoUsar && modulo.comoUsar.length > 0 && (
                                <Seccion titulo="Como se usa" emoji="🚀">
                                    <ol className="space-y-2 list-decimal list-inside marker:text-blue-600 marker:font-bold">
                                        {modulo.comoUsar.map((paso, i) => (
                                            <li key={i} className="leading-relaxed pl-1">
                                                {paso}
                                            </li>
                                        ))}
                                    </ol>
                                </Seccion>
                            )}

                            {modulo.errores && modulo.errores.length > 0 && (
                                <Seccion titulo="Errores comunes" emoji="⚠️">
                                    <ul className="space-y-3">
                                        {modulo.errores.map((e, i) => (
                                            <li
                                                key={i}
                                                className="bg-amber-50 border border-amber-200 rounded-lg p-3"
                                            >
                                                <p className="font-bold text-amber-900 text-sm">
                                                    {e.problema}
                                                </p>
                                                <p className="text-sm text-amber-800 mt-1 leading-relaxed">
                                                    {e.solucion}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                </Seccion>
                            )}

                            {modulo.tip && (
                                <div className="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
                                    <p className="text-emerald-900 text-sm font-semibold flex items-start gap-2">
                                        <span aria-hidden="true">💡</span>
                                        <span><strong>Tip:</strong> {modulo.tip}</span>
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Footer */}
                        <div className="border-t border-slate-200 px-6 py-3 bg-slate-50 flex justify-end">
                            <button
                                type="button"
                                onClick={cerrar}
                                className="px-5 py-2 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400"
                            >
                                Entendido
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
};

const Seccion = ({ titulo, emoji, children }) => (
    <section>
        <h3 className="text-base font-black text-slate-900 mb-2 flex items-center gap-2">
            <span aria-hidden="true">{emoji}</span>
            <span>{titulo}</span>
        </h3>
        <div className="text-sm">{children}</div>
    </section>
);

export default AyudaModulo;
