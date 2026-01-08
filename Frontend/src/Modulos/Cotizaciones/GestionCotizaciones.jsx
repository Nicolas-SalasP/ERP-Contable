import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../Configuracion/api';
import ModalGenerico from '../../Componentes/ModalGenerico';

const GestionCotizaciones = () => {
    const [cotizaciones, setCotizaciones] = useState([]);
    const [loading, setLoading] = useState(true);

    // Configuración para ModalGenerico
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

    // ESTADOS PARA FILTROS
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
                    message: `La cotización #${confirmarAccion.id} ha sido marcada como ${confirmarAccion.nuevoEstado}.`,
                    type: 'success'
                });
                fetchCotizaciones();
            }
        } catch (error) {
            setNotificacion({
                show: true,
                title: 'Error',
                message: 'No se pudo actualizar el estado de la cotización.',
                type: 'danger'
            });
        }
    };

    const descargarPDF = async (id, nombreCliente) => {
        try {
            let token = localStorage.getItem('erp_token');

            if (!token) {
                throw new Error('No se encontró una sesión activa.');
            }

            token = token.replace(/^"(.*)"$/, '$1');

            const baseURL = api.defaults?.baseURL || 'http://localhost/ERP-Contable/Backend/Public/api';
            const response = await fetch(`${baseURL}/cotizaciones/pdf/${id}`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/pdf'
                }
            });

            if (!response.ok) {
                throw new Error('El servidor no pudo generar el archivo.');
            }
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
            setNotificacion({
                show: true,
                title: 'Error de Descarga',
                message: error.message,
                type: 'danger'
            });
        }
    };

    // LÓGICA DE FILTRADO DINÁMICO
    const cotizacionesFiltradas = cotizaciones.filter(c => {
        const matchBusqueda =
            c.nombre_cliente.toLowerCase().includes(filtros.busqueda.toLowerCase()) ||
            c.id.toString().includes(filtros.busqueda);

        const matchEstado = filtros.estado === '' || c.estado_nombre === filtros.estado;

        const matchFecha = (!filtros.fechaInicio || c.fecha_emision >= filtros.fechaInicio) &&
            (!filtros.fechaFin || c.fecha_emision <= filtros.fechaFin);

        return matchBusqueda && matchEstado && matchFecha;
    });

    const getEstadoStyle = (estado) => {
        switch (estado?.toUpperCase()) {
            case 'ACEPTADA': return 'bg-emerald-100 text-emerald-700 border-emerald-200';
            case 'ANULADA': return 'bg-red-100 text-red-800 border-red-200';
            default: return 'bg-amber-100 text-amber-700 border-amber-200';
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-6">
            {/* Modal de Notificación */}
            <ModalGenerico
                isOpen={notificacion.show}
                onClose={() => setNotificacion({ ...notificacion, show: false })}
                title={notificacion.title}
                message={notificacion.message}
                type={notificacion.type}
            />

            {/* Modal de Confirmación */}
            <ModalGenerico
                isOpen={confirmarAccion.show}
                onClose={() => setConfirmarAccion({ ...confirmarAccion, show: false })}
                onConfirm={handleCambiarEstado}
                title={`Confirmar ${confirmarAccion.nuevoEstado}`}
                message={`¿Estás seguro de que deseas marcar la cotización #${confirmarAccion.id} como ${confirmarAccion.nuevoEstado}?`}
                type={confirmarAccion.tipo}
                showCancel={true}
                confirmText="Confirmar"
                cancelText="Volver"
            />

            <div className="flex justify-between items-center mb-8">
                <div>
                    <h2 className="text-3xl font-bold text-slate-900">Historial de Cotizaciones</h2>
                    <p className="text-slate-500 text-sm">Gestiona y filtra tus propuestas comerciales</p>
                </div>
                <Link to="/cotizaciones/nueva" className="bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition-all active:scale-95">
                    + Nueva Cotización
                </Link>
            </div>

            {/* BARRA DE FILTROS */}
            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-1">Búsqueda rápida</label>
                    <input
                        type="text"
                        placeholder="Folio o Cliente..."
                        className="w-full border border-slate-200 p-2 rounded-lg text-sm outline-none focus:border-emerald-500"
                        value={filtros.busqueda}
                        onChange={(e) => setFiltros({ ...filtros, busqueda: e.target.value })}
                    />
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-1">Filtrar Estado</label>
                    <select
                        className="w-full border border-slate-200 p-2 rounded-lg text-sm outline-none focus:border-emerald-500"
                        value={filtros.estado}
                        onChange={(e) => setFiltros({ ...filtros, estado: e.target.value })}
                    >
                        <option value="">Todos los estados</option>
                        <option value="PENDIENTE">Pendiente</option>
                        <option value="ACEPTADA">Aceptada</option>
                        <option value="ANULADA">Anulada</option>
                    </select>
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-1">Desde</label>
                    <input
                        type="date"
                        className="w-full border border-slate-200 p-2 rounded-lg text-sm outline-none focus:border-emerald-500"
                        value={filtros.fechaInicio}
                        onChange={(e) => setFiltros({ ...filtros, fechaInicio: e.target.value })}
                    />
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-1">Hasta</label>
                    <input
                        type="date"
                        className="w-full border border-slate-200 p-2 rounded-lg text-sm outline-none focus:border-emerald-500"
                        value={filtros.fechaFin}
                        onChange={(e) => setFiltros({ ...filtros, fechaFin: e.target.value })}
                    />
                </div>
            </div>

            {/* TABLA */}
            <div className="bg-white shadow-sm rounded-xl overflow-hidden border border-slate-200">
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
                        {loading ? (
                            <tr><td colSpan="6" className="p-10 text-center text-slate-400 font-medium">Cargando cotizaciones...</td></tr>
                        ) : cotizacionesFiltradas.length === 0 ? (
                            <tr><td colSpan="6" className="p-10 text-center text-slate-400 font-medium">No se encontraron resultados</td></tr>
                        ) : (
                            cotizacionesFiltradas.map(c => (
                                <tr key={c.id} className="hover:bg-slate-50/50 transition-colors">
                                    <td className="p-4 text-slate-500 font-mono text-sm">#{c.id.toString().padStart(5, '0')}</td>
                                    <td className="p-4 text-slate-900 font-bold">{c.nombre_cliente}</td>
                                    <td className="p-4 text-slate-600 text-sm text-center">{c.fecha_emision}</td>
                                    <td className="p-4 text-slate-900 font-black text-right">${parseFloat(c.total).toLocaleString('es-CL')}</td>
                                    <td className="p-4 text-center">
                                        <span className={`px-3 py-1 rounded-full text-[10px] font-black border ${getEstadoStyle(c.estado_nombre)}`}>
                                            {c.estado_nombre}
                                        </span>
                                    </td>
                                    <td className="p-4 text-right">
                                        <div className="flex justify-end gap-2">
                                            {c.estado_nombre === 'PENDIENTE' && (
                                                <>
                                                    <button
                                                        onClick={() => setConfirmarAccion({ show: true, id: c.id, nuevoEstado: 'ACEPTADA', tipo: 'success' })}
                                                        className="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all"
                                                        title="Aceptar"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" /></svg>
                                                    </button>
                                                    <button
                                                        onClick={() => setConfirmarAccion({ show: true, id: c.id, nuevoEstado: 'ANULADA', tipo: 'danger' })}
                                                        className="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition-all"
                                                        title="Anular"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </>
                                            )}
                                            <button
                                                onClick={() => descargarPDF(c.id, c.nombre_cliente)}
                                                className="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                                title="Descargar PDF"
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default GestionCotizaciones;