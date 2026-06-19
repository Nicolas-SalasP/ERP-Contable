import React, { useState } from 'react';
import { useLocation } from 'react-router-dom';
import { ChevronRight, ChevronLeft, Menu } from 'lucide-react';
import BarraLateral from './BarraLateral';
import BannerSuscripcion from '../BannerSuscripcion';
import BannerPrivacidad from './BannerPrivacidad';
import ErrorBoundary from '../ErrorBoundary';
import { ToggleTema } from './ToggleTema';
import { BarraProgresoNavegacion } from './BarraProgresoNavegacion';

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
        <div className="relative flex h-screen bg-gray-100 dark:bg-slate-900 overflow-hidden">
            <BarraProgresoNavegacion />

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
                    <ChevronRight size={12} strokeWidth={1.75} />
                ) : (
                    <ChevronLeft size={12} strokeWidth={1.75} />
                )}
            </button>

            <div className="flex-1 flex flex-col overflow-hidden">
                <header className="lg:hidden bg-white dark:bg-slate-900 shadow-sm p-4 flex items-center justify-between z-10">
                    <button onClick={openSidebar} className="text-slate-600 dark:text-slate-300 focus:outline-none">
                        <Menu size={24} strokeWidth={1.75} />
                    </button>
                    <span className="font-bold text-slate-800 dark:text-slate-100">ERP Contable</span>
                    <ToggleTema />
                </header>

                {/* Toggle de tema visible en desktop (sidebar ya ocupa el lateral) */}
                <div className="hidden lg:flex justify-end items-center px-4 py-1 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                    <ToggleTema />
                </div>

                <BannerSuscripcion />
                <BannerPrivacidad />

                <main className="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 dark:bg-slate-900 dark:text-slate-200 p-3 sm:p-4 md:p-6 lg:p-8">
                    <ErrorBoundary key={location.pathname} mensaje="Este módulo tuvo un problema. El resto del sistema sigue disponible.">
                        {children}
                    </ErrorBoundary>
                </main>
            </div>
        </div>
    );
};

export default LayoutPrincipal;
