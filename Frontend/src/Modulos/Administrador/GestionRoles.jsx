import React, { useState, useEffect } from 'react';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

const GestionRoles = () => {
    const [roles, setRoles] = useState([]);
    const [rolSeleccionado, setRolSeleccionado] = useState(null);
    const [loading, setLoading] = useState(true);

    // Definición maestra de permisos del sistema
    const listaPermisos = [
        { categoria: 'Ventas', keys: ['ventas.ver', 'ventas.crear', 'ventas.anular', 'clientes.gestionar'] },
        { categoria: 'Compras', keys: ['compras.ver', 'compras.crear', 'proveedores.gestionar'] },
        { categoria: 'Tesorería', keys: ['tesoreria.ver', 'bancos.gestionar', 'conciliacion.ejecutar'] },
        { categoria: 'Contabilidad', keys: ['contabilidad.ver', 'asientos.crear', 'plan_cuentas.editar'] },
        { categoria: 'Activos', keys: ['activos.ver', 'activos.gestionar', 'proyectos.crear'] },
        { categoria: 'Tributario', keys: ['f29.ver', 'f29.ejecutar', 'renta.ver'] },
        { categoria: 'Administración', keys: ['usuarios.gestionar', 'roles.gestionar', 'empresa.editar'] },
    ];

    useEffect(() => { cargarRoles(); }, []);

    const cargarRoles = async () => {
        setLoading(true);
        try {
            const res = await api.get('/usuarios/roles');
            if (res.success) {
                setRoles(res.data);
                if (res.data.length > 0) setRolSeleccionado(res.data[0]);
            }
        } finally { setLoading(false); }
    };

    const handleTogglePermiso = (key) => {
        const nuevosPermisos = rolSeleccionado.permisos || [];
        const act = nuevosPermisos.includes(key) 
            ? nuevosPermisos.filter(p => p !== key) 
            : [...nuevosPermisos, key];
        
        setRolSeleccionado({ ...rolSeleccionado, permisos: act });
    };

    const handleGuardar = async () => {
        try {
            const res = await api.put(`/usuarios/roles/${rolSeleccionado.id}`, {
                nombre: rolSeleccionado.nombre,
                permisos: rolSeleccionado.permisos
            });
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Permisos Actualizados', timer: 1500, showConfirmButton: false });
                cargarRoles();
            }
        } catch (error) {
            Swal.fire({ icon: 'error', text: 'No se pudieron guardar los cambios.' });
        }
    };

    return (
        <div className="p-4 md:p-6 max-w-7xl mx-auto animate-fadeIn">
            <div className="mb-8">
                <h1 className="text-3xl font-black text-slate-800 tracking-tight">Roles y Permisos</h1>
                <p className="text-slate-500 font-medium">Define qué módulos puede utilizar cada perfil de tu equipo.</p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                {/* LISTA DE ROLES */}
                <div className="lg:col-span-4 space-y-3">
                    <h3 className="text-xs font-black text-slate-400 uppercase tracking-widest px-2">Perfiles Disponibles</h3>
                    {roles.map(rol => (
                        <button 
                            key={rol.id}
                            onClick={() => setRolSeleccionado(rol)}
                            className={`w-full text-left p-4 rounded-2xl border transition-all flex items-center justify-between group ${
                                rolSeleccionado?.id === rol.id 
                                ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-200' 
                                : 'bg-white border-slate-200 text-slate-700 hover:border-indigo-300'
                            }`}
                        >
                            <span className="font-bold">{rol.nombre}</span>
                            <i className={`fas fa-chevron-right text-xs transition-transform ${rolSeleccionado?.id === rol.id ? 'translate-x-1' : 'opacity-0'}`}></i>
                        </button>
                    ))}
                    <button className="w-full p-4 rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 font-bold hover:border-indigo-300 hover:text-indigo-500 transition-all">
                        + Crear Nuevo Rol
                    </button>
                </div>

                {/* MATRIZ DE PERMISOS */}
                <div className="lg:col-span-8 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    {rolSeleccionado ? (
                        <>
                            <div className="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                                <div>
                                    <h2 className="text-xl font-black text-slate-800">Configurando: {rolSeleccionado.nombre}</h2>
                                    <p className="text-xs text-slate-500 font-medium mt-1">Marca las casillas para autorizar el acceso.</p>
                                </div>
                                <button 
                                    onClick={handleGuardar}
                                    className="bg-emerald-500 hover:bg-emerald-600 text-white font-black py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-200 transition-all text-sm"
                                >
                                    Guardar Cambios
                                </button>
                            </div>

                            <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-8 overflow-y-auto max-h-[60vh] custom-scrollbar">
                                {listaPermisos.map(grupo => (
                                    <div key={grupo.categoria} className="space-y-3">
                                        <h4 className="text-[11px] font-black text-indigo-500 uppercase tracking-widest border-b border-indigo-50 pb-2">
                                            Módulo {grupo.categoria}
                                        </h4>
                                        <div className="space-y-2">
                                            {grupo.keys.map(key => (
                                                <label key={key} className="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors group">
                                                    <div className="relative flex items-center">
                                                        <input 
                                                            type="checkbox" 
                                                            checked={rolSeleccionado.permisos?.includes(key)}
                                                            onChange={() => handleTogglePermiso(key)}
                                                            className="w-5 h-5 rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                                        />
                                                    </div>
                                                    <span className="text-sm font-bold text-slate-600 group-hover:text-slate-900 capitalize">
                                                        {key.split('.')[1].replace('_', ' ')}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </>
                    ) : (
                        <div className="p-20 text-center text-slate-300">
                            <i className="fas fa-user-shield text-6xl mb-4 opacity-20"></i>
                            <p className="font-bold">Selecciona un rol para editar sus permisos</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default GestionRoles;