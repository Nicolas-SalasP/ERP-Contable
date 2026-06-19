import React, { useState, useEffect } from 'react';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';
import { UserPlus, Loader2, Users, Shield, CheckCircle, X, Clock, Pencil, Trash2, Info } from 'lucide-react';

const GestionUsuarios = () => {
    const [usuarios, setUsuarios] = useState([]);
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);

    const [showModalInvitar, setShowModalInvitar] = useState(false);
    const [showModalEditar, setShowModalEditar] = useState(false);

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

    const abrirPerfilUsuario = (usuario) => {
        setUsuarioSeleccionado({
            ...usuario,
            nuevo_rol_id: usuario.rol_id
        });
        setShowModalEditar(true);
    };

    const handleActualizarRol = async (e) => {
        e.preventDefault();
        if (usuarioSeleccionado.nuevo_rol_id == usuarioSeleccionado.rol_id) {
            setShowModalEditar(false);
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

            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 md:mb-8 gap-4 overflow-hidden">
                <div>
                    <h1 className="text-2xl md:text-3xl font-black text-slate-800 dark:text-slate-200 tracking-tight">Gestión de Equipo</h1>
                    <p className="text-slate-500 text-sm mt-1">Administra los accesos y roles de tu empresa.</p>
                </div>
                <button
                    onClick={() => setShowModalInvitar(true)}
                    className="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 sm:py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-500/30 transition-all flex items-center justify-center gap-2"
                >
                    <UserPlus size={20} strokeWidth={1.75} />
                    Invitar Usuario
                </button>
            </div>

            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div className="overflow-x-auto custom-scrollbar">
                    <table className="w-full text-left border-collapse min-w-[750px]">
                        <thead>
                            <tr className="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-slate-500 text-xs uppercase tracking-wider font-bold">
                                <th className="px-6 py-4 whitespace-nowrap">Usuario</th>
                                <th className="px-6 py-4 whitespace-nowrap">Rol en el Sistema</th>
                                <th className="px-6 py-4 whitespace-nowrap">Estado</th>
                                <th className="px-6 py-4 whitespace-nowrap text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                            {loading ? (
                                <tr>
                                    <td colSpan="4" className="px-6 py-10 text-center text-slate-400">
                                        <div className="flex justify-center items-center gap-2">
                                            <Loader2 size={20} strokeWidth={1.75} className="animate-spin text-emerald-500" />
                                            Cargando equipo...
                                        </div>
                                    </td>
                                </tr>
                            ) : usuarios.length === 0 ? (
                                <tr>
                                    <td colSpan="4" className="px-6 py-12 text-center">
                                        <div className="flex flex-col items-center gap-3 text-slate-400">
                                            <Users size={48} strokeWidth={1.75} className="text-slate-300" />
                                            <p className="font-bold text-slate-500">No hay usuarios registrados</p>
                                            <p className="text-sm">Invita a tu equipo para empezar a colaborar.</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                usuarios.map((user) => (
                                    <tr key={user.id} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-4">
                                                <div className="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 flex items-center justify-center font-bold text-sm shrink-0">
                                                    {getIniciales(user.nombre)}
                                                </div>
                                                <div>
                                                    <div className="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                                        {user.nombre}
                                                        {user.id === currentUser.id && <span className="text-[10px] bg-emerald-100 text-emerald-700 font-black px-2 py-0.5 rounded-md uppercase tracking-widest">Tú</span>}
                                                    </div>
                                                    <div className="text-slate-500 text-xs">{user.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300">
                                                <Shield size={16} strokeWidth={1.75} className="text-slate-400" />
                                                {roles.find(r => r.id === user.rol_id)?.nombre || 'Desconocido'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {user.estado_suscripcion_id === 1 ? (
                                                <span className="text-emerald-600 font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200 inline-flex">
                                                    <CheckCircle size={16} strokeWidth={1.75} />
                                                    Activo
                                                </span>
                                            ) : user.estado_suscripcion_id === 2 ? (
                                                <span className="text-rose-600 font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5 bg-rose-50 px-3 py-1.5 rounded-lg border border-rose-200 inline-flex">
                                                    <X size={16} strokeWidth={1.75} />
                                                    Inactivo
                                                </span>
                                            ) : (
                                                <span className="text-amber-600 font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200 inline-flex">
                                                    <Clock size={16} strokeWidth={1.75} />
                                                    Pendiente
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-center whitespace-nowrap">
                                            <div className="flex justify-center gap-3">
                                                <button
                                                    onClick={() => abrirPerfilUsuario(user)}
                                                    className="w-10 h-10 flex items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 hover:border-emerald-500 transition-all shadow-sm"
                                                    title="Ver Perfil y Editar Rol"
                                                    aria-label="Ver perfil y editar rol"
                                                >
                                                    <Pencil size={16} strokeWidth={1.75} />
                                                </button>
                                                <button
                                                    onClick={() => handleDesvincular(user)}
                                                    disabled={user.id === currentUser.id}
                                                    className={`w-10 h-10 flex items-center justify-center rounded-lg transition-all shadow-sm border ${user.id === currentUser.id
                                                        ? 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed'
                                                        : 'bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white border-rose-200 hover:border-rose-500'
                                                        }`}
                                                    title="Desvincular"
                                                    aria-label="Desvincular usuario"
                                                >
                                                    <Trash2 size={16} strokeWidth={1.75} />
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

            {showModalInvitar && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 animate-fadeIn">
                    <div className="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
                        <div className="p-5 md:p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0">
                            <h3 className="text-lg font-black text-slate-800 dark:text-slate-200">Invitar al Equipo</h3>
                            <button onClick={() => setShowModalInvitar(false)} className="w-9 h-9 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-600 hover:text-slate-800 transition-colors" aria-label="Cerrar">
                                <X size={16} strokeWidth={1.75} />
                            </button>
                        </div>
                        <div className="p-5 md:p-6 overflow-y-auto custom-scrollbar">
                            <form onSubmit={handleInvitar} className="space-y-4 md:space-y-5">
                                <div>
                                    <label className="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Correo Electrónico</label>
                                    <input type="email" required value={formInvitar.email} onChange={(e) => setFormInvitar({ ...formInvitar, email: e.target.value })} placeholder="correo@ejemplo.com" className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 md:p-4 text-sm outline-none focus:ring-2 focus:ring-emerald-500 bg-slate-50 dark:bg-slate-700 transition-all font-bold text-slate-800 dark:text-slate-200" />
                                    <p className="text-[10px] text-slate-400 mt-2 flex items-center gap-1">
                                        <Info size={12} strokeWidth={1.75} />
                                        Si no tiene cuenta en Tenri ERP Cloud, se registrará al ingresar este correo.
                                    </p>
                                </div>
                                <div>
                                    <label className="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Rol Asignado Inicialmente</label>
                                    <select value={formInvitar.rol_id} onChange={(e) => setFormInvitar({ ...formInvitar, rol_id: e.target.value })} className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 md:p-4 text-sm outline-none focus:ring-2 focus:ring-emerald-500 bg-slate-50 dark:bg-slate-700 cursor-pointer transition-all font-bold text-slate-800 dark:text-slate-200">
                                        {roles.map(rol => (
                                            <option key={rol.id} value={rol.id}>{rol.nombre}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="pt-2 flex flex-col-reverse sm:flex-row gap-3">
                                    <button type="button" onClick={() => setShowModalInvitar(false)} className="w-full sm:flex-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold py-3 md:py-4 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-all text-sm">
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

            {showModalEditar && usuarioSeleccionado && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 animate-fadeIn">
                    <div className="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all flex flex-col max-h-[90vh]">

                        <div className="relative h-24 bg-gradient-to-r from-slate-800 to-slate-900 shrink-0">
                            <button onClick={() => setShowModalEditar(false)} className="absolute top-4 right-4 w-9 h-9 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors z-10" aria-label="Cerrar">
                                <X size={16} strokeWidth={1.75} />
                            </button>
                        </div>

                        <div className="flex justify-center -mt-12 relative z-10">
                            <div className="w-24 h-24 rounded-full bg-white dark:bg-slate-700 p-1.5 shadow-sm">
                                <div className="w-full h-full rounded-full bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-3xl font-black text-slate-400">
                                    {getIniciales(usuarioSeleccionado.nombre)}
                                </div>
                            </div>
                        </div>

                        <div className="px-6 pb-6 pt-4 relative flex-1 overflow-y-auto custom-scrollbar">

                            <div className="text-center mb-6">
                                <h3 className="text-xl font-black text-slate-800 dark:text-slate-200">{usuarioSeleccionado.nombre}</h3>
                                <p className="text-sm text-slate-500 font-medium">{usuarioSeleccionado.email}</p>
                            </div>

                            <div className="bg-slate-50 dark:bg-slate-900 rounded-2xl p-4 border border-slate-100 dark:border-slate-700 mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Estado</p>
                                    {usuarioSeleccionado.estado_suscripcion_id === 1 ? (
                                        <span className="text-emerald-600 font-bold text-xs flex items-center gap-1.5">
                                            <CheckCircle size={14} strokeWidth={1.75} />
                                            Activo
                                        </span>
                                    ) : usuarioSeleccionado.estado_suscripcion_id === 2 ? (
                                        <span className="text-rose-600 font-bold text-xs flex items-center gap-1.5">
                                            <X size={14} strokeWidth={1.75} />
                                            Inactivo
                                        </span>
                                    ) : (
                                        <span className="text-amber-600 font-bold text-xs flex items-center gap-1.5">
                                            <Clock size={14} strokeWidth={1.75} />
                                            Pendiente
                                        </span>
                                    )}
                                </div>
                                <div>
                                    <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Último Acceso</p>
                                    <p className="text-xs font-bold text-slate-700 dark:text-slate-300">{formatearFecha(usuarioSeleccionado.ultimo_acceso)}</p>
                                </div>
                            </div>

                            <hr className="border-slate-100 dark:border-slate-700 mb-6" />

                            <form onSubmit={handleActualizarRol} className="space-y-5">
                                <div>
                                    <label className="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                                        <Shield size={14} strokeWidth={1.75} />
                                        Rol del Sistema
                                    </label>
                                    <select
                                        value={usuarioSeleccionado.nuevo_rol_id}
                                        onChange={(e) => setUsuarioSeleccionado({ ...usuarioSeleccionado, nuevo_rol_id: e.target.value })}
                                        className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-emerald-500 bg-white dark:bg-slate-700 shadow-sm cursor-pointer transition-all font-bold text-slate-800 dark:text-slate-200"
                                        disabled={usuarioSeleccionado.id === currentUser.id}
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