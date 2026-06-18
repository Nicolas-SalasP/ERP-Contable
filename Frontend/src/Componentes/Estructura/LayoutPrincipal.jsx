import React, { useState } from 'react';
import { useLocation } from 'react-router-dom';
import BarraLateral from './BarraLateral';
import BannerSuscripcion from '../BannerSuscripcion';
import BannerPrivacidad from './BannerPrivacidad';
import ErrorBoundary from '../ErrorBoundary';

const LayoutPrincipal = ({ children }) => {
    const [isSidebarOpen, setSidebarOpen] = useState(false);
    const [colapsado, setColapsado] = useState(() => {
        try {
            const stored = localStorage.getItem('tenri_sidebar_colapsado');
            return stored !== null ? stored === 'true' : false;
        } catch {
            return false;
        }
    });
    const location = useLocation();

    const openSidebar = () => setSidebarOpen(true);
    const closeSidebar = () => setSidebarOpen(false);
    const toggleSidebar = () => setSidebarOpen((prev) => !prev);

    const toggleColapsado = () => {
        setColapsado(prev => {
            const next = !prev;
            try { localStorage.setItem('tenri_sidebar_colapsado', String(next)); } catch {}
            return next;
        });
    };

    return (
        <div className="relative flex h-screen bg-gray-100 overflow-hidden">

            <BarraLateral
                isOpen={isSidebarOpen}
                toggleSidebar={toggleSidebar}
                closeSidebar={closeSidebar}
                colapsado={colapsado}
                toggleColapsado={toggleColapsado}
            />

            {/* Botón toggle sidebar — fuera del sidebar para evitar overflow-hidden */}
            <button
                onClick={toggleColapsado}
                aria-label={colapsado ? 'Expandir menú' : 'Colapsar menú'}
                className="hidden lg:flex items-center justify-center absolute z-40 top-[74px] w-7 h-7 rounded-md bg-slate-800 border border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition-all shadow-sm"
                style={{ left: colapsado ? '50px' : '242px', transition: 'left 300ms ease-in-out' }}
            >
                {colapsado ? (
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                ) : (
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                )}
            </button>

            <div className="flex-1 flex flex-col overflow-hidden">
                <header className="lg:hidden bg-white shadow-sm p-4 flex items-center justify-between z-10">
                    <button onClick={openSidebar} className="text-slate-600 focus:outline-none">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <span className="font-bold text-slate-800">ERP Contable</span>
                    <div className="w-6"></div>
                </header>

                <BannerSuscripcion />
                <BannerPrivacidad />

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
