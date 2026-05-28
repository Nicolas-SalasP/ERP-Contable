import React from 'react';
import BotonAccion from '../../../Componentes/BotonAccion';

const HistorialFiltros = ({
    panelRef,
    busqueda,
    filtroNumero,
    filtroEstado,
    onBusquedaChange,
    onFiltroNumeroChange,
    onFiltroEstadoChange,
    onEjecutarBusqueda,
    onFocusBusqueda,
    sugerencias,
    mostrarSugerencias,
    onSeleccionarProveedor,
    loading,
}) => {
    return (
        <div
            className="bg-white p-5 rounded-xl shadow-sm border border-slate-200 mb-8"
            ref={panelRef}
        >
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                {/* Proveedor con autocomplete */}
                <div className="relative sm:col-span-2">
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                        Proveedor
                    </label>
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            type="text"
                            className="w-full !pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700 text-sm"
                            placeholder="RUT o Razón Social..."
                            value={busqueda}
                            onChange={onBusquedaChange}
                            onFocus={onFocusBusqueda}
                            onKeyDown={(e) => e.key === 'Enter' && onEjecutarBusqueda(true)}
                        />
                    </div>

                    {mostrarSugerencias && sugerencias.length > 0 && (
                        <div className="absolute top-full left-0 w-full bg-white border border-slate-200 mt-2 rounded-xl shadow-2xl max-h-60 overflow-y-auto z-50 custom-scrollbar">
                            {sugerencias.map((p) => (
                                <div
                                    key={p.id}
                                    onClick={() => onSeleccionarProveedor(p)}
                                    className="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-0 border-slate-100 transition-colors group"
                                >
                                    <p className="font-bold text-slate-800 text-sm group-hover:text-blue-700">
                                        {p.razon_social}
                                    </p>
                                    <span className="text-xs text-slate-500 font-mono">{p.rut}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Numero de documento */}
                <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                        N° Documento
                    </label>
                    <input
                        type="text"
                        className="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700 text-sm"
                        placeholder="Ej: 12345"
                        value={filtroNumero}
                        onChange={(e) => onFiltroNumeroChange(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && onEjecutarBusqueda(true)}
                    />
                </div>

                {/* Estado + boton buscar */}
                <div className="flex gap-2">
                    <div className="flex-1">
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                            Estado
                        </label>
                        <select
                            className="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700 text-sm"
                            value={filtroEstado}
                            onChange={(e) => onFiltroEstadoChange(e.target.value)}
                        >
                            <option value="">Todos</option>
                            <option value="REGISTRADA">Pendientes</option>
                            <option value="PAGADA">Pagadas</option>
                            <option value="ANULADA">Anuladas</option>
                        </select>
                    </div>
                    <BotonAccion
                        onClick={() => onEjecutarBusqueda(true)}
                        cargando={loading}
                        color="slate"
                        tamano="md"
                        textoCargando=""
                        icono={
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                                />
                            </svg>
                        }
                        className="mt-6 h-[42px]"
                        title="Buscar"
                    >
                        {''}
                    </BotonAccion>
                </div>
            </div>
        </div>
    );
};

export default HistorialFiltros;
