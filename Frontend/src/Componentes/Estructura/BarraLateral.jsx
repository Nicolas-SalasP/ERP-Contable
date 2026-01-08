import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../Contextos/AuthContext'; // <--- Importamos el contexto

const BarraLateral = ({ isOpen, toggleSidebar }) => {
    const location = useLocation();
    const { user, logout } = useAuth(); // <--- Obtenemos usuario y logout
    
    const menuItems = [
        { path: '/', label: 'Dashboard', icon: '📊' },
        { path: '/facturas/nueva', label: 'Ingresar Factura', icon: '📝' },
        { path: '/facturas/historial', label: 'Historial Compras', icon: '📁' },
        { path: '/cotizaciones', label: 'Gestión Cotizaciones', icon: '💼' },
        { path: '/cotizaciones/nueva', label: 'Nueva Cotización', icon: '➕' },
        { path: '/clientes', label: 'Clientes', icon: 'users' },
        { path: '/proveedores', label: 'Proveedores', icon: 'users' },
        { path: '/contabilidad/libro-mayor', label: 'Libro Mayor', icon: '📚' },
        { 
            path: '/contabilidad/anulacion', 
            label: 'Anulaciones', 
            icon: 'anulacion'
        },
    ];

    const isActive = (path) => location.pathname === path;

    const getInitials = (name) => {
        return name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'US';
    };

    return (
        <>
            <div 
                className={`fixed inset-0 bg-gray-800 bg-opacity-50 z-20 transition-opacity lg:hidden ${isOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'}`}
                onClick={toggleSidebar}
            ></div>

            <div className={`fixed top-0 left-0 z-30 h-full w-64 bg-slate-900 text-white transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static ${isOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                
                <div className="flex items-center justify-center h-16 border-b border-slate-800 bg-slate-950">
                    <h1 className="text-xl font-bold tracking-widest text-emerald-400">ERP<span className="text-white">CONTABLE</span></h1>
                </div>

                <nav className="mt-6 px-4 space-y-2 overflow-y-auto" style={{ maxHeight: 'calc(100vh - 160px)' }}>
                    {menuItems.map((item) => (
                        <Link
                            key={item.path}
                            to={item.path}
                            onClick={() => window.innerWidth < 1024 && toggleSidebar()}
                            className={`flex items-center px-4 py-3 rounded-lg transition-colors duration-200 ${
                                isActive(item.path) 
                                ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20' 
                                : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                            }`}
                        >
                            <span className="mr-3 text-xl">
                                {item.icon === 'users' ? (
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                ) : item.icon === 'anulacion' ? (
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2zM10 9l4 4m0-4l-4 4" />
                                    </svg>
                                ) : item.icon}
                            </span>
                            <span className="font-medium text-sm">{item.label}</span>
                        </Link>
                    ))}
                </nav>

                <div className="absolute bottom-0 w-full p-4 border-t border-slate-800 bg-slate-950">
                    <div className="flex items-center justify-between gap-2">
                        <Link 
                            to="/empresa/perfil" 
                            className="flex items-center gap-3 flex-1 hover:bg-slate-900 p-2 rounded-lg transition-colors overflow-hidden group"
                            title="Ir a mi perfil"
                        >
                            <div className="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-xs font-bold text-white flex-shrink-0 group-hover:bg-emerald-400 transition-colors">
                                {getInitials(user?.nombre || 'User')}
                            </div>
                            <div className="overflow-hidden">
                                <p className="text-xs text-white font-bold truncate group-hover:text-emerald-300 transition-colors">
                                    {user?.nombre || 'Usuario'}
                                </p>
                                <p className="text-[10px] text-slate-400 truncate">Administrador</p>
                            </div>
                        </Link>

                        <button 
                            onClick={logout}
                            className="text-slate-500 hover:text-red-400 hover:bg-slate-900 p-2 rounded-lg transition-all"
                            title="Cerrar Sesión"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>

                    </div>
                </div>
            </div>
        </>
    );
};

export default BarraLateral;