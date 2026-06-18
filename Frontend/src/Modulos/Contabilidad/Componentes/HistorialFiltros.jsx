import React from 'react';
import BotonAccion from '../../../Componentes/BotonAccion';
import { Search, Filter } from 'lucide-react';

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
                <div className="relative sm:col-span-2">
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                        Proveedor
                    </label>
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Search size={20} strokeWidth={1.75} className="text-slate-400" />
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
                        icono={<Filter size={20} strokeWidth={1.75} />}
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
