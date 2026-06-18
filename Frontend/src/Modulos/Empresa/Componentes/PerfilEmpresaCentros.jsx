import React from 'react';
import { Pencil, Trash2 } from 'lucide-react';
const PerfilEmpresaCentros = ({
    centros,
    formCentro,
    onFormCentroChange,
    onAgregarCentro,
    onEditarCentro,
    onEliminarCentro,
}) => {
    return (
        <div className="p-6 md:p-8 animate-fade-in">
            <div className="mb-6">
                <h3 className="text-xl font-black text-slate-800 dark:text-slate-200">Centros de Costo</h3>
                <p className="text-sm text-slate-500">
                    Clasifica tus ingresos y gastos para mejorar la analítica contable.
                </p>
            </div>

            <form
                onSubmit={onAgregarCentro}
                className="bg-slate-50 dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 mb-8 flex flex-col md:flex-row gap-4 items-end shadow-sm"
            >
                <div className="w-full md:w-32">
                    <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                        Código
                    </label>
                    <input
                        type="text"
                        value={formCentro.codigo}
                        onChange={e => onFormCentroChange({ ...formCentro, codigo: e.target.value.toUpperCase() })}
                        placeholder="ADM01"
                        className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm font-mono uppercase outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                    />
                </div>
                <div className="flex-1 w-full">
                    <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                        Nombre del Departamento / Proyecto
                    </label>
                    <input
                        type="text"
                        value={formCentro.nombre}
                        onChange={e => onFormCentroChange({ ...formCentro, nombre: e.target.value })}
                        placeholder="Ej: Administración Central"
                        className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                    />
                </div>
                <button
                    type="submit"
                    className="w-full md:w-auto bg-indigo-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-indigo-500 transition-colors text-sm shadow-lg shadow-indigo-600/30 whitespace-nowrap"
                >
                    Crear Centro
                </button>
            </form>

            <div className="overflow-x-auto custom-scrollbar border border-slate-200 rounded-2xl">
                <table className="min-w-full text-left bg-white dark:bg-slate-800">
                    <thead className="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-32">
                                Código
                            </th>
                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                Nombre
                            </th>
                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center w-24">
                                Acción
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                        {centros.length === 0 ? (
                            <tr>
                                <td colSpan="3" className="p-8 text-center text-slate-400 font-medium">
                                    No hay centros de costo registrados.
                                </td>
                            </tr>
                        ) : (
                            centros.map(cc => (
                                <tr key={cc.id} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-mono text-xs px-2.5 py-1 rounded-md font-bold">
                                            {cc.codigo}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">{cc.nombre}</td>
                                    <td className="px-6 py-4 text-center flex justify-center gap-2">
                                        <button
                                            onClick={() => onEditarCentro(cc)}
                                            className="text-blue-500 bg-blue-50 hover:bg-blue-600 hover:text-white p-2 rounded-lg transition-colors"
                                            title="Editar"
                                        >
                                            <Pencil size={16} strokeWidth={1.75} />
                                        </button>
                                        <button
                                            onClick={() => onEliminarCentro(cc.id)}
                                            className="text-rose-500 bg-rose-50 hover:bg-rose-600 hover:text-white p-2 rounded-lg transition-colors"
                                            title="Eliminar"
                                        >
                                            <Trash2 size={16} strokeWidth={1.75} />
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default PerfilEmpresaCentros;
