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
            Swal.fire('Error', 'No se pudo cargar la auditoría.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleGuardarYCerrar = () => {
        Swal.fire({
            title: '¿Está seguro de realizar esta acción?',
            text: 'Se registrará su revisión y se cerrará la vista de auditoría.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0f172a',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Operación realizada correctamente',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    navigate('/facturas/historial');
                });
            }
        });
    };

    if (loading) return <div className="p-10 text-center text-slate-500 font-bold">Cargando auditoría segura...</div>;

    return (
        <div className="max-w-6xl mx-auto p-6 font-sans">
            <div className="bg-slate-900 rounded-t-xl p-6 md:p-8 flex justify-between items-center text-white shadow-lg relative">
                <div>
                    <div className="flex items-center gap-3 mb-2">
                        <span className="bg-emerald-500/20 text-emerald-400 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-emerald-500/30">
                            Solo Lectura
                        </span>
                        <span className="text-slate-400 text-xs font-mono">ID Registro: {factura?.id}</span>
                    </div>
                    <h1 className="text-2xl md:text-3xl font-bold">Auditoría: Factura N° {factura?.numero_factura}</h1>
                    <p className="text-slate-400 text-sm mt-1">{factura?.proveedor}</p>
                </div>

                <div className="relative" ref={menuRef}>
                    <button 
                        onClick={() => setMenuAbierto(!menuAbierto)}
                        className="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors"
                    >
                        <svg className="w-6 h-6 text-slate-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" opacity=".3"/><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    </button>

                    {menuAbierto && (
                        <div className="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden z-50 animate-fade-in">
                            <ul className="text-sm font-medium text-slate-700">
                                <li>
                                    <button className="w-full text-left px-5 py-3 hover:bg-slate-50 hover:text-blue-600 transition-colors flex items-center gap-3">
                                        <i className="fas fa-book"></i> Ver asiento contable
                                    </button>
                                </li>
                                <li>
                                    <button className="w-full text-left px-5 py-3 hover:bg-slate-50 hover:text-amber-600 transition-colors flex items-center gap-3 border-t border-slate-100">
                                        <i className="fas fa-exchange-alt"></i> Cambiar / Reclasificar
                                    </button>
                                </li>
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            <div className="bg-white border-x border-b border-slate-200 rounded-b-xl shadow-sm p-6 md:p-8">
                <h3 className="text-lg font-bold text-slate-800 mb-6 border-b border-slate-100 pb-4">Registro Histórico de Movimientos</h3>
                
                <div className="relative border-l-2 border-slate-200 ml-4 md:ml-6 space-y-10 pb-4">
                    {historial.map((log, index) => (
                        <div key={log.id} className="relative pl-8 md:pl-12">
                            <div className={`absolute -left-[9px] top-0 w-4 h-4 rounded-full border-4 border-white shadow-sm ${index === 0 ? 'bg-blue-500' : 'bg-slate-300'}`}></div>
                            
                            <div className="bg-slate-50 border border-slate-100 rounded-xl p-5 hover:shadow-md transition-shadow">
                                <div className="flex flex-col md:flex-row justify-between md:items-center gap-2 mb-4">
                                    <div className="flex items-center gap-3">
                                        <span className="font-black text-slate-800 text-sm uppercase tracking-wide">{log.operacion}</span>
                                        <span className="text-[10px] bg-slate-200 text-slate-600 px-2 py-0.5 rounded font-mono font-bold">Ref: {log.asiento}</span>
                                    </div>
                                    <div className="text-xs text-slate-500 font-mono flex items-center gap-2">
                                        <i className="far fa-clock"></i> {log.fecha}
                                    </div>
                                </div>

                                <p className="text-slate-600 text-sm mb-4 leading-relaxed">
                                    {log.detalle}
                                </p>

                                <div className="flex flex-wrap items-center gap-4 bg-white p-3 rounded-lg border border-slate-200 text-xs">
                                    <div className="flex items-center gap-2">
                                        <span className="text-slate-400 font-bold uppercase">Usuario:</span>
                                        <span className="font-medium text-slate-800">{log.usuario}</span>
                                    </div>
                                    <div className="w-px h-4 bg-slate-300 hidden md:block"></div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-slate-400 font-bold uppercase">Estado:</span>
                                        <span className="text-slate-500 line-through">{log.estado_ant}</span>
                                        <i className="fas fa-arrow-right text-slate-300 mx-1"></i>
                                        <span className="font-bold text-emerald-600">{log.estado_nue}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="mt-6 flex justify-end">
                <button 
                    onClick={handleGuardarYCerrar}
                    className="px-8 py-3.5 bg-slate-900 text-white font-bold rounded-xl shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all uppercase tracking-widest text-xs"
                >
                    Guardar y Cerrar
                </button>
            </div>
        </div>
    );
};

export default VisorAuditoriaFactura;