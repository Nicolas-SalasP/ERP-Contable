import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api';

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
            // Endpoint dedicado (Regla #7)
            // const res = await api.get(`/facturas/${id}/auditoria`);
            // setFactura(res.data.factura);
            // setHistorial(res.data.historial);
            
            // Mock temporal para visualización estructural
            setFactura({ id: id, numero_factura: '494150', proveedor: 'Tecnomas Ltda.', estado: 'REGISTRADA' });
            setHistorial([
                { id: 2, usuario: 'Admin Sistema', fecha: '2026-02-21 10:30:00', operacion: 'RECLASIFICACIÓN', estado_ant: 'REGISTRADA', estado_nue: 'REGISTRADA', detalle: 'Cambio de cuenta 690199 a 630101. Ajuste interno en asiento original.', asiento: '26260000005' },
                { id: 1, usuario: 'Admin Sistema', fecha: '2026-02-20 19:16:23', operacion: 'CREACIÓN', estado_ant: '-', estado_nue: 'REGISTRADA', detalle: 'Ingreso original de factura al sistema.', asiento: '26260000005' }
            ]);
        } catch (error) {
            console.error(error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar la auditoría.',
                customClass: { confirmButton: 'bg-slate-900 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-slate-800' },
                buttonsStyling: false
            });
        } finally {
            setLoading(false);
        }
    };

    const handleGuardarYCerrar = () => {
        Swal.fire({
            title: '¿Finalizar revisión?',
            text: 'Se registrará tu revisión y se cerrará la vista de auditoría.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            // Aplicamos los estilos nativos de Tailwind al SweetAlert
            buttonsStyling: false,
            customClass: {
                confirmButton: 'bg-slate-900 text-white font-bold py-2.5 px-6 rounded-lg shadow-sm hover:bg-slate-800 ml-3 transition-colors',
                cancelButton: 'bg-white text-slate-700 border border-slate-300 font-bold py-2.5 px-6 rounded-lg shadow-sm hover:bg-slate-50 transition-colors',
                popup: 'rounded-2xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Auditoría Guardada',
                    text: 'Volviendo al historial...',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-2xl' }
                }).then(() => {
                    navigate('/facturas/historial');
                });
            }
        });
    };

    if (loading) {
        return (
            <div className="min-h-[60vh] flex flex-col justify-center items-center text-slate-400">
                <i className="fas fa-shield-alt text-5xl mb-4 text-slate-200 animate-pulse"></i>
                <p className="font-bold tracking-wide uppercase text-sm">Cargando auditoría segura...</p>
            </div>
        );
    }

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6 font-sans pb-10">
            {/* CABECERA RESPONSIVA */}
            <div className="bg-slate-900 rounded-t-2xl p-6 md:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 text-white shadow-lg relative">
                <div className="w-full md:w-auto">
                    <div className="flex items-center gap-3 mb-3">
                        <span className="bg-emerald-500/20 text-emerald-400 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-emerald-500/30">
                            Solo Lectura
                        </span>
                        <span className="text-slate-400 text-xs font-mono">ID Registro: {factura?.id}</span>
                    </div>
                    <h1 className="text-2xl md:text-3xl font-bold leading-tight">Auditoría: Factura N° {factura?.numero_factura}</h1>
                    <p className="text-slate-400 text-sm mt-1.5 flex items-center gap-2">
                        <i className="fas fa-building text-slate-500"></i> {factura?.proveedor}
                    </p>
                </div>

                <div className="relative w-full md:w-auto flex justify-end" ref={menuRef}>
                    <button 
                        onClick={() => setMenuAbierto(!menuAbierto)}
                        className="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors border border-slate-700 hover:border-slate-600 focus:outline-none"
                        title="Opciones de Auditoría"
                    >
                        <svg className="w-6 h-6 text-slate-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" opacity=".3"/><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    </button>

                    {/* MENÚ FLOTANTE MEJORADO */}
                    {menuAbierto && (
                        <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden z-[99] animate-fade-in origin-top-right">
                            <ul className="text-sm font-medium text-slate-700 py-1">
                                <li>
                                    <button className="w-full text-left px-5 py-3 hover:bg-slate-50 hover:text-blue-600 transition-colors flex items-center gap-3">
                                        <i className="fas fa-book w-4 text-center"></i> Ver asiento contable
                                    </button>
                                </li>
                                <li>
                                    <button className="w-full text-left px-5 py-3 hover:bg-slate-50 hover:text-amber-600 transition-colors flex items-center gap-3 border-t border-slate-100">
                                        <i className="fas fa-exchange-alt w-4 text-center"></i> Cambiar / Reclasificar
                                    </button>
                                </li>
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            <div className="bg-white border-x border-b border-slate-200 rounded-b-2xl shadow-sm p-4 md:p-8">
                <h3 className="text-lg font-bold text-slate-800 mb-6 border-b border-slate-100 pb-4 flex items-center gap-2">
                    <i className="fas fa-history text-slate-400"></i> Registro Histórico de Movimientos
                </h3>
                
                <div className="relative border-l-2 border-slate-200 ml-3 md:ml-6 space-y-8 md:space-y-10 pb-4">
                    {historial.map((log, index) => (
                        <div key={log.id} className="relative pl-6 md:pl-10">
                            {/* Punto de la línea de tiempo */}
                            <div className={`absolute -left-[9px] top-1.5 w-4 h-4 rounded-full border-4 border-white shadow-sm ${index === 0 ? 'bg-blue-500 ring-4 ring-blue-50' : 'bg-slate-300'}`}></div>
                            
                            <div className={`bg-white border rounded-xl p-4 md:p-5 hover:shadow-md transition-shadow ${index === 0 ? 'border-blue-100 shadow-sm' : 'border-slate-100'}`}>
                                <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-3 mb-4">
                                    <div className="flex flex-wrap items-center gap-2 md:gap-3">
                                        <span className={`font-black text-xs md:text-sm uppercase tracking-wide ${index === 0 ? 'text-blue-700' : 'text-slate-700'}`}>
                                            {log.operacion}
                                        </span>
                                        <span className="text-[10px] bg-slate-100 border border-slate-200 text-slate-600 px-2 py-0.5 rounded font-mono font-bold">
                                            Ref: {log.asiento}
                                        </span>
                                    </div>
                                    <div className="text-xs text-slate-500 font-mono flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-md w-fit">
                                        <i className="far fa-clock"></i> {log.fecha}
                                    </div>
                                </div>

                                <p className="text-slate-600 text-sm mb-5 leading-relaxed bg-slate-50 p-3 rounded-lg border border-slate-100">
                                    {log.detalle}
                                </p>

                                <div className="flex flex-col sm:flex-row sm:items-center gap-3 md:gap-4 bg-white p-0 text-xs">
                                    <div className="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100 w-fit">
                                        <i className="fas fa-user-circle text-slate-400"></i>
                                        <span className="text-slate-500 font-bold uppercase text-[10px] tracking-wider">Usuario:</span>
                                        <span className="font-bold text-slate-800">{log.usuario}</span>
                                    </div>
                                    
                                    {log.estado_ant !== '-' && (
                                        <div className="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100 w-fit">
                                            <span className="text-slate-500 line-through font-medium">{log.estado_ant}</span>
                                            <i className="fas fa-arrow-right text-slate-300 mx-0.5"></i>
                                            <span className="font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">{log.estado_nue}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="mt-8 flex justify-end border-t border-slate-200 pt-6">
                <button 
                    onClick={handleGuardarYCerrar}
                    className="w-full md:w-auto px-8 py-3.5 bg-slate-900 text-white font-bold rounded-xl shadow-lg shadow-slate-900/20 hover:bg-slate-800 hover:-translate-y-0.5 transition-all uppercase tracking-widest text-xs flex items-center justify-center gap-2"
                >
                    <i className="fas fa-check-double text-emerald-400"></i> Finalizar Revisión
                </button>
            </div>
        </div>
    );
};

export default VisorAuditoriaFactura;