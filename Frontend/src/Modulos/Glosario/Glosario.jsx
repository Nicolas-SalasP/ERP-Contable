import React, { useState } from 'react';
import { listarModulos, buscarModulos } from '../../Utilidades/glosario';

const Glosario = () => {
    const [busqueda, setBusqueda] = useState('');
    const [seleccionado, setSeleccionado] = useState(null);

    const modulosVisibles = busqueda.trim() ? buscarModulos(busqueda) : listarModulos();

    return (
        <div className="max-w-[95rem] mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 animate-fade-in pb-20">
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">
                    📚 Glosario del Sistema
                </h1>
                <p className="text-slate-500 font-medium mt-1">
                    Aprende los conceptos contables y como usar cada modulo del ERP.
                </p>
            </div>

            {/* Buscador */}
            <div className="mb-6">
                <div className="relative max-w-2xl">
                    <input
                        type="text"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Buscar concepto, modulo o termino..."
                        className="w-full bg-white border border-slate-300 rounded-xl pl-12 pr-4 py-3 text-slate-800 placeholder:text-slate-400 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                    />
                    <svg
                        className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <p className="text-xs text-slate-500 mt-2">
                    {modulosVisibles.length} {modulosVisibles.length === 1 ? 'modulo' : 'modulos'}
                </p>
            </div>

            {/* Si hay uno seleccionado, mostrar detalle a pantalla completa */}
            {seleccionado ? (
                <DetalleModulo modulo={seleccionado} onVolver={() => setSeleccionado(null)} />
            ) : (
                <GridModulos modulos={modulosVisibles} onSeleccionar={setSeleccionado} />
            )}
        </div>
    );
};

const GridModulos = ({ modulos, onSeleccionar }) => {
    if (modulos.length === 0) {
        return (
            <div className="bg-white p-12 rounded-2xl border border-slate-200 text-center">
                <p className="text-slate-500">No se encontraron modulos que coincidan con la busqueda.</p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {modulos.map((m) => (
                <button
                    key={m.id}
                    type="button"
                    onClick={() => onSeleccionar(m)}
                    className="text-left bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:border-indigo-400 hover:shadow-md transition-all group focus:outline-none focus:ring-2 focus:ring-indigo-400"
                >
                    <div className="flex items-start gap-3">
                        <div className="text-3xl" aria-hidden="true">
                            {m.icono}
                        </div>
                        <div className="flex-1">
                            <h3 className="font-black text-slate-900 group-hover:text-indigo-600 transition-colors">
                                {m.titulo}
                            </h3>
                            <p className="text-sm text-slate-500 mt-1 leading-relaxed">
                                {m.resumen}
                            </p>
                        </div>
                    </div>
                </button>
            ))}
        </div>
    );
};

const DetalleModulo = ({ modulo, onVolver }) => (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="bg-gradient-to-br from-blue-600 to-indigo-700 text-white px-6 py-5 flex items-start justify-between gap-4">
            <div className="flex items-start gap-4">
                <div className="text-5xl leading-none" aria-hidden="true">
                    {modulo.icono}
                </div>
                <div>
                    <h2 className="text-3xl font-black tracking-tight">{modulo.titulo}</h2>
                    <p className="text-blue-100 mt-1">{modulo.resumen}</p>
                </div>
            </div>
            <button
                type="button"
                onClick={onVolver}
                className="text-white/80 hover:text-white font-bold text-sm flex items-center gap-1 flex-shrink-0"
            >
                ← Volver al listado
            </button>
        </div>

        <div className="p-6 space-y-6 text-slate-700">
            <Seccion titulo="¿Que es?" emoji="📖">
                <p className="leading-relaxed">{modulo.queEs}</p>
            </Seccion>

            {modulo.conceptos && modulo.conceptos.length > 0 && (
                <Seccion titulo="Conceptos clave" emoji="🔑">
                    <dl className="grid grid-cols-1 md:grid-cols-2 gap-3">
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
                            <li key={i} className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                <p className="font-bold text-amber-900">{e.problema}</p>
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
                    <p className="text-emerald-900 font-semibold flex items-start gap-2">
                        <span aria-hidden="true">💡</span>
                        <span><strong>Tip:</strong> {modulo.tip}</span>
                    </p>
                </div>
            )}
        </div>
    </div>
);

const Seccion = ({ titulo, emoji, children }) => (
    <section>
        <h3 className="text-lg font-black text-slate-900 mb-3 flex items-center gap-2">
            <span aria-hidden="true">{emoji}</span>
            <span>{titulo}</span>
        </h3>
        <div>{children}</div>
    </section>
);

export default Glosario;
