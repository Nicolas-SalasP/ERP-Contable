import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../Contextos/AuthContext';

const BarraLateral = ({ isOpen, toggleSidebar }) => {
    const location = useLocation();
    const { user, logout } = useAuth();
    const [openMenu, setOpenMenu] = useState('');

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
            subItems: [
                { path: '/proveedores', label: 'Directorio Proveedores' },
                { path: '/proveedores/visor', label: 'Visor 360 Proveedor' },
                { path: '/facturas/nueva', label: 'Ingresar Factura' },
                { path: '/facturas/historial', label: 'Historial de Compras' },
            ]
        },
        {
            id: 'tesoreria',
            label: 'Tesorería y Banco',
            icon: 'fas fa-landmark',
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
            subItems: [
                { path: '/contabilidad/libro-mayor', label: 'Libro Mayor' },
                { path: '/contabilidad/plan-cuentas', label: 'Plan de Cuentas' },
                { path: '/contabilidad/anulacion', label: 'Anulaciones' },
                { path: '/contabilidad/asiento-manual', label: 'Asiento Manual' },
            ]
        },
        {
            id: 'activos',
            label: 'Activos Fijos',
            icon: 'fas fa-building',
            subItems: [
                { path: '/activos', label: 'Inventario Activos' },
            ]
        },
        {
            id: 'tributario',
            label: 'Gestión Tributaria',
            icon: 'fas fa-file-invoice-dollar',
            subItems: [
                { path: '/contabilidad/cierre-f29', label: 'Cierre de IVA (F29)' },
                { path: '/tributario/renta', label: 'Operación Renta' },
            ]
        }
    ];

    const isActive = (path) => location.pathname === path;
    
    const isGroupActive = (group) => {
        if (group.path && isActive(group.path)) return true;
        if (group.subItems && group.subItems.some(item => isActive(item.path))) return true;
        return false;
    };

    const toggleMenu = (id, hasSubItems) => {
        if (!hasSubItems) {
            setOpenMenu('');
            if (window.innerWidth < 1024) toggleSidebar();
            return;
        }
        setOpenMenu(openMenu === id ? '' : id);
    };

    const getInitials = (name) => {
        return name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'US';
    };

    return (
        <>
            <div 
                className={`fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-20 transition-opacity lg:hidden ${isOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'}`}
                onClick={toggleSidebar}
            ></div>
            <div className={`fixed top-0 left-0 z-30 h-full w-64 bg-slate-950 border-r border-slate-800 text-slate-300 transform transition-transform duration-300 ease-in-out flex flex-col lg:translate-x-0 lg:static ${isOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                
                <div className="flex items-center justify-center h-16 border-b border-slate-800/50 bg-slate-950 shrink-0">
                    <h1 className="text-xl font-black tracking-widest text-white flex items-center gap-2">
                        <i className="fas fa-layer-group text-emerald-500"></i>
                        ERP<span className="text-emerald-500">CONTABLE</span>
                    </h1>
                </div>

                {/* NAVEGACIÓN SCROLLABLE */}
                <nav className="flex-1 mt-4 px-3 space-y-1 overflow-y-auto custom-scrollbar pb-6">
                    {menuGroups.map((group) => {
                        const active = isGroupActive(group);
                        const open = openMenu === group.id;

                        return (
                            <div key={group.id} className="mb-1">
                                {/* BOTÓN DEL GRUPO O ENLACE DIRECTO */}
                                {group.subItems ? (
                                    <button
                                        onClick={() => toggleMenu(group.id, true)}
                                        className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 ${
                                            active
                                                ? 'bg-emerald-500/10 text-emerald-400 font-bold border border-emerald-500/20' // 1. Estoy dentro de esta sección
                                                : open
                                                    ? 'bg-slate-800/80 text-white shadow-inner' // 2. Solo lo abrí para mirar (no se cruzan colores)
                                                    : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' // 3. Cerrado y normal
                                        }`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <i className={`${group.icon} w-5 text-center text-lg`}></i>
                                            <span className="text-sm">{group.label}</span>
                                        </div>
                                        <i className={`fas fa-chevron-down text-[10px] transition-transform duration-300 ${open ? 'rotate-180' : ''}`}></i>
                                    </button>
                                ) : (
                                    <Link
                                        to={group.path}
                                        onClick={() => toggleMenu(group.id, false)}
                                        className={`w-full flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 ${
                                            isActive(group.path)
                                            ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20 font-bold' // Directo activo (Dashboard)
                                            : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <i className={`${group.icon} w-5 text-center text-lg`}></i>
                                            <span className="text-sm">{group.label}</span>
                                        </div>
                                    </Link>
                                )}

                                {/* SUBMENÚ DESPLEGABLE */}
                                {group.subItems && (
                                    <div className={`overflow-hidden transition-all duration-300 ease-in-out ${open ? 'max-h-96 opacity-100 mt-1 mb-2' : 'max-h-0 opacity-0'}`}>
                                        <div className="pl-11 pr-2 space-y-1 border-l-2 border-slate-800 ml-5 py-1">
                                            {group.subItems.map((subItem) => (
                                                <Link
                                                    key={subItem.path}
                                                    to={subItem.path}
                                                    onClick={() => window.innerWidth < 1024 && toggleSidebar()} // Se cierra en móviles al tocar
                                                    className={`block px-3 py-2.5 rounded-md text-xs font-medium transition-colors ${
                                                        isActive(subItem.path)
                                                        ? 'bg-emerald-500/10 text-emerald-400 font-bold'
                                                        : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50'
                                                    }`}
                                                >
                                                    {subItem.label}
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </nav>

                {/* PERFIL DE USUARIO ANCLADO ABAJO */}
                <div className="p-4 border-t border-slate-800/50 bg-slate-950 shrink-0">
                    <div className="flex items-center justify-between gap-2">
                        <Link 
                            to="/empresa/perfil" 
                            className="flex items-center gap-3 flex-1 hover:bg-slate-900 p-2 rounded-lg transition-colors overflow-hidden group"
                            title="Ir a Configuración de Empresa"
                            onClick={() => window.innerWidth < 1024 && toggleSidebar()}
                        >
                            <div className="w-9 h-9 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-xs font-black text-white flex-shrink-0 shadow-sm">
                                {getInitials(user?.nombre || 'User')}
                            </div>
                            <div className="overflow-hidden">
                                <p className="text-xs text-slate-200 font-bold truncate group-hover:text-emerald-400 transition-colors">
                                    {user?.nombre || 'Usuario Admin'}
                                </p>
                                <p className="text-[10px] text-slate-500 truncate">Configuración Empresa</p>
                            </div>
                        </Link>

                        <button 
                            onClick={logout}
                            className="text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 h-10 w-10 flex items-center justify-center rounded-lg transition-all"
                            title="Cerrar Sesión"
                        >
                            <i className="fas fa-sign-out-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};

export default BarraLateral;