import React from 'react';
const ModalCentroEdicion = ({ isOpen, centro, onChange, onClose, onSubmit }) => {
    if (!isOpen || !centro) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-fade-in">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div className="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                    <h3 className="font-black text-slate-800 text-lg flex items-center gap-2">
                        <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        Editar Centro de Costo
                    </h3>
                    <button
                        onClick={onClose}
                        className="text-slate-400 hover:text-rose-500 transition-colors"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
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
                            className="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono uppercase outline-none focus:ring-2 focus:ring-indigo-500"
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
                            className="w-full border border-slate-200 rounded-xl p-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <div className="pt-3 flex justify-end gap-3 border-t border-slate-100">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
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
