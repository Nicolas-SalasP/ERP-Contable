import React from 'react';

/**
 * Panel modal genérico para formularios del módulo RRHH.
 * Overlay centrado, cierre por click en fondo o botón X.
 */
const PanelModal = ({ abierto, titulo, icono = 'fas fa-pen', onClose, children, ancho = 'max-w-2xl' }) => {
    if (!abierto) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={onClose} />
            <div className={`relative w-full ${ancho} bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-h-[90vh] flex flex-col`}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <i className={`${icono} text-emerald-600`} />
                        {titulo}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                        aria-label="Cerrar"
                    >
                        <i className="fas fa-times text-lg" />
                    </button>
                </div>
                <div className="overflow-y-auto px-6 py-5">{children}</div>
            </div>
        </div>
    );
};

export default PanelModal;
