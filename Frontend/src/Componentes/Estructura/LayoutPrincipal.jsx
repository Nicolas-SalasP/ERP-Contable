import React, { useState } from 'react';
import { useLocation } from 'react-router-dom';
import BarraLateral from './BarraLateral';
import BannerSuscripcion from '../BannerSuscripcion';
import ErrorBoundary from '../ErrorBoundary';

const LayoutPrincipal = ({ children }) => {
    const [isSidebarOpen, setSidebarOpen] = useState(false);
    const location = useLocation();

    const openSidebar = () => setSidebarOpen(true);
    const closeSidebar = () => setSidebarOpen(false);
    const toggleSidebar = () => setSidebarOpen((prev) => !prev);

    return (
        <div className="flex h-screen bg-gray-100 overflow-hidden">

            <BarraLateral
                isOpen={isSidebarOpen}
                toggleSidebar={toggleSidebar}
                closeSidebar={closeSidebar}
            />

            <div className="flex-1 flex flex-col overflow-hidden">
                <header className="lg:hidden bg-white shadow-sm p-4 flex items-center justify-between z-10">
                    <button onClick={openSidebar} className="text-slate-600 focus:outline-none">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <span className="font-bold text-slate-800">ERP Contable</span>
                    <div className="w-6"></div>
                </header>

                <BannerSuscripcion />

                <main className="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-3 sm:p-4 md:p-6 lg:p-8">
                    <ErrorBoundary key={location.pathname} mensaje="Este módulo tuvo un problema. El resto del sistema sigue disponible.">
                        {children}
                    </ErrorBoundary>
                </main>
            </div>
        </div>
    );
};

export default LayoutPrincipal;