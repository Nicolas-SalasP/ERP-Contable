import React, { useState, useEffect } from 'react';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

const GestionUsuarios = () => {
    const [usuarios, setUsuarios] = useState([]);
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);
    
    // Estados para Modales
    const [showModalInvitar, setShowModalInvitar] = useState(false);
    const [showModalEditar, setShowModalEditar] = useState(false);
    
    // Usuario seleccionado para ver detalles/editar
    const [usuarioSeleccionado, setUsuarioSeleccionado] = useState(null);

    const currentUser = JSON.parse(localStorage.getItem('erp_user') || sessionStorage.getItem('erp_user') || '{}');

    const [formInvitar, setFormInvitar] = useState({
        email: '',
        rol_id: ''
    });

    useEffect(() => {
        cargarDatos();
    }, []);

    const cargarDatos = async () => {
        setLoading(true);
        try {
            const [resUsuarios, resRoles] = await Promise.all([
                api.get('/usuarios'),
                api.get('/usuarios/roles')
            ]);

            if (resUsuarios.success) setUsuarios(resUsuarios.data);
            if (resRoles.success) {
                setRoles(resRoles.data);
                if (resRoles.data.length > 0 && !formInvitar.rol_id) {
                    setFormInvitar(prev => ({ ...prev, rol_id: resRoles.data[0].id }));
                }
            }
        } catch (error) {
            Swal.fire({ icon: 'error', text: 'Error al cargar los usuarios.', confirmButtonColor: '#0f172a' });
        } finally {
            setLoading(false);
        }
    };

    // --- ACCIONES: INVITAR ---
    const handleInvitar = async (e) => {
        e.preventDefault();
        try {
            const res = await api.post('/usuarios/invitar', formInvitar);
            if (res.success) {
                Swal.fire({ icon: 'success', title: '¡Invitación Enviada!', text: 'El usuario ya puede acceder al sistema.', timer: 2000, showConfirmButton: false });
                setShowModalInvitar(false);
                setFormInvitar({ ...formInvitar, email: '' }); 
                cargarDatos();
            }
        } catch (error) {
            Swal.fire({ icon: 'error', text: error.message || 'No se pudo invitar al usuario.', confirmButtonColor: '#0f172a' });
        }
    };

    // --- ACCIONES: EDITAR/VER PERFIL ---
    const abrirPerfilUsuario = (usuario) => {
        setUsuarioSeleccionado({
            ...usuario,
            nuevo_rol_id: usuario.rol_id // Guardamos el rol actual para el select
        });
        setShowModalEditar(true);
    };

    const handleActualizarRol = async (e) => {
        e.preventDefault();
        if (usuarioSeleccionado.nuevo_rol_id == usuarioSeleccionado.rol_id) {
            setShowModalEditar(false); // No hubo cambios
            return;
        }

        try {
            const res = await api.put(`/usuarios/${usuarioSeleccionado.id}/rol`, { rol_id: usuarioSeleccionado.nuevo_rol_id });
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Rol Actualizado', timer: 1500, showConfirmButton: false });
                setShowModalEditar(false);
                cargarDatos();
            }
        } catch (error) {
            Swal.fire({ icon: 'error', text: 'Error al cambiar el rol.', confirmButtonColor: '#0f172a' });
        }
    };

    // --- ACCIONES: DESVINCULAR ---
    const handleDesvincular = async (usuario) => {
        if (usuario.id === currentUser.id) {
            Swal.fire({ icon: 'warning', text: 'No puedes desvincularte a ti mismo.' });
            return;
        }

        const confirmacion = await Swal.fire({
            title: '¿Desvincular Usuario?',
            text: `¿Estás seguro que deseas quitar el acceso a ${usuario.email}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, Desvincular',
            cancelButtonText: 'Cancelar'
        });

        if (confirmacion.isConfirmed) {
            try {
                const res = await api.delete(`/usuarios/${usuario.id}`);
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Desvinculado', timer: 1500, showConfirmButton: false });
                    cargarDatos();
                }
            } catch (error) {
                Swal.fire({ icon: 'error', text: 'Error al desvincular al usuario.' });
            }
        }
    };

    // --- UTILIDADES ---
    const getIniciales = (nombre) => {
        if (!nombre || nombre === 'Usuario Invitado') return '?';
        const partes = nombre.split(' ');
        if (partes.length > 1) return (partes[0][0] + partes[1][0]).toUpperCase();
        return nombre.substring(0, 2).toUpperCase();
    };

    const formatearFecha = (fecha) => {
        if (!fecha) return 'Nunca ha ingresado';
        const date = new Date(fecha);
        return date.toLocaleDateString('es-CL', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    return (
        <div className="p-4 md:p-6 max-w-7xl mx-auto animate-fadeIn">
            
            {/* Cabecera Responsiva */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 md:mb-8 gap-4 overflow-hidden">
                <div>
                    <h1 className="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">Gestión de Equipo</h1>
                    <p className="text-slate-500 text-sm mt-1">Administra los accesos y roles de tu empresa.</p>
                </div>
                <button 
                    onClick={() => setShowModalInvitar(true)}
                    className="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 sm:py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-500/30 transition-all flex items-center justify-center gap-2"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Invitar Usuario
                </button>
            </div>

            {/* Tabla de Usuarios con Scroll Horizontal */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div className="overflow-x-auto custom-scrollbar">
                    <table className="w-full text-left border-collapse min-w-[750px]">
                        <thead>
                            <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider font-bold">
                                <th className="px-6 py-4 whitespace-nowrap">Usuario</th>
                                <th className="px-6 py-4 whitespace-nowrap">Rol en el Sistema</th>
                                <th className="px-6 py-4 whitespace-nowrap">Estado</th>
                                <th className="px-6 py-4 whitespace-nowrap text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-sm">
                            {loading ? (
                                <tr>
                                    <td colSpan="4" className="px-6 py-10 text-center text-slate-400">
                                        <div className="flex justify-center items-center gap-2">
                                            <svg className="animate-spin h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Cargando equipo...
                                        </div>
                                    </td>
                                </tr>
                            ) : usuarios.length === 0 ? (
                                <tr>
                                    <td colSpan="4" className="px-6 py-10 text-center text-slate-400">
                                        No hay usuarios registrados en esta empresa.
                                    </td>
                                </tr>
                            ) : (
                                usuarios.map((user) => (
                                    <tr key={user.id} className="hover:bg-slate-50 transition-colors">
                                        
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-4">
                                                <div className="w-10 h-10 rounded-full bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center font-bold text-sm shrink-0">
                                                    {getIniciales(user.nombre)}
                                                </div>
                                                <div>
                                                    <div className="font-bold text-slate-800 flex items-center gap-2">
                                                        {user.nombre} 
                                                        {user.id === currentUser.id && <span className="text-[10px] bg-emerald-100 text-emerald-700 font-black px-2 py-0.5 rounded-md uppercase tracking-widest">Tú</span>}
                                                    </div>
                                                    <div className="text-slate-500 text-xs">{user.email}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 border border-slate-200 text-slate-700">
                                                <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                                {roles.find(r => r.id === user.rol_id)?.nombre || 'Desconocido'}
                                            </span>
                                        </td>

                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {user.nombre === 'Usuario Invitado' ? (
                                                <span className="text-amber-600 font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200 inline-flex">
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    Pendiente
                                                </span>
                                            ) : (
                                                <span className="text-emerald-600 font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200 inline-flex">
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    Activo
                                                </span>
                                            )}
                                        </td>

                                        <td className="px-6 py-4 text-center whitespace-nowrap">
                                            <div className="flex justify-center gap-3">
                                                {/* Botón Ver Perfil / Editar */}
                                                <button 
                                                    onClick={() => abrirPerfilUsuario(user)}
                                                    className="w-9 h-9 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 hover:border-emerald-500 transition-all shadow-sm"
                                                    title="Ver Perfil y Editar Rol"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                </button>
                                                
                                                {/* Botón Desvincular */}
                                                <button 
                                                    onClick={() => handleDesvincular(user)}
                                                    disabled={user.id === currentUser.id}
                                                    className={`w-9 h-9 flex items-center justify-center rounded-lg transition-all shadow-sm border ${
                                                        user.id === currentUser.id 
                                                        ? 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed' 
                                                        : 'bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white border-rose-200 hover:border-rose-500'
                                                    }`}
                                                    title="Desvincular"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
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

            {/* MODAL 1: INVITAR USUARIO */}
            {showModalInvitar && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 animate-fadeIn">
                    <div className="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
                        <div className="p-5 md:p-6 border-b border-slate-100 flex justify-between items-center shrink-0">
                            <h3 className="text-lg font-black text-slate-800">Invitar al Equipo</h3>
                            <button onClick={() => setShowModalInvitar(false)} className="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div className="p-5 md:p-6 overflow-y-auto">
                            <form onSubmit={handleInvitar} className="space-y-4 md:space-y-5">
                                <div>
                                    <label className="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Correo Electrónico</label>
                                    <input type="email" required value={formInvitar.email} onChange={(e) => setFormInvitar({ ...formInvitar, email: e.target.value })} placeholder="correo@ejemplo.com" className="w-full border border-slate-200 rounded-xl p-3 md:p-4 text-sm outline-none focus:ring-2 focus:ring-emerald-500 bg-slate-50 transition-all font-bold text-slate-800" />
                                    <p className="text-[10px] text-slate-400 mt-2 flex items-center gap-1">
                                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Si no tiene cuenta en AtlasWeb, se registrará al ingresar este correo.
                                    </p>
                                </div>
                                <div>
                                    <label className="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Rol Asignado Inicialmente</label>
                                    <select value={formInvitar.rol_id} onChange={(e) => setFormInvitar({ ...formInvitar, rol_id: e.target.value })} className="w-full border border-slate-200 rounded-xl p-3 md:p-4 text-sm outline-none focus:ring-2 focus:ring-emerald-500 bg-slate-50 cursor-pointer transition-all font-bold text-slate-800">
                                        {roles.map(rol => (
                                            <option key={rol.id} value={rol.id}>{rol.nombre}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="pt-2 flex flex-col-reverse sm:flex-row gap-3">
                                    <button type="button" onClick={() => setShowModalInvitar(false)} className="w-full sm:flex-1 bg-slate-100 text-slate-600 font-bold py-3 md:py-4 rounded-xl hover:bg-slate-200 transition-all text-sm">
                                        Cancelar
                                    </button>
                                    <button type="submit" className="w-full sm:flex-1 bg-emerald-500 text-white font-bold py-3 md:py-4 rounded-xl hover:bg-emerald-600 shadow-lg shadow-emerald-500/30 transition-all text-sm">
                                        Enviar Invitación
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL 2: PERFIL DE USUARIO Y EDICIÓN DE ROL */}
            {showModalEditar && usuarioSeleccionado && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 animate-fadeIn">
                    <div className="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
                        
                        {/* Cabecera del Perfil */}
                        <div className="relative h-24 bg-gradient-to-r from-slate-800 to-slate-900 shrink-0">
                            <button onClick={() => setShowModalEditar(false)} className="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        
                        <div className="px-6 pb-6 pt-0 relative flex-1 overflow-y-auto">
                            {/* Avatar flotante */}
                            <div className="flex justify-center -mt-12 mb-4">
                                <div className="w-24 h-24 rounded-full bg-white p-1.5 shadow-lg">
                                    <div className="w-full h-full rounded-full bg-slate-100 flex items-center justify-center text-3xl font-black text-slate-400">
                                        {getIniciales(usuarioSeleccionado.nombre)}
                                    </div>
                                </div>
                            </div>
                            
                            {/* Datos del Usuario */}
                            <div className="text-center mb-6">
                                <h3 className="text-xl font-black text-slate-800">{usuarioSeleccionado.nombre}</h3>
                                <p className="text-sm text-slate-500 font-medium">{usuarioSeleccionado.email}</p>
                            </div>

                            {/* Info de Actividad */}
                            <div className="bg-slate-50 rounded-2xl p-4 border border-slate-100 mb-6 grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Estado</p>
                                    {usuarioSeleccionado.nombre === 'Usuario Invitado' ? (
                                        <span className="text-amber-600 font-bold text-xs flex items-center gap-1.5">
                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Pendiente
                                        </span>
                                    ) : (
                                        <span className="text-emerald-600 font-bold text-xs flex items-center gap-1.5">
                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Activo
                                        </span>
                                    )}
                                </div>
                                <div>
                                    <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Último Acceso</p>
                                    <p className="text-xs font-bold text-slate-700">{formatearFecha(usuarioSeleccionado.ultimo_acceso)}</p>
                                </div>
                            </div>

                            <hr className="border-slate-100 mb-6" />

                            {/* Formulario para cambiar Rol */}
                            <form onSubmit={handleActualizarRol} className="space-y-5">
                                <div>
                                    <label className="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                        Rol del Sistema
                                    </label>
                                    <select 
                                        value={usuarioSeleccionado.nuevo_rol_id} 
                                        onChange={(e) => setUsuarioSeleccionado({ ...usuarioSeleccionado, nuevo_rol_id: e.target.value })} 
                                        className="w-full border border-slate-200 rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-emerald-500 bg-white shadow-sm cursor-pointer transition-all font-bold text-slate-800"
                                        disabled={usuarioSeleccionado.id === currentUser.id} // El usuario no puede cambiarse el rol a sí mismo
                                    >
                                        {roles.map(rol => (
                                            <option key={rol.id} value={rol.id}>{rol.nombre}</option>
                                        ))}
                                    </select>
                                    {usuarioSeleccionado.id === currentUser.id && (
                                        <p className="text-[10px] text-slate-400 mt-2">No puedes modificar tu propio rol por seguridad.</p>
                                    )}
                                </div>

                                <div className="pt-2 flex gap-3">
                                    <button 
                                        type="submit" 
                                        disabled={usuarioSeleccionado.id === currentUser.id || usuarioSeleccionado.rol_id == usuarioSeleccionado.nuevo_rol_id}
                                        className="w-full bg-emerald-500 text-white font-bold py-3 md:py-4 rounded-xl hover:bg-emerald-600 shadow-lg shadow-emerald-500/30 transition-all text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

        </div>
    );
};

export default GestionUsuarios;