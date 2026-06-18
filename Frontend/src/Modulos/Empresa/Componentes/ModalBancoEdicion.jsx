import React from 'react';
import { Pencil, X } from 'lucide-react';
const ModalBancoEdicion = ({ isOpen, banco, listaBancos, onChange, onClose, onSubmit }) => {
    if (!isOpen || !banco) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-fade-in">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div className="p-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900">
                    <h3 className="font-black text-slate-800 dark:text-slate-200 text-lg flex items-center gap-2">
                        <Pencil size={20} strokeWidth={1.75} className="text-blue-500" />
                        Editar Cuenta Bancaria
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
                            Institución
                        </label>
                        <select
                            className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 cursor-pointer outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                            value={banco.banco}
                            onChange={e => onChange({ ...banco, banco: e.target.value })}
                        >
                            <option value="">Seleccione banco...</option>
                            {listaBancos.map(b => (
                                <option key={b.id} value={b.nombre}>{b.nombre}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                            Tipo de Cuenta
                        </label>
                        <select
                            className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 cursor-pointer outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                            value={banco.tipo_cuenta}
                            onChange={e => onChange({ ...banco, tipo_cuenta: e.target.value })}
                        >
                            <option value="Corriente">Cta. Corriente</option>
                            <option value="Vista">Cta. Vista / RUT</option>
                            <option value="Ahorro">Cta. Ahorro</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                            N° Cuenta
                        </label>
                        <input
                            className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm font-mono outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                            value={banco.numero_cuenta}
                            onChange={e => onChange({ ...banco, numero_cuenta: e.target.value })}
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
                            className="px-5 py-2.5 rounded-xl font-bold text-sm text-white bg-blue-600 hover:bg-blue-500 shadow-lg shadow-blue-600/30 transition-colors"
                        >
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default ModalBancoEdicion;
