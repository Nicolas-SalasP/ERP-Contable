import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../Configuracion/api';
import ModalGenerico from '../../Componentes/ModalGenerico';
import Swal from 'sweetalert2';

const GestionCotizaciones = () => {
    const [cotizaciones, setCotizaciones] = useState([]);
    const [loading, setLoading] = useState(true);

    const [confirmarAccion, setConfirmarAccion] = useState({
        show: false,
        id: null,
        nuevoEstado: '',
        tipo: 'info'
    });

    const [notificacion, setNotificacion] = useState({
        show: false,
        title: '',
        message: '',
        type: 'info'
    });

    const formatearFecha = (fechaRaw) => {
        if (!fechaRaw) return '-';
        const fecha = new Date(fechaRaw);
        return fecha.toLocaleDateString('es-CL', { timeZone: 'UTC' });
    };

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
        fechaInicio: '',
        fechaFin: ''
    });

    const fetchCotizaciones = async () => {
        setLoading(true);
        try {
            const res = await api.get('/cotizaciones');
            if (res.success) setCotizaciones(res.data);
        } catch (err) {
            console.error("Error al cargar cotizaciones:", err);
            Swal.fire('Error', 'No se pudieron cargar las cotizaciones', 'error');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchCotizaciones();
    }, []);

    const handleCambiarEstado = async () => {
        try {
            const res = await api.put(`/cotizaciones/${confirmarAccion.id}/estado`, {
                estado: confirmarAccion.nuevoEstado
            });
            if (res.success) {
                setConfirmarAccion({ ...confirmarAccion, show: false });
                setNotificacion({
                    show: true,
                    title: 'Operación Exitosa',
                    message: `La cotización #${String(confirmarAccion.id).padStart(5, '0')} ha sido marcada como ${confirmarAccion.nuevoEstado}.`,
                    type: 'success'
                });
                fetchCotizaciones();
            }
        } catch (error) {
            setConfirmarAccion({ ...confirmarAccion, show: false });
            Swal.fire('Error', 'No se pudo actualizar el estado de la cotización.', 'error');
        }
    };

    const descargarPDF = async (id, nombreCliente) => {
        try {
            let tokenRaw = localStorage.getItem('token') ||
                localStorage.getItem('erp_token') ||
                sessionStorage.getItem('erp_token');

            if (!tokenRaw) {
                Swal.fire('Sesión Expirada', 'No se encontró una sesión activa. Por favor, reingrese al sistema.', 'warning');
                return;
            }

            const token = tokenRaw.startsWith('"') ? JSON.parse(tokenRaw) : tokenRaw;

            const baseURL = api.defaults?.baseURL || window.location.origin + '/api';

            const response = await fetch(`${baseURL}/cotizaciones/pdf/${id}`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/pdf'
                }
            });

            if (response.status === 401) throw new Error('Tu sesión ha expirado o el token es inválido.');
            if (!response.ok) throw new Error('El servidor no pudo generar el archivo PDF.');

            const clienteSeguro = nombreCliente ? nombreCliente.replace(/[^a-zA-Z0-9\s\-_]/g, '').trim() : 'Cliente';
            const nombreArchivo = `Cotizacion_${id} - ${clienteSeguro}.pdf`;

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', nombreArchivo);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

        } catch (error) {
            Swal.fire('Error de Descarga', error.message, 'error');
        }
    };

    const cotizacionesFiltradas = cotizaciones.filter(c => {
        const matchBusqueda =
            c.nombre_cliente?.toLowerCase().includes(filtros.busqueda.toLowerCase()) ||
            c.id?.toString().includes(filtros.busqueda) ||
            c.folio?.toString().includes(filtros.busqueda);

        const matchEstado = filtros.estado === '' || c.estado_nombre === filtros.estado;

        const matchFecha = (!filtros.fechaInicio || c.fecha_emision >= filtros.fechaInicio) &&
            (!filtros.fechaFin || c.fecha_emision <= filtros.fechaFin);

        return matchBusqueda && matchEstado && matchFecha;
    });

    const getEstadoStyle = (estado) => {
        switch (estado?.toUpperCase()) {
            case 'ACEPTADA':
            case 'APROBADA': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            case 'ANULADA':
            case 'RECHAZADA': return 'bg-red-50 text-red-700 border-red-200';
            default: return 'bg-amber-50 text-amber-700 border-amber-200';
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6">

            <ModalGenerico
                isOpen={notificacion.show}
                onClose={() => setNotificacion({ ...notificacion, show: false })}
                title={notificacion.title}
                message={notificacion.message}
                type={notificacion.type}
            />

            <ModalGenerico
                isOpen={confirmarAccion.show}
                onClose={() => setConfirmarAccion({ ...confirmarAccion, show: false })}
                onConfirm={handleCambiarEstado}
                title={`Confirmar ${confirmarAccion.nuevoEstado}`}
                message={`¿Estás seguro de que deseas marcar la cotización #${String(confirmarAccion.id).padStart(5, '0')} como ${confirmarAccion.nuevoEstado}?`}
                type={confirmarAccion.tipo}
                showCancel={true}
                confirmText="Sí, Confirmar"
                cancelText="Volver"
            />

            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h2 className="text-2xl md:text-3xl font-bold text-slate-900">Historial de Cotizaciones</h2>
                    <p className="text-slate-500 text-sm mt-1">Gestiona y filtra tus propuestas comerciales</p>
                </div>
                <Link to="/cotizaciones/nueva" className="w-full sm:w-auto text-center bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-bold shadow hover:bg-emerald-700 transition-all active:scale-95">
                    + Nueva Cotización
                </Link>
            </div>

            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-1">Búsqueda rápida</label>
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input
                            type="text"
                            placeholder="Folio o Cliente..."
                            className="w-full !pl-10 pr-3 py-2 border border-slate-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                            value={filtros.busqueda}
                            onChange={(e) => setFiltros({ ...filtros, busqueda: e.target.value })}
                        />
                    </div>
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-1">Filtrar Estado</label>
                    <select
                        className="w-full border border-slate-200 p-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer bg-white"
                        value={filtros.estado}
                        onChange={(e) => setFiltros({ ...filtros, estado: e.target.value })}
                    >
                        <option value="">Todos los estados</option>
                        <option value="PENDIENTE">Pendientes</option>
                        <option value="ACEPTADA">Aceptadas / Aprobadas</option>
                        <option value="ANULADA">Anuladas / Rechazadas</option>
                    </select>
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-1">Desde</label>
                    <input
                        type="date"
                        className="w-full border border-slate-200 p-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        value={filtros.fechaInicio}
                        onChange={(e) => setFiltros({ ...filtros, fechaInicio: e.target.value })}
                    />
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-1">Hasta</label>
                    <input
                        type="date"
                        className="w-full border border-slate-200 p-2 rounded-lg text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        value={filtros.fechaFin}
                        onChange={(e) => setFiltros({ ...filtros, fechaFin: e.target.value })}
                    />
                </div>
            </div>

            {loading ? (
                <div className="p-10 text-center text-slate-400 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mx-auto mb-3"></div>
                    <p className="font-medium">Cargando cotizaciones...</p>
                </div>
            ) : cotizacionesFiltradas.length === 0 ? (
                <div className="p-10 text-center text-slate-400 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <svg className="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <p className="font-medium">No se encontraron resultados para tu búsqueda.</p>
                </div>
            ) : (
                <>
                    <div className="grid grid-cols-1 gap-4 md:hidden">
                        {cotizacionesFiltradas.map(c => (
                            <div key={c.id} className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm relative overflow-hidden">
                                <div className={`absolute top-0 left-0 w-1.5 h-full ${c.estado_nombre === 'ACEPTADA' ? 'bg-emerald-500' : c.estado_nombre === 'ANULADA' ? 'bg-red-400' : 'bg-amber-400'}`}></div>

                                <div className="flex justify-between items-start mb-2 pl-2">
                                    <div>
                                        <div className="text-xs font-bold text-slate-400 font-mono mb-0.5">#{c.id.toString().padStart(5, '0')}</div>
                                        <h3 className="font-bold text-slate-800 leading-tight">{c.nombre_cliente}</h3>
                                    </div>
                                    <span className={`inline-block px-2.5 py-0.5 text-[10px] font-bold rounded-full uppercase border ${getEstadoStyle(c.estado_nombre)}`}>
                                        {c.estado_nombre}
                                    </span>
                                </div>

                                <div className="pl-2 space-y-1.5 mb-4">
                                    <div className="text-sm text-slate-600 flex items-center gap-2">
                                        <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        {c.fecha_emision}
                                    </div>
                                    <div className="text-lg font-black text-slate-800 flex items-center gap-2">
                                        <span className="text-emerald-500 font-bold">$</span>
                                        {parseFloat(c.total).toLocaleString('es-CL')}
                                    </div>
                                </div>

                                <div className="flex gap-2 pt-3 border-t border-slate-100 pl-2">
                                    {c.estado_nombre === 'PENDIENTE' && (
                                        <>
                                            <button onClick={() => setConfirmarAccion({ show: true, id: c.id, nuevoEstado: 'ACEPTADA', tipo: 'success' })} className="flex-1 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 font-bold text-xs py-2 rounded-lg transition-colors border border-emerald-100">
                                                Aceptar
                                            </button>
                                            <button onClick={() => setConfirmarAccion({ show: true, id: c.id, nuevoEstado: 'ANULADA', tipo: 'danger' })} className="flex-1 bg-red-50 text-red-500 hover:bg-red-100 font-bold text-xs py-2 rounded-lg transition-colors border border-red-100">
                                                Anular
                                            </button>
                                        </>
                                    )}
                                    <button onClick={() => descargarPDF(c.id, c.nombre_cliente)} className={`flex-1 bg-slate-50 text-slate-600 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 font-bold text-xs py-2 rounded-lg transition-colors border border-slate-200 flex items-center justify-center gap-2`}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        PDF
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="hidden md:block bg-white shadow-sm rounded-xl overflow-hidden border border-slate-200">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider">Folio</th>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider">Cliente</th>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-center">Fecha</th>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-right">Total</th>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-center">Estado</th>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {cotizacionesFiltradas.map(c => {
                                    const nombreEstado = c.estado?.nombre || c.estado_nombre || 'PENDIENTE';

                                    return (
                                        <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="p-4 text-slate-500 font-mono text-sm">#{c.id.toString().padStart(5, '0')}</td>
                                            <td className="p-4 text-slate-900 font-bold">{c.nombre_cliente}</td>
                                            <td className="p-4 text-slate-600 text-sm text-center">
                                                {formatearFecha(c.fecha_emision)}
                                            </td>
                                            <td className="p-4 text-slate-900 font-black text-right">${parseFloat(c.total).toLocaleString('es-CL')}</td>
                                            <td className="p-4 text-center">
                                                <span className={`inline-block px-3 py-1 rounded-full text-[10px] font-black border uppercase ${getEstadoStyle(nombreEstado)}`}>
                                                    {nombreEstado}
                                                </span>
                                            </td>
                                            <td className="p-4 text-right">
                                                <div className="flex justify-end gap-2 items-center">
                                                    {nombreEstado === 'PENDIENTE' && (
                                                        <>
                                                            <button
                                                                onClick={() => setConfirmarAccion({ show: true, id: c.id, nuevoEstado: 'ACEPTADA', tipo: 'success' })}
                                                                className="p-2 bg-emerald-50 text-emerald-600 border border-emerald-100 hover:bg-emerald-100 hover:text-emerald-700 rounded-lg transition-all"
                                                                title="Aceptar Cotización"
                                                            >
                                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" /></svg>
                                                            </button>
                                                            <button
                                                                onClick={() => setConfirmarAccion({ show: true, id: c.id, nuevoEstado: 'ANULADA', tipo: 'danger' })}
                                                                className="p-2 bg-red-50 text-red-500 border border-red-100 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all"
                                                                title="Anular Cotización"
                                                            >
                                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                                                            </button>
                                                        </>
                                                    )}
                                                    <button
                                                        onClick={() => descargarPDF(c.id, c.nombre_cliente)}
                                                        className="flex items-center gap-1.5 px-3 py-2 bg-slate-50 text-slate-600 border border-slate-200 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 rounded-lg transition-all font-bold text-xs"
                                                        title="Descargar PDF"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                                        PDF
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </div>
    );
};

export default GestionCotizaciones;