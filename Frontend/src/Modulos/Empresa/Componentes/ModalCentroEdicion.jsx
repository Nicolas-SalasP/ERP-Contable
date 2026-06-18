import React from 'react';
import { Pencil, X } from 'lucide-react';
const ModalCentroEdicion = ({ isOpen, centro, onChange, onClose, onSubmit }) => {
    if (!isOpen || !centro) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-fade-in">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div className="p-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900">
                    <h3 className="font-black text-slate-800 dark:text-slate-200 text-lg flex items-center gap-2">
                        <Pencil size={20} strokeWidth={1.75} className="text-indigo-500" />
                        Editar Centro de Costo
                    </h3>
                    <button
                        onClick={onClose}
                        className="text-slate-400 hover:text-rose-500 transition-colors"
                    >
                        <X size={20} strokeWidth={1.75} />
                    </button>
                </div>

                <form onSubmit={onSubmit} className="p-6 space-y-5">
                    <div>
                        <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                            Código
                        </label>
                        <input
                            type="text"
                            value={centro.codigo}
                            onChange={e => onChange({ ...centro, codigo: e.target.value.toUpperCase() })}
                            className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm font-mono uppercase outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                            Nombre del Departamento / Proyecto
                        </label>
                        <input
                            type="text"
                            value={centro.nombre}
                            onChange={e => onChange({ ...centro, nombre: e.target.value })}
                            className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                        />
                    </div>

                    <div className="pt-3 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-700">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className="px-5 py-2.5 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-colors"
                        >
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default ModalCentroEdicion;
