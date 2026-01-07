import React, { useState, useEffect, useRef } from 'react';
import { api } from '../../../Configuracion/api'; 

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString); 
    return date.toLocaleDateString('es-CL', { timeZone: 'UTC' });
};

const ModalAsiento = ({ isOpen, onClose, data, loading }) => {
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 bg-slate-900 bg-opacity-80 flex items-center justify-center z-50 p-4 backdrop-blur-sm animate-fade-in">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh] border border-slate-200">
                <div className="bg-slate-900 text-white px-4 md:px-8 py-5 flex justify-between items-center">
                    <div>
                        <div className="flex items-center gap-3">
                             <span className="bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">Contabilidad</span>
                             {loading && <span className="text-xs text-blue-300 animate-pulse"><i className="fas fa-circle-notch fa-spin mr-1"></i> Cargando...</span>}
                        </div>
                        <h3 className="text-lg md:text-xl font-bold mt-1">
                            {loading ? 'Cargando Asiento...' : `Asiento Contable N° ${data?.cabecera?.numero_asiento || '---'}`}
                        </h3>
                    </div>
                    <button onClick={onClose} className="text-slate-400 hover:text-white transition-colors p-2 rounded-full hover:bg-slate-800">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                {loading ? (
                    <div className="p-16 text-center text-slate-400"><p>Recuperando información contable...</p></div>
                ) : (
                    data && (
                        <>
                            <div className="bg-slate-50 px-4 md:px-8 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div className="flex-1">
                                    <p className="text-xs text-slate-500 font-bold uppercase tracking-wide">Glosa / Descripción</p>
                                    <p className="text-slate-800 italic font-medium mt-1 text-sm md:text-base">"{data.cabecera.glosa}"</p>
                                </div>
                                <div className="text-left md:text-right w-full md:w-auto">
                                    <p className="text-xs text-slate-500 font-bold uppercase tracking-wide">Fecha Contable</p>
                                    <p className="text-slate-800 font-mono font-bold mt-1">{formatDate(data.cabecera.fecha_contable)}</p>
                                </div>
                            </div>
                            <div className="flex-1 overflow-auto">
                                <table className="min-w-full divide-y divide-slate-100">
                                    <thead className="bg-white sticky top-0 z-10 shadow-sm">
                                        <tr>
                                            <th className="px-4 md:px-8 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Cuenta Contable</th>
                                            <th className="px-4 md:px-8 py-3 text-right text-xs font-bold text-emerald-600 uppercase tracking-wider w-32 md:w-40 whitespace-nowrap">Debe</th>
                                            <th className="px-4 md:px-8 py-3 text-right text-xs font-bold text-red-600 uppercase tracking-wider w-32 md:w-40 whitespace-nowrap">Haber</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-slate-50 text-sm">
                                        {data.detalles.map((det, idx) => (
                                            <tr key={idx} className="hover:bg-blue-50/50 transition-colors">
                                                <td className="px-4 md:px-8 py-4">
                                                    <div className="font-bold text-slate-800">{det.nombre_cuenta}</div>
                                                    <div className="text-xs text-slate-500 font-mono mt-1 bg-slate-100 px-2 py-0.5 rounded inline-block border border-slate-200">{det.cuenta_contable}</div>
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
                                            <td className="px-4 md:px-8 py-4 text-right uppercase text-xs tracking-wider text-slate-500">Totales</td>
                                            <td className="px-4 md:px-8 py-4 text-right text-emerald-700 bg-emerald-100/50 whitespace-nowrap">
                                                {formatCurrency(data.detalles.reduce((acc, i) => acc + parseFloat(i.debe), 0))}
                                            </td>
                                            <td className="px-4 md:px-8 py-4 text-right text-red-700 bg-red-100/50 whitespace-nowrap">
                                                {formatCurrency(data.detalles.reduce((acc, i) => acc + parseFloat(i.haber), 0))}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </>
                    )
                )}
                <div className="p-4 bg-gray-50 border-t border-slate-200 flex justify-end">
                    <button onClick={onClose} className="px-6 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium transition-colors shadow-lg text-sm uppercase tracking-wide w-full md:w-auto">Cerrar</button>
                </div>
            </div>
        </div>
    );
};

const HistorialFacturas = () => {
    const [busqueda, setBusqueda] = useState('');
    const [filtroNumero, setFiltroNumero] = useState('');
    const [filtroEstado, setFiltroEstado] = useState('');

    const [facturas, setFacturas] = useState([]);
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);

    const [pagination, setPagination] = useState({ page: 1, limit: 10, total: 0, totalPages: 0 });

    const [listaProveedores, setListaProveedores] = useState([]);
    const [sugerencias, setSugerencias] = useState([]);
    const [mostrarSugerencias, setMostrarSugerencias] = useState(false);
    const searchRef = useRef(null);

    const [modalOpen, setModalOpen] = useState(false);
    const [asientoData, setAsientoData] = useState(null);
    const [loadingAsiento, setLoadingAsiento] = useState(false);

    useEffect(() => {
        api.get('/proveedores')
            .then(res => { if (res.success) setListaProveedores(res.data); })
            .catch(err => console.error("Error", err));

        const handleClickOutside = (event) => {
            if (searchRef.current && !searchRef.current.contains(event.target)) setMostrarSugerencias(false);
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    useEffect(() => {
        ejecutarBusqueda();
    }, [pagination.page]); 

    const handleBusquedaChange = (e) => {
        const termino = e.target.value;
        setBusqueda(termino);
        if (termino.length > 0) {
            const matches = listaProveedores.filter(p => 
                p.razon_social.toLowerCase().includes(termino.toLowerCase()) ||
                (p.rut && p.rut.toLowerCase().includes(termino.toLowerCase())) ||
                p.codigo_interno.toString().includes(termino)
            );
            setSugerencias(matches);
            setMostrarSugerencias(true);
        } else {
            setSugerencias([]);
            setMostrarSugerencias(false);
        }
    };

    const ejecutarBusqueda = async (resetPage = false) => {
        setLoading(true);
        setSearched(true);
        setMostrarSugerencias(false);

        if (resetPage) {
            setPagination(prev => ({ ...prev, page: 1 }));
        }

        const params = new URLSearchParams();
        if(busqueda) params.append('search', busqueda); 
        
        if(filtroNumero) params.append('num', filtroNumero);
        if(filtroEstado) params.append('estado', filtroEstado);

        params.append('page', resetPage ? 1 : pagination.page);
        params.append('limit', pagination.limit);

        try {
            const res = await api.get(`/facturas/historial?${params.toString()}`);
            if(res.success) {
                setFacturas(res.data);
                setPagination(prev => ({
                    ...prev,
                    total: res.pagination.total,
                    totalPages: res.pagination.totalPages
                }));
            } else {
                setFacturas([]);
            }
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const seleccionarProveedor = (prov) => {
        setBusqueda(prov.razon_social);
        setMostrarSugerencias(false);
    };

    const verAsientoContable = async (facturaId) => {
        setModalOpen(true);
        setLoadingAsiento(true);
        setAsientoData(null); 
        try {
            const res = await api.get(`/facturas/${facturaId}/asiento`);
            if(res.success) setAsientoData(res.data);
            else { alert('No se pudo cargar la información del asiento.'); setModalOpen(false); }
        } catch (error) {
            console.error(error);
            alert('Error al obtener el asiento contable.');
            setModalOpen(false);
        } finally {
            setLoadingAsiento(false);
        }
    };

    return (
        <div className="max-w-7xl mx-auto font-sans text-slate-800">
            <ModalAsiento isOpen={modalOpen} onClose={() => setModalOpen(false)} data={asientoData} loading={loadingAsiento} />

            <div className="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">Historial de Compras</h1>
                    <p className="text-slate-500 text-sm mt-1">Cuenta Corriente y Auditoría Contable</p>
                </div>
            </div>

            <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-8" ref={searchRef}>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    
                    <div className="relative md:col-span-2">
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Proveedor</label>
                        <input 
                            type="text" 
                            className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700"
                            placeholder="RUT o Razón Social..."
                            value={busqueda}
                            onChange={handleBusquedaChange}
                            onFocus={() => { if(busqueda) setMostrarSugerencias(true); }}
                            onKeyDown={(e) => e.key === 'Enter' && ejecutarBusqueda(true)}
                        />
                        {mostrarSugerencias && sugerencias.length > 0 && (
                            <div className="absolute top-full left-0 w-full bg-white border border-slate-200 mt-2 rounded-xl shadow-2xl max-h-60 overflow-y-auto z-50">
                                {sugerencias.map(p => (
                                    <div key={p.id} onClick={() => seleccionarProveedor(p)} className="p-4 hover:bg-blue-50 cursor-pointer border-b last:border-0 border-slate-100 transition-colors group">
                                        <p className="font-bold text-slate-800 group-hover:text-blue-700">{p.razon_social}</p>
                                        <div className="flex justify-between items-center mt-1">
                                            <span className="text-sm text-slate-500 font-mono">{p.rut}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">N° Documento</label>
                        <input 
                            type="text" 
                            className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700"
                            placeholder="Ej: 12345"
                            value={filtroNumero}
                            onChange={(e) => setFiltroNumero(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && ejecutarBusqueda(true)}
                        />
                    </div>

                    <div className="flex gap-2">
                        <div className="flex-1">
                            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Estado</label>
                            <select 
                                className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700"
                                value={filtroEstado}
                                onChange={(e) => setFiltroEstado(e.target.value)}
                            >
                                <option value="">Todos</option>
                                <option value="REGISTRADA">Pendientes</option>
                                <option value="PAGADO">Pagadas</option>
                                <option value="ANULADA">Anuladas</option>
                            </select>
                        </div>
                        <button 
                            onClick={() => ejecutarBusqueda(true)}
                            disabled={loading}
                            className="px-6 py-3 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 shadow-lg disabled:opacity-70 transition-all flex items-center justify-center mb-[1px]"
                        >
                            {loading ? <i className="fas fa-circle-notch fa-spin"></i> : 'Filtrar'}
                        </button>
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden min-h-[400px] flex flex-col justify-between">
                <div>
                    {!searched && facturas.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-[300px] text-slate-400">
                            <svg className="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <p className="text-lg font-medium">Usa los filtros para buscar facturas.</p>
                        </div>
                    ) : facturas.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-[300px] text-slate-400">
                            <svg className="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <p className="text-lg font-medium">No se encontraron documentos.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Fecha</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Proveedor</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Documento</th>
                                        <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Asiento</th>
                                        <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Monto Bruto</th>
                                        <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Estado</th>
                                        <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-slate-50 text-sm">
                                    {facturas.map((fac) => (
                                        <tr key={fac.id} className="hover:bg-blue-50/30 transition-colors group">
                                            <td className="px-6 py-4 whitespace-nowrap text-slate-600 font-mono text-xs">{formatDate(fac.fecha_emision)}</td>
                                            
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-slate-800">{fac.nombre_proveedor}</div>
                                                <div className="text-xs text-slate-400 mt-0.5">{fac.rut_proveedor}</div>
                                            </td>

                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="font-mono text-slate-600 bg-slate-100 px-2 py-1 rounded text-xs border border-slate-200 font-bold">N° {fac.numero_factura}</span>
                                            </td>
                                            
                                            <td className="px-6 py-4 text-center whitespace-nowrap">
                                                {fac.codigo_asiento ? (
                                                    <span className="text-blue-600 font-mono font-bold text-xs">{fac.codigo_asiento}</span>
                                                ) : (
                                                    <span className="text-slate-300 text-xs">-</span>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 whitespace-nowrap text-right font-bold text-slate-800 text-base">
                                                {formatCurrency(fac.monto_bruto)}
                                            </td>

                                            <td className="px-6 py-4 whitespace-nowrap text-center">
                                                {fac.estado === 'REGISTRADA' ? (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                        Vigente
                                                    </span>
                                                ) : fac.estado === 'ANULADA' ? (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                                                        Anulada
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-800 border border-gray-200">
                                                        {fac.estado}
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 whitespace-nowrap text-center">
                                                <button onClick={() => verAsientoContable(fac.id)} className="text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 p-2 rounded-lg transition-all group-hover:scale-110" title="Ver Asiento Contable">
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
                
                {pagination.totalPages > 1 && (
                    <div className="bg-slate-50 px-6 py-3 border-t border-slate-200 flex items-center justify-between">
                        <button 
                            disabled={pagination.page === 1}
                            onClick={() => setPagination(prev => ({...prev, page: prev.page - 1}))}
                            className="px-3 py-1 bg-white border border-slate-300 rounded text-sm disabled:opacity-50"
                        >
                            Anterior
                        </button>
                        <span className="text-sm text-slate-600">Página {pagination.page} de {pagination.totalPages}</span>
                        <button 
                            disabled={pagination.page === pagination.totalPages}
                            onClick={() => setPagination(prev => ({...prev, page: prev.page + 1}))}
                            className="px-3 py-1 bg-white border border-slate-300 rounded text-sm disabled:opacity-50"
                        >
                            Siguiente
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};

export default HistorialFacturas;