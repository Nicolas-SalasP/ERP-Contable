import React from 'react';
import { Link, useLocation } from 'react-router-dom';

const BarraLateral = ({ isOpen, toggleSidebar }) => {
    const location = useLocation();
    
    // Menú de navegación
    const menuItems = [
        { path: '/', label: 'Dashboard', icon: '📊' },
        { path: '/facturas/nueva', label: 'Ingresar Factura', icon: '📝' },
        { path: '/facturas/historial', label: 'Historial Compras', icon: '📁' }, // <--- NUEVA OPCIÓN
        { path: '/proveedores', label: 'Proveedores', icon: 'users' },
        { path: '/contabilidad/libro-mayor', label: 'Libro Mayor', icon: '📚' },
    ];

    const isActive = (path) => location.pathname === path;

    return (
        <>
            {/* Overlay para móviles */}
            <div 
                className={`fixed inset-0 bg-gray-800 bg-opacity-50 z-20 transition-opacity lg:hidden ${isOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'}`}
                onClick={toggleSidebar}
            ></div>

            {/* Barra Lateral */}
            <div className={`fixed top-0 left-0 z-30 h-full w-64 bg-slate-900 text-white transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static ${isOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                
                {/* Logo */}
                <div className="flex items-center justify-center h-16 border-b border-slate-800 bg-slate-950">
                    <h1 className="text-xl font-bold tracking-widest text-emerald-400">ERP<span className="text-white">CONTABLE</span></h1>
                </div>

                {/* Lista de Menú */}
                <nav className="mt-6 px-4 space-y-2">
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
                                ) : item.icon}
                            </span>
                            <span className="font-medium text-sm">{item.label}</span>
                        </Link>
                    ))}
                </nav>

                {/* Footer del Sidebar */}
                <div className="absolute bottom-0 w-full p-4 border-t border-slate-800 bg-slate-950">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-xs font-bold text-white">
                            NS
                        </div>
                        <div>
                            <p className="text-xs text-white font-bold">Nicolas Salas</p>
                            <p className="text-[10px] text-slate-400">Admin IT</p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

export default BarraLateral;