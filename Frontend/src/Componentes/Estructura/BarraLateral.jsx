import React, { useEffect, useMemo, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { LogOut } from 'lucide-react';
import { useAuth } from '../../Contextos/AuthContext';
import { usePermisos } from '../../Contextos/Permisos';

const BarraLateral = ({ isOpen, toggleSidebar, closeSidebar = toggleSidebar, colapsado = false, toggleColapsado }) => {
    const location = useLocation();
    const { user, logout, misEmpresas, cambiarEmpresa } = useAuth();
    const { tieneAlgunPermiso, tieneModulo } = usePermisos();
    const [openMenu, setOpenMenu] = useState('');
    const [cambiandoEmpresa, setCambiandoEmpresa] = useState(false);
    const [errorEmpresa, setErrorEmpresa] = useState('');

    const menuGroups = [
        {
            id: 'dashboard',
            label: 'Dashboard',
            icon: 'fas fa-chart-pie',
            path: '/',
        },
        {
            id: 'comercial',
            label: 'Ventas y Comercial',
            icon: 'fas fa-store',
            permisosRequeridos: ['ventas.ver', 'clientes.ver'],
            subItems: [
                { path: '/clientes', label: 'Directorio de Clientes' },
                { path: '/cotizaciones/nueva', label: 'Nueva Cotización' },
                { path: '/cotizaciones', label: 'Gestión de Cotizaciones' },
            ]
        },
        {
            id: 'compras',
            label: 'Compras y Gastos',
            icon: 'fas fa-shopping-cart',
            permisosRequeridos: ['compras.ver', 'proveedores.ver'],
            subItems: [
                { path: '/proveedores', label: 'Directorio Proveedores' },
                { path: '/proveedores/visor', label: 'Visor 360 Proveedor' },
                { path: '/facturas/nueva', label: 'Ingresar Factura' },
                { path: '/facturas/historial', label: 'Historial de Compras' },
                { path: '/comercial/honorarios-recibidos', label: 'Honorarios Recibidos', permisosRequeridos: ['compras.ver'] },
            ]
        },
        {
            id: 'tesoreria',
            label: 'Tesorería y Banco',
            icon: 'fas fa-landmark',
            permisosRequeridos: ['tesoreria.ver'],
            subItems: [
                { path: '/banco/nomina-pagos', label: 'Nómina de Pagos' },
                { path: '/banco/cartola', label: 'Importar Cartola' },
                { path: '/banco/conciliacion', label: 'Mesa de Conciliación' },
            ]
        },
        {
            id: 'contabilidad',
            label: 'Contabilidad General',
            icon: 'fas fa-book-open',
            permisosRequeridos: ['contabilidad.ver'],
            subItems: [
                { path: '/contabilidad/libro-mayor', label: 'Libro Mayor' },
                { path: '/contabilidad/balance-comprobacion', label: 'Balance de Comprobación' },
                { path: '/contabilidad/plan-cuentas', label: 'Plan de Cuentas' },
                { path: '/contabilidad/anulacion', label: 'Anulaciones' },
                { path: '/contabilidad/asiento-manual', label: 'Asiento Manual' },
                { path: '/contabilidad/cierre-periodo', label: 'Cierre de Períodos', permisosRequeridos: ['contabilidad.ver'] },
            ]
        },
        {
            id: 'activos',
            label: 'Activos Fijos',
            icon: 'fas fa-building',
            permisosRequeridos: ['activos.ver'],
            subItems: [
                { path: '/activos', label: 'Inventario Activos' },
            ]
        },
        {
            id: 'inventario',
            label: 'Inventario',
            icon: 'fas fa-boxes-stacked',
            permisosRequeridos: [
                'inventario.dashboard.ver',
                'inventario.reportes.ver',
                'inventario.productos.ver',
                'inventario.bodegas.ver',
                'inventario.ubicaciones.ver',
                'inventario.picking.ver',
                'inventario.packing.ver',
                'inventario.despachos.ver',
                'inventario.devoluciones.ver',
                'inventario.auditoria.ver',
                'inventario.eventos_integracion.ver',
                'inventario.movimientos.ver',
                'inventario.kardex.ver',
                'inventario.valorizacion.ver',
                'inventario.lotes.ver',
                'inventario.reservas.ver',
                'inventario.disponibilidad.ver',
                'inventario.tomas_fisicas.ver',
                'inventario.alertas.ver',
                'inventario.reglas_reposicion.ver',
            ],
            subItems: [
                {
                    path: '/inventario/dashboard',
                    label: 'Dashboard Inventario',
                    permisosRequeridos: [
                        'inventario.dashboard.ver',
                        'inventario.reportes.ver',
                        'inventario.productos.ver',
                        'inventario.bodegas.ver',
                        'inventario.ubicaciones.ver',
                        'inventario.picking.ver',
                        'inventario.packing.ver',
                        'inventario.despachos.ver',
                        'inventario.devoluciones.ver',
                        'inventario.eventos_integracion.ver',
                        'inventario.movimientos.ver',
                        'inventario.kardex.ver',
                        'inventario.valorizacion.ver',
                        'inventario.lotes.ver',
                        'inventario.reservas.ver',
                        'inventario.disponibilidad.ver',
                        'inventario.tomas_fisicas.ver',
                        'inventario.alertas.ver',
                        'inventario.reglas_reposicion.ver',
                    ],
                },
                {
                    path: '/inventario/reportes',
                    label: 'Reportes',
                    permisosRequeridos: ['inventario.reportes.ver'],
                },
                {
                    path: '/inventario/productos',
                    label: 'Productos',
                    permisosRequeridos: ['inventario.productos.ver'],
                },
                {
                    path: '/inventario/bodegas',
                    label: 'Bodegas',
                    permisosRequeridos: ['inventario.bodegas.ver'],
                },
                {
                    path: '/inventario/ubicaciones',
                    label: 'Ubicaciones',
                    permisosRequeridos: ['inventario.ubicaciones.ver'],
                },
                {
                    path: '/inventario/picking',
                    label: 'Picking',
                    permisosRequeridos: ['inventario.picking.ver'],
                },
                {
                    path: '/inventario/packing',
                    label: 'Packing',
                    permisosRequeridos: ['inventario.packing.ver'],
                },
                {
                    path: '/inventario/despachos',
                    label: 'Despachos',
                    permisosRequeridos: ['inventario.despachos.ver'],
                },
                {
                    path: '/inventario/devoluciones',
                    label: 'Devoluciones/Reversas',
                    permisosRequeridos: ['inventario.devoluciones.ver'],
                },
                {
                    path: '/inventario/auditoria',
                    label: 'Auditoría Operativa',
                    permisosRequeridos: ['inventario.auditoria.ver'],
                },
                {
                    path: '/inventario/eventos-integracion',
                    label: 'Eventos Integración',
                    permisosRequeridos: ['inventario.eventos_integracion.ver'],
                },
                {
                    path: '/inventario/movimientos',
                    label: 'Movimientos',
                    permisosRequeridos: ['inventario.movimientos.ver'],
                },
                {
                    path: '/inventario/kardex',
                    label: 'Kardex',
                    permisosRequeridos: ['inventario.kardex.ver'],
                },
                {
                    path: '/inventario/lotes',
                    label: 'Lotes',
                    permisosRequeridos: ['inventario.lotes.ver'],
                },
                {
                    path: '/inventario/reservas',
                    label: 'Reservas',
                    permisosRequeridos: ['inventario.reservas.ver'],
                },
                {
                    path: '/inventario/tomas-fisicas',
                    label: 'Tomas Físicas',
                    permisosRequeridos: ['inventario.tomas_fisicas.ver'],
                },
                {
                    path: '/inventario/valorizacion',
                    label: 'Valorización',
                    permisosRequeridos: ['inventario.valorizacion.ver'],
                },
                {
                    path: '/inventario/alertas',
                    label: 'Alertas y Reposición',
                    permisosRequeridos: ['inventario.alertas.ver', 'inventario.reglas_reposicion.ver'],
                },
            ]
        },
        {
            id: 'tributario',
            label: 'Gestión Tributaria',
            icon: 'fas fa-file-invoice-dollar',
            permisosRequeridos: ['tributario.ver'],
            subItems: [
                { path: '/contabilidad/cierre-f29', label: 'Cierre de IVA (F29)' },
                { path: '/tributario/renta', label: 'Operación Renta' },
                { path: '/tributario/correccion-monetaria', label: 'Corrección Monetaria' },
                { path: '/tributario/dj-1887', label: 'DJ 1887 — Rentas Empleados' },
                { path: '/tributario/dj-1879', label: 'DJ 1879 — Retenciones Honorarios' },
                { path: '/tributario/dj-1947', label: 'DJ 1947 — Propyme' },
                { path: '/tributario/dj-1926', label: 'DJ 1926 — Gastos No Deducibles' },
                { path: '/tributario/dj-1837', label: 'DJ 1837 — Honorarios sin Retención' },
                { path: '/tributario/dj-1835', label: 'DJ 1835 — Retenciones Art. 59' },
            ]
        },
        {
            id: 'sii',
            label: 'Facturación Electrónica (SII)',
            icon: 'fas fa-file-invoice',
            permisosRequeridos: [
                'sii.configuracion.ver',
                'sii.certificado.ver',
                'sii.caf.ver',
                'sii.dte.ver',
            ],
            subItems: [
                {
                    path: '/sii/configuracion',
                    label: 'Configuración',
                    permisosRequeridos: ['sii.configuracion.ver'],
                },
                {
                    path: '/sii/folios-caf',
                    label: 'Folios CAF',
                    permisosRequeridos: ['sii.caf.ver'],
                },
                {
                    path: '/sii/certificado',
                    label: 'Certificado Digital',
                    permisosRequeridos: ['sii.certificado.ver'],
                },
                {
                    path: '/sii/facturas',
                    label: 'Facturas SII',
                    permisosRequeridos: ['sii.dte.ver'],
                },
            ]
        },
        {
            id: 'rrhh',
            label: 'Recursos Humanos',
            icon: 'fas fa-users',
            permisosRequeridos: [
                'rrhh.empleados.ver',
                'rrhh.remuneraciones.ver',
                'rrhh.parametros.ver',
            ],
            subItems: [
                { path: '/rrhh/empleados', label: 'Empleados', permisosRequeridos: ['rrhh.empleados.ver'] },
                { path: '/rrhh/contratos', label: 'Contratos', permisosRequeridos: ['rrhh.empleados.ver'] },
                { path: '/rrhh/liquidaciones', label: 'Liquidaciones de Sueldo', permisosRequeridos: ['rrhh.remuneraciones.ver'] },
                { path: '/rrhh/finiquitos', label: 'Finiquitos', permisosRequeridos: ['rrhh.remuneraciones.ver'] },
                { path: '/rrhh/parametros', label: 'Parámetros Previsionales', permisosRequeridos: ['rrhh.parametros.ver'] },
                { path: '/rrhh/centralizacion', label: 'Centralización Contable', permisosRequeridos: ['rrhh.parametros.ver'] },
                { path: '/rrhh/previred', label: 'Archivo Previred', permisosRequeridos: ['rrhh.remuneraciones.ver'] },
                { path: '/rrhh/lre', label: 'LRE — Libro de Remuneraciones', permisosRequeridos: ['rrhh.remuneraciones.ver'] },
                { path: '/rrhh/emrcl', label: 'EMRCL — Encuesta INE', permisosRequeridos: ['rrhh.remuneraciones.ver'] },
            ]
        },
        {
            id: 'soporte',
            label: 'Soporte',
            icon: 'fas fa-headset',
            permisosRequeridos: ['soporte.ver'],
            subItems: [
                { path: '/soporte/tickets', label: 'Mis tickets' },
            ]
        },
        {
            id: 'administracion',
            label: 'Administración',
            icon: 'fas fa-cogs',
            permisosRequeridos: ['usuarios.ver', 'usuarios.gestionar'],
            subItems: [
                { path: '/empresa/usuarios', label: 'Gestión de Equipo' },
                { path: '/empresa/roles', label: 'Roles y Permisos' },
                { path: '/empresa/propietarios', label: 'Propietarios Empresa', permisosRequeridos: ['contabilidad.ver'] },
                { path: '/cumplimiento', label: 'Protección de Datos (DPO)', permisosRequeridos: ['usuarios.gestionar'] },
            ]
        },
        {
            id: 'glosario',
            label: 'Ayuda y Glosario',
            icon: 'fas fa-book',
            path: '/glosario',
        }
    ];

    const isMobileViewport = () => (
        typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches
    );

    const closeSidebarOnMobile = () => {
        if (isMobileViewport()) {
            closeSidebar();
        }
    };

    const isActive = (path) => {
        if (!path) return false;
        if (path === '/') return location.pathname === '/';
        return location.pathname === path || location.pathname.startsWith(`${path}/`);
    };

    const subItemVisible = (subItem) => {
        const permisoOk = !subItem.permisosRequeridos || tieneAlgunPermiso(subItem.permisosRequeridos);
        const moduloOk = !subItem.moduloRequerido || tieneModulo(subItem.moduloRequerido);
        return permisoOk && moduloOk;
    };

    const visibleSubItems = (group) => {
        return group.subItems ? group.subItems.filter(subItemVisible) : [];
    };

    const canShowGroup = (group) => {
        const hasParentPermission = !group.permisosRequeridos || tieneAlgunPermiso(group.permisosRequeridos);
        const hasParentModulo = !group.moduloRequerido || tieneModulo(group.moduloRequerido);

        if (!hasParentPermission || !hasParentModulo) return false;
        if (!group.subItems) return true;

        return visibleSubItems(group).length > 0;
    };

    const isGroupActive = (group) => {
        if (group.path && isActive(group.path)) return true;
        if (group.subItems && visibleSubItems(group).some(item => isActive(item.path))) return true;
        return false;
    };

    const activeGroupId = useMemo(() => {
        const activeGroup = menuGroups.find((group) => group.subItems && isGroupActive(group));
        return activeGroup?.id || '';
    }, [location.pathname]);

    useEffect(() => {
        setOpenMenu(activeGroupId);
    }, [activeGroupId]);

    const toggleMenu = (id, hasSubItems) => {
        if (!hasSubItems) {
            setOpenMenu('');
            closeSidebarOnMobile();
            return;
        }
        setOpenMenu(openMenu === id ? '' : id);
    };

    const getInitials = (name) => {
        return name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'US';
    };

    const empresaActiva = misEmpresas.find(e => e.activa) ?? null;

    const handleCambiarEmpresa = async (e) => {
        const empresaId = Number(e.target.value);
        if (!empresaId || empresaActiva?.id === empresaId) return;
        setCambiandoEmpresa(true);
        setErrorEmpresa('');
        const ok = await cambiarEmpresa(empresaId);
        setCambiandoEmpresa(false);
        if (!ok) setErrorEmpresa('No se pudo cambiar la empresa.');
    };

    return (
        <>
            <div
                className={`fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-20 transition-opacity lg:hidden ${isOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'}`}
                onClick={closeSidebar}
            ></div>
            <div className={`fixed top-0 left-0 z-30 h-full bg-slate-950 border-r border-slate-800 text-slate-300 transform transition-all duration-300 ease-in-out flex flex-col lg:translate-x-0 lg:static w-64 overflow-hidden ${isOpen ? 'translate-x-0' : '-translate-x-full'} ${colapsado ? 'lg:w-16' : 'lg:w-64'}`}>

                {/* Header con logo */}
                <div className={`flex items-center justify-center h-16 border-b border-slate-800/50 bg-slate-950 shrink-0 gap-2 ${colapsado ? 'px-0' : 'px-4'}`}>
                    <i className="fas fa-layer-group text-emerald-500 text-xl flex-shrink-0"></i>
                    {!colapsado && (
                        <h1 className="text-xl font-black tracking-widest text-white flex items-center gap-2">
                            ERP<span className="text-emerald-500">CONTABLE</span>
                        </h1>
                    )}
                </div>

                {/* Selector de empresa — oculto cuando colapsado */}
                {misEmpresas.length > 1 && !colapsado && (
                    <div className="px-3 pt-3 pb-2 border-b border-slate-800/50 shrink-0">
                        <label className="block text-[10px] text-slate-500 mb-1 font-semibold uppercase tracking-wider">
                            Empresa activa
                        </label>
                        <select
                            value={empresaActiva?.id ?? ''}
                            onChange={handleCambiarEmpresa}
                            disabled={cambiandoEmpresa}
                            className="w-full bg-slate-800 text-slate-200 text-xs rounded-lg px-2 py-2 border border-slate-700 focus:outline-none focus:border-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {misEmpresas.map(emp => (
                                <option key={emp.id} value={emp.id}>
                                    {emp.razon_social}
                                </option>
                            ))}
                        </select>
                        {cambiandoEmpresa && (
                            <p className="text-[10px] text-slate-400 mt-1">Cambiando...</p>
                        )}
                        {errorEmpresa && !cambiandoEmpresa && (
                            <p className="text-[10px] text-rose-400 mt-1">{errorEmpresa}</p>
                        )}
                    </div>
                )}

                <nav className={`flex-1 mt-4 space-y-1 overflow-y-auto custom-scrollbar pb-6 ${colapsado ? 'px-1' : 'px-3'}`}>
                    {menuGroups
                        .filter(canShowGroup)
                        .map((group) => {
                            const active = isGroupActive(group);
                            const open = openMenu === group.id;
                            const subItemsVisibles = visibleSubItems(group);

                            return (
                                <div key={group.id} className="mb-1">
                                    {group.subItems ? (
                                        <button
                                            type="button"
                                            aria-expanded={open}
                                            aria-controls={`menu-${group.id}`}
                                            title={colapsado ? group.label : undefined}
                                            onClick={() => {
                                                if (colapsado) {
                                                    toggleColapsado();
                                                } else {
                                                    toggleMenu(group.id, true);
                                                }
                                            }}
                                            className={`w-full flex items-center py-2.5 rounded-lg border transition-all duration-200 ${colapsado ? 'justify-center px-2' : 'justify-between px-3'} ${active
                                                    ? 'bg-emerald-500/10 text-emerald-400 font-bold border-emerald-500/20'
                                                    : open
                                                        ? 'bg-slate-800/80 text-white shadow-inner border-transparent'
                                                        : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200 border-transparent'
                                                }`}
                                        >
                                            <div className={`flex items-center ${(!colapsado || isOpen) ? 'gap-3' : 'gap-0'}`}>
                                                <i className={`${group.icon} w-5 text-center text-lg flex-shrink-0`}></i>
                                                {(!colapsado || isOpen) && (
                                                    <span className="text-xs sm:text-sm">{group.label}</span>
                                                )}
                                            </div>
                                            {(!colapsado || isOpen) && (
                                                <i className={`fas fa-chevron-down text-[10px] transition-transform duration-300 flex-shrink-0 ${open ? 'rotate-180' : ''}`}></i>
                                            )}
                                        </button>
                                    ) : (
                                        <Link
                                            to={group.path}
                                            title={colapsado ? group.label : undefined}
                                            onClick={() => toggleMenu(group.id, false)}
                                            className={`w-full flex items-center py-2.5 rounded-lg transition-all duration-200 ${colapsado ? 'justify-center px-2' : 'px-3'} ${isActive(group.path)
                                                    ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20 font-bold'
                                                    : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200'
                                                }`}
                                        >
                                            <div className={`flex items-center ${(!colapsado || isOpen) ? 'gap-3' : 'gap-0'}`}>
                                                <i className={`${group.icon} w-5 text-center text-lg flex-shrink-0`}></i>
                                                {(!colapsado || isOpen) && (
                                                    <span className="text-xs sm:text-sm">{group.label}</span>
                                                )}
                                            </div>
                                        </Link>
                                    )}

                                    {group.subItems && (
                                        <div
                                            id={`menu-${group.id}`}
                                            className={`grid transition-all duration-300 ease-in-out ${(open && !colapsado) ? 'grid-rows-[1fr] opacity-100 mt-1 mb-2' : 'grid-rows-[0fr] opacity-0'}`}
                                        >
                                            <div className="overflow-hidden">
                                                <div className="pl-11 pr-2 space-y-1 border-l-2 border-slate-800 ml-5 py-1">
                                                 {subItemsVisibles
                                                    .map((subItem) => (

                                                        <Link
                                                            key={subItem.path}
                                                            to={subItem.path}
                                                            onClick={closeSidebarOnMobile}
                                                            className={`block px-3 py-2.5 rounded-md text-xs font-medium transition-colors ${isActive(subItem.path)
                                                                    ? 'bg-emerald-500/10 text-emerald-400 font-bold'
                                                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50'
                                                                }`}
                                                        >
                                                            {subItem.label}
                                                        </Link>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                </nav>

                {/* Footer usuario */}
                <div className={`border-t border-slate-800/50 bg-slate-950 shrink-0 ${colapsado ? 'p-2' : 'p-4'}`}>
                    <div className={`flex items-center gap-2 ${colapsado ? 'flex-col justify-center' : 'justify-between'}`}>
                        <Link
                            to="/empresa/perfil"
                            className={`flex items-center gap-3 hover:bg-slate-900 p-2 rounded-lg transition-colors group ${colapsado ? 'justify-center' : 'flex-1 overflow-hidden'}`}
                            title={colapsado ? (user?.nombre || 'Configuración Empresa') : 'Ir a Configuración de Empresa'}
                            onClick={closeSidebarOnMobile}
                        >
                            <div className="w-9 h-9 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-xs font-black text-white flex-shrink-0 shadow-sm">
                                {getInitials(user?.nombre || 'User')}
                            </div>
                            {!colapsado && (
                                <div className="overflow-hidden">
                                    <p className="text-xs text-slate-200 font-bold truncate group-hover:text-emerald-400 transition-colors">
                                        {user?.nombre || 'Usuario Admin'}
                                    </p>
                                    <p className="text-[10px] text-slate-500 truncate">Configuración Empresa</p>
                                </div>
                            )}
                        </Link>

                        <button
                            onClick={logout}
                            className="text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 h-10 w-10 flex items-center justify-center rounded-lg transition-all flex-shrink-0"
                            title="Cerrar Sesión"
                        >
                            <LogOut size={20} strokeWidth={1.75} />
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};

export default BarraLateral;
