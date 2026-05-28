import React from 'react';
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
                <h3 className="text-xl font-black text-slate-800">Centros de Costo</h3>
                <p className="text-sm text-slate-500">
                    Clasifica tus ingresos y gastos para mejorar la analítica contable.
                </p>
            </div>

            <form
                onSubmit={onAgregarCentro}
                className="bg-slate-50 p-5 rounded-2xl border border-slate-200 mb-8 flex flex-col md:flex-row gap-4 items-end shadow-sm"
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
                        className="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono uppercase outline-none focus:ring-2 focus:ring-indigo-500"
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
                        className="w-full border border-slate-200 rounded-xl p-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <button
                    type="submit"
                    className="w-full md:w-auto bg-indigo-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-indigo-500 transition-colors text-sm shadow-lg shadow-indigo-600/30 whitespace-nowrap"
                >
                    Crear Centro
                </button>
            </form>

            <div className="overflow-hidden border border-slate-200 rounded-2xl">
                <table className="min-w-full text-left bg-white">
                    <thead className="bg-slate-50 border-b border-slate-200">
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
                    <tbody className="divide-y divide-slate-100">
                        {centros.length === 0 ? (
                            <tr>
                                <td colSpan="3" className="p-8 text-center text-slate-400 font-medium">
                                    No hay centros de costo registrados.
                                </td>
                            </tr>
                        ) : (
                            centros.map(cc => (
                                <tr key={cc.id} className="hover:bg-slate-50 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className="bg-slate-200 text-slate-700 font-mono text-xs px-2.5 py-1 rounded-md font-bold">
                                            {cc.codigo}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 font-bold text-slate-800">{cc.nombre}</td>
                                    <td className="px-6 py-4 text-center flex justify-center gap-2">
                                        <button
                                            onClick={() => onEditarCentro(cc)}
                                            className="text-blue-500 bg-blue-50 hover:bg-blue-600 hover:text-white p-2 rounded-lg transition-colors"
                                            title="Editar"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            onClick={() => onEliminarCentro(cc.id)}
                                            className="text-rose-500 bg-rose-50 hover:bg-rose-600 hover:text-white p-2 rounded-lg transition-colors"
                                            title="Eliminar"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
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
