import React, { useState, useEffect } from 'react';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

const SWAL_UI = {
    buttonsStyling: false,
    reverseButtons: true,
    customClass: {
        popup: 'rounded-3xl',
        title: 'text-slate-800',
        htmlContainer: 'text-slate-500',
        input: 'box-border block !w-[calc(100%-3rem)] !mx-auto rounded-xl border border-slate-200 px-4 py-3 text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none',
        confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-7 rounded-xl mx-2 shadow-lg shadow-indigo-200',
        cancelButton: 'bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2.5 px-7 rounded-xl mx-2',
    },
};

// Claves de permisos alineadas con el backend (ModuloPermisos / RolSeeder).
const listaPermisos = [
    { categoria: 'Ventas', keys: ['ventas.ver', 'ventas.crear'] },
    { categoria: 'Clientes', keys: ['clientes.ver', 'clientes.crear'] },
    { categoria: 'Compras', keys: ['compras.ver', 'compras.crear'] },
    { categoria: 'Proveedores', keys: ['proveedores.ver', 'proveedores.crear'] },
    { categoria: 'Tesorería', keys: ['tesoreria.ver', 'tesoreria.crear'] },
    { categoria: 'Contabilidad', keys: ['contabilidad.ver', 'contabilidad.crear'] },
    { categoria: 'Activos', keys: ['activos.ver', 'activos.crear'] },
    { categoria: 'Tributario', keys: ['tributario.ver', 'tributario.crear'] },
    { categoria: 'Usuarios', keys: ['usuarios.ver', 'usuarios.gestionar'] },
    {
        categoria: 'Inventario',
        keys: [
            'inventario.dashboard.ver', 'inventario.reportes.ver', 'inventario.reportes.exportar',
            'inventario.productos.ver', 'inventario.productos.crear', 'inventario.productos.editar',
            'inventario.bodegas.ver', 'inventario.bodegas.crear',
            'inventario.movimientos.ver', 'inventario.movimientos.entrada', 'inventario.movimientos.salida',
            'inventario.movimientos.traspaso', 'inventario.movimientos.ajuste',
            'inventario.kardex.ver', 'inventario.valorizacion.ver',
            'inventario.ajustes_criticos.ver', 'inventario.ajustes_criticos.crear',
            'inventario.lotes.ver', 'inventario.lotes.crear', 'inventario.lotes.editar',
            'inventario.reservas.ver', 'inventario.reservas.crear', 'inventario.reservas.cancelar',
            'inventario.reservas.liberar', 'inventario.reservas.consumir', 'inventario.disponibilidad.ver',
            'inventario.tomas_fisicas.ver', 'inventario.tomas_fisicas.crear', 'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar', 'inventario.tomas_fisicas.ajustar', 'inventario.tomas_fisicas.cancelar',
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver', 'inventario.reglas_reposicion.crear',
            'inventario.reglas_reposicion.editar', 'inventario.reglas_reposicion.eliminar',
            'inventario.ubicaciones.ver', 'inventario.ubicaciones.crear', 'inventario.ubicaciones.editar',
            'inventario.stock_ubicaciones.ver', 'inventario.stock_ubicaciones.mover', 'inventario.putaway.ejecutar',
            'inventario.picking.ver', 'inventario.picking.crear', 'inventario.picking.editar',
            'inventario.picking.confirmar', 'inventario.picking.cancelar',
            'inventario.packing.ver', 'inventario.packing.crear', 'inventario.packing.editar',
            'inventario.packing.confirmar', 'inventario.packing.cancelar',
            'inventario.despachos.ver', 'inventario.despachos.crear', 'inventario.despachos.editar',
            'inventario.despachos.confirmar', 'inventario.despachos.cancelar',
            'inventario.devoluciones.ver', 'inventario.devoluciones.crear',
            'inventario.devoluciones.confirmar', 'inventario.devoluciones.cancelar',
            'inventario.auditoria.ver', 'inventario.eventos_integracion.ver',
        ],
    },
    {
        categoria: 'SII (Facturación)',
        keys: [
            'sii.configuracion.ver', 'sii.configuracion.editar',
            'sii.certificado.ver', 'sii.certificado.subir', 'sii.certificado.revocar',
            'sii.caf.ver', 'sii.caf.subir', 'sii.caf.revocar',
            'sii.dte.ver', 'sii.dte.emitir', 'sii.dte.reintentar', 'sii.dte.anular',
            'sii.auditoria.ver',
        ],
    },
];

const GestionRoles = () => {
    const [roles, setRoles] = useState([]);
    const [rolSeleccionado, setRolSeleccionado] = useState(null);
    const [_loading, setLoading] = useState(true);
    const [guardando, setGuardando] = useState(false);

    const esRolSistema = rolSeleccionado && (rolSeleccionado.empresa_id === null || rolSeleccionado.empresa_id === undefined);

    useEffect(() => { cargarRoles(); }, []);

    const cargarRoles = async (idSeleccionar = null) => {
        setLoading(true);
        try {
            const res = await api.get('/usuarios/roles');
            if (res.success) {
                setRoles(res.data);
                const elegido = idSeleccionar
                    ? res.data.find(r => r.id === idSeleccionar)
                    : res.data.find(r => r.id === rolSeleccionado?.id) || res.data[0];
                setRolSeleccionado(elegido || null);
            }
        } finally { setLoading(false); }
    };

    const crearRol = async () => {
        const { value: nombre } = await Swal.fire({
            ...SWAL_UI,
            title: 'Nuevo rol',
            text: 'Dale un nombre al perfil que vas a configurar.',
            input: 'text',
            inputLabel: 'Nombre del rol',
            inputPlaceholder: 'Ej: Vendedor',
            showCancelButton: true,
            confirmButtonText: 'Crear rol',
            cancelButtonText: 'Cancelar',
            inputValidator: (v) => (!v || !v.trim()) && 'Ingresa un nombre para el rol.',
        });
        if (!nombre) return;

        try {
            const res = await api.post('/usuarios/roles', { nombre: nombre.trim(), permisos: [] });
            if (res.success) {
                Swal.fire({ ...SWAL_UI, icon: 'success', title: 'Rol creado', timer: 1300, showConfirmButton: false });
                await cargarRoles(res.data?.id);
            }
        } catch (error) {
            Swal.fire({ ...SWAL_UI, icon: 'error', title: 'No se pudo crear', text: error.message || 'Error al crear el rol.' });
        }
    };

    const duplicarRol = async () => {
        if (!rolSeleccionado) return;
        try {
            const res = await api.post('/usuarios/roles', {
                nombre: `${rolSeleccionado.nombre} (copia)`,
                permisos: rolSeleccionado.permisos || [],
            });
            if (res.success) {
                Swal.fire({ ...SWAL_UI, icon: 'success', title: 'Rol duplicado', text: 'Se creó una copia editable que puedes personalizar.', timer: 1800, showConfirmButton: false });
                await cargarRoles(res.data?.id);
            }
        } catch (error) {
            Swal.fire({ ...SWAL_UI, icon: 'error', title: 'No se pudo duplicar', text: error.message || 'Solo puedes asignar permisos que tú posees.' });
        }
    };

    const handleTogglePermiso = (key) => {
        if (esRolSistema) return;
        const nuevosPermisos = rolSeleccionado.permisos || [];
        const act = nuevosPermisos.includes(key)
            ? nuevosPermisos.filter(p => p !== key)
            : [...nuevosPermisos, key];

        setRolSeleccionado({ ...rolSeleccionado, permisos: act });
    };

    const handleGuardar = async () => {
        if (esRolSistema) return;
        setGuardando(true);
        try {
            const res = await api.put(`/usuarios/roles/${rolSeleccionado.id}`, {
                nombre: rolSeleccionado.nombre,
                permisos: rolSeleccionado.permisos
            });
            if (res.success) {
                Swal.fire({ ...SWAL_UI, icon: 'success', title: 'Permisos actualizados', timer: 1500, showConfirmButton: false });
                cargarRoles(rolSeleccionado.id);
            }
        } catch (error) {
            Swal.fire({ ...SWAL_UI, icon: 'error', title: 'No se pudo guardar', text: error.message || 'No se pudieron guardar los cambios.' });
        } finally {
            setGuardando(false);
        }
    };

    return (
        <div className="p-4 md:p-6 max-w-7xl mx-auto animate-fadeIn">
            <div className="mb-8">
                <h1 className="text-3xl font-black text-slate-800 dark:text-slate-200 tracking-tight">Roles y Permisos</h1>
                <p className="text-slate-500 font-medium">Define qué módulos puede utilizar cada perfil de tu equipo.</p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <div className="lg:col-span-4 space-y-3">
                    <h3 className="text-xs font-black text-slate-400 uppercase tracking-widest px-2">Perfiles Disponibles</h3>
                    {roles.map(rol => (
                        <button
                            key={rol.id}
                            onClick={() => setRolSeleccionado(rol)}
                            className={`w-full text-left p-4 rounded-2xl border transition-all flex items-center justify-between group ${
                                rolSeleccionado?.id === rol.id
                                ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-200'
                                : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-indigo-300'
                            }`}
                        >
                            <span className="font-bold flex items-center gap-2">
                                {rol.nombre}
                                {(rol.empresa_id === null || rol.empresa_id === undefined) && (
                                    <span className={`text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded ${rolSeleccionado?.id === rol.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-400'}`}>Sistema</span>
                                )}
                            </span>
                            <i className={`fas fa-chevron-right text-xs transition-transform ${rolSeleccionado?.id === rol.id ? 'translate-x-1' : 'opacity-0'}`}></i>
                        </button>
                    ))}
                    <button
                        onClick={crearRol}
                        className="w-full p-4 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 text-slate-400 font-bold hover:border-indigo-300 hover:text-indigo-500 transition-all"
                    >
                        + Crear Nuevo Rol
                    </button>
                </div>

                <div className="lg:col-span-8 bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col">
                    {rolSeleccionado ? (
                        <>
                            <div className="p-6 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex justify-between items-center gap-4">
                                <div>
                                    <h2 className="text-xl font-black text-slate-800 dark:text-slate-200">Configurando: {rolSeleccionado.nombre}</h2>
                                    <p className="text-xs text-slate-500 font-medium mt-1">
                                        {esRolSistema
                                            ? 'Rol del sistema (solo lectura). Duplícalo para crear una versión editable.'
                                            : 'Marca las casillas para autorizar el acceso.'}
                                    </p>
                                </div>
                                {esRolSistema ? (
                                    <button
                                        onClick={duplicarRol}
                                        className="bg-indigo-500 hover:bg-indigo-600 text-white font-black py-2.5 px-6 rounded-xl shadow-lg shadow-indigo-200 transition-all text-sm whitespace-nowrap"
                                    >
                                        <i className="fas fa-copy mr-2"></i>Duplicar
                                    </button>
                                ) : (
                                    <button
                                        onClick={handleGuardar}
                                        disabled={guardando}
                                        className="bg-emerald-500 hover:bg-emerald-600 text-white font-black py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-200 transition-all text-sm disabled:opacity-50 whitespace-nowrap"
                                    >
                                        {guardando ? 'Guardando…' : 'Guardar Cambios'}
                                    </button>
                                )}
                            </div>

                            <div className={`p-6 grid grid-cols-1 md:grid-cols-2 gap-8 overflow-y-auto max-h-[60vh] custom-scrollbar ${esRolSistema ? 'opacity-70' : ''}`}>
                                {listaPermisos.map(grupo => (
                                    <div key={grupo.categoria} className="space-y-3">
                                        <h4 className="text-[11px] font-black text-indigo-500 uppercase tracking-widest border-b border-indigo-50 pb-2">
                                            Módulo {grupo.categoria}
                                        </h4>
                                        <div className="space-y-2">
                                            {grupo.keys.map(key => (
                                                <label key={key} className={`flex items-center gap-3 p-2 rounded-lg transition-colors group ${esRolSistema ? 'cursor-default' : 'hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer'}`}>
                                                    <div className="relative flex items-center">
                                                        <input
                                                            type="checkbox"
                                                            checked={rolSeleccionado.permisos?.includes(key) || false}
                                                            onChange={() => handleTogglePermiso(key)}
                                                            disabled={esRolSistema}
                                                            className="w-5 h-5 rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer disabled:cursor-default"
                                                        />
                                                    </div>
                                                    <span className="text-sm font-bold text-slate-600 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-slate-100 capitalize">
                                                        {key.split('.').slice(1).join(' ').replaceAll('_', ' ')}
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
