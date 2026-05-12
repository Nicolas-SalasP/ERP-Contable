import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api';

import { logger } from '../../../Configuracion/logger';
const VisorAuditoriaFactura = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const [factura, setFactura] = useState(null);
    const [historial, setHistorial] = useState([]);
    const [loading, setLoading] = useState(true);
    const [menuAbierto, setMenuAbierto] = useState(false);
    const menuRef = useRef();

    useEffect(() => {
        cargarDatosAuditoria();
        const handleClickOutside = (event) => {
            if (menuRef.current && !menuRef.current.contains(event.target)) {
                setMenuAbierto(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, [id]);

    const cargarDatosAuditoria = async () => {
        setLoading(true);
        try {
            // CONEXIÓN REAL AL BACKEND
            const res = await api.get(`/facturas/${id}/auditoria`);
            if (res.success) {
                setFactura(res.data.factura);
                setHistorial(res.data.historial);
            } else {
                throw new Error("No se pudo obtener la información");
            }
        } catch (error) {
            logger.error(error);
            Swal.fire({
                icon: 'error',
                title: 'Error de Carga',
                text: 'No logramos conectar con el servidor de auditoría.',
                customClass: { confirmButton: 'bg-slate-900 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-slate-800' },
                buttonsStyling: false
            });
        } finally {
            setLoading(false);
        }
    };

    const handleGuardarYCerrar = () => {
        navigate(-1); // Retorna a la vista anterior (Historial)
    };

    if (loading) {
        return (
            <div className="min-h-[60vh] flex flex-col justify-center items-center text-slate-400">
                <i className="fas fa-shield-alt text-5xl mb-4 text-slate-200 animate-pulse"></i>
                <p className="font-bold tracking-wide uppercase text-sm">Validando registro de auditoría...</p>
            </div>
        );
    }

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6 font-sans pb-10">

            {/* BOTÓN VOLVER (NUEVO) */}
            <button
                onClick={() => navigate(-1)}
                className="mb-4 flex items-center gap-2 text-slate-500 hover:text-slate-900 font-bold text-sm transition-colors group"
            >
                <svg className="w-5 h-5 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15 19l-7-7 7-7"></path>
                </svg>
                Volver al Historial
            </button>

            {/* CABECERA */}
            <div className="bg-slate-900 rounded-t-2xl p-6 md:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 text-white shadow-lg relative">
                <div className="w-full md:w-auto">
                    <div className="flex items-center gap-3 mb-3">
                        <span className="bg-emerald-500/20 text-emerald-400 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-emerald-500/30">
                            Registro de Auditoría
                        </span>
                        <span className="text-slate-400 text-xs font-mono">UUID: {id}</span>
                    </div>
                    <h1 className="text-2xl md:text-3xl font-bold leading-tight">Factura N° {factura?.numero_factura}</h1>
                    <p className="text-slate-400 text-sm mt-1.5 flex items-center gap-2 uppercase tracking-wide font-medium">
                        <i className="fas fa-building text-slate-500"></i> {factura?.proveedor || 'Sin Proveedor Registrado'}
                    </p>
                </div>

                <div className="relative w-full md:w-auto flex justify-end" ref={menuRef}>
                    <button
                        onClick={() => setMenuAbierto(!menuAbierto)}
                        className="p-2.5 bg-slate-800 hover:bg-slate-700 rounded-xl transition-all border border-slate-700 hover:border-slate-500 focus:outline-none"
                    >
                        {/* ARREGLO 3 PUNTOS: Solo puntos, sin silueta de persona */}
                        <svg className="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 5v.01M12 12v.01M12 19v.01"></path>
                        </svg>
                    </button>

                    {/* MENÚ FLOTANTE */}
                    {menuAbierto && (
                        <div className="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden z-[99] animate-fade-in origin-top-right">
                            <ul className="text-sm font-medium text-slate-700 py-1">
                                <li>
                                    <button
                                        onClick={() => navigate(`/contabilidad/factura/${id}/asiento`)}
                                        className="w-full text-left px-5 py-3.5 hover:bg-slate-50 hover:text-blue-600 transition-colors flex items-center gap-3"
                                    >
                                        <i className="fas fa-file-invoice-dollar w-4 text-center"></i> Consultar Asiento Original
                                    </button>
                                </li>
                                <li>
                                    <button
                                        onClick={() => navigate(`/contabilidad/factura/${id}/reclasificar`)}
                                        className="w-full text-left px-5 py-3.5 hover:bg-slate-50 hover:text-amber-600 transition-colors flex items-center gap-3 border-t border-slate-100"
                                    >
                                        <i className="fas fa-sync-alt w-4 text-center"></i> Corregir / Reclasificar
                                    </button>
                                </li>
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            {/* CUERPO DE LA LÍNEA DE TIEMPO */}
            <div className="bg-white border-x border-b border-slate-200 rounded-b-2xl shadow-sm p-4 md:p-8">
                <h3 className="text-lg font-bold text-slate-800 mb-8 border-b border-slate-100 pb-4 flex items-center gap-2">
                    <i className="fas fa-fingerprint text-slate-400"></i> Cadena de Custodia del Documento
                </h3>

                <div className="relative border-l-2 border-slate-100 ml-3 md:ml-6 space-y-8 md:space-y-12 pb-4">
                    {historial.length > 0 ? historial.map((log, index) => (
                        <div key={log.id} className="relative pl-6 md:pl-10">
                            {/* Punto de la línea de tiempo */}
                            <div className={`absolute -left-[9px] top-1.5 w-4 h-4 rounded-full border-4 border-white shadow-sm ${index === 0 ? 'bg-blue-600 ring-4 ring-blue-50' : 'bg-slate-200'}`}></div>

                            <div className={`bg-white border rounded-2xl p-5 hover:border-slate-300 transition-all ${index === 0 ? 'border-blue-200 shadow-sm bg-blue-50/10' : 'border-slate-100'}`}>
                                <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-3 mb-4">
                                    <div className="flex flex-wrap items-center gap-2 md:gap-3">
                                        <span className={`font-black text-xs md:text-sm uppercase tracking-widest ${index === 0 ? 'text-blue-700' : 'text-slate-700'}`}>
                                            {log.operacion}
                                        </span>
                                        {log.asiento && (
                                            <span className="text-[10px] bg-slate-100 border border-slate-200 text-slate-600 px-2.5 py-1 rounded-lg font-mono font-black">
                                                ID ASIENTO: {log.asiento}
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-[11px] text-slate-500 font-mono font-bold flex items-center gap-1.5 bg-slate-50 border border-slate-100 px-3 py-1.5 rounded-full w-fit">
                                        <i className="far fa-calendar-alt"></i> {log.fecha}
                                    </div>
                                </div>

                                <p className="text-slate-600 text-sm mb-6 leading-relaxed italic border-l-4 border-slate-200 pl-4">
                                    "{log.detalle}"
                                </p>

                                <div className="flex flex-wrap items-center gap-3 text-xs">
                                    <div className="flex items-center gap-2 bg-slate-100/50 px-3 py-2 rounded-xl border border-slate-100 w-fit">
                                        <div className="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center">
                                            <i className="fas fa-user text-[10px] text-slate-500"></i>
                                        </div>
                                        <span className="text-slate-400 font-bold uppercase text-[9px] tracking-tighter">Ejecutado por:</span>
                                        <span className="font-black text-slate-800">{log.usuario}</span>
                                    </div>

                                    {log.estado_ant && log.estado_ant !== '-' && (
                                        <div className="flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-xl border border-slate-100 w-fit">
                                            <span className="text-slate-400 line-through font-bold text-[10px]">{log.estado_ant}</span>
                                            <i className="fas fa-chevron-right text-slate-300 text-[10px]"></i>
                                            <span className="font-black text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-md uppercase text-[10px]">{log.estado_nue}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )) : (
                        <div className="p-10 text-center text-slate-400 italic">
                            No se registran movimientos históricos para esta factura.
                        </div>
                    )}
                </div>
            </div>

            <div className="mt-8 flex justify-end">
                <button
                    onClick={handleGuardarYCerrar}
                    className="w-full md:w-auto px-10 py-4 bg-slate-900 text-white font-black rounded-2xl shadow-xl shadow-slate-900/20 hover:bg-slate-800 hover:-translate-y-1 transition-all uppercase tracking-widest text-[10px] flex items-center justify-center gap-3"
                >
                    <i className="fas fa-times text-slate-400"></i> Cerrar Auditoría
                </button>
            </div>
        </div>
    );
};

export default VisorAuditoriaFactura;