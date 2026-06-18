import React from 'react';
import { Pencil, Trash2 } from 'lucide-react';
const PerfilEmpresaBancos = ({
    bancos,
    listaBancos,
    nuevoBanco,
    onNuevoBancoChange,
    onAgregarBanco,
    onEditarBanco,
    onEliminarBanco,
}) => {
    return (
        <div className="p-6 md:p-8 animate-fade-in">
            <div className="mb-6">
                <h3 className="text-xl font-black text-slate-800 dark:text-slate-200">Cuentas Bancarias</h3>
                <p className="text-sm text-slate-500">
                    Administra las cuentas utilizadas para pagos y conciliación.
                </p>
            </div>

            <form
                onSubmit={onAgregarBanco}
                className="bg-slate-50 dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 mb-8 flex flex-col md:flex-row gap-4 items-end shadow-sm"
            >
                <div className="flex-1 w-full">
                    <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                        Institución
                    </label>
                    <select
                        className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 cursor-pointer outline-none focus:ring-2 focus:ring-emerald-500 font-medium"
                        value={nuevoBanco.banco}
                        onChange={e => onNuevoBancoChange({ ...nuevoBanco, banco: e.target.value })}
                    >
                        <option value="">Seleccione banco...</option>
                        {listaBancos.map(b => (
                            <option key={b.id} value={b.nombre}>{b.nombre}</option>
                        ))}
                    </select>
                </div>
                <div className="w-full md:w-48">
                    <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                        Tipo
                    </label>
                    <select
                        className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 cursor-pointer outline-none focus:ring-2 focus:ring-emerald-500 font-medium"
                        value={nuevoBanco.tipo_cuenta}
                        onChange={e => onNuevoBancoChange({ ...nuevoBanco, tipo_cuenta: e.target.value })}
                    >
                        <option value="Corriente">Cta. Corriente</option>
                        <option value="Vista">Cta. Vista / RUT</option>
                        <option value="Ahorro">Cta. Ahorro</option>
                    </select>
                </div>
                <div className="flex-1 w-full">
                    <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                        N° Cuenta
                    </label>
                    <input
                        placeholder="123456789"
                        className="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-3 text-sm font-mono outline-none focus:ring-2 focus:ring-emerald-500 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                        value={nuevoBanco.numero_cuenta}
                        onChange={e => onNuevoBancoChange({ ...nuevoBanco, numero_cuenta: e.target.value })}
                    />
                </div>

                <button
                    type="submit"
                    className="w-full md:w-auto bg-emerald-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-emerald-500 transition-colors text-sm shadow-lg shadow-emerald-600/30 whitespace-nowrap"
                >
                    Agregar Cuenta
                </button>
            </form>

            <div className="overflow-x-auto custom-scrollbar border border-slate-200 rounded-2xl">
                <table className="min-w-full text-left bg-white dark:bg-slate-800">
                    <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                Banco
                            </th>
                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                N° Cuenta
                            </th>
                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                Acción
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                        {bancos.length === 0 ? (
                            <tr>
                                <td colSpan="3" className="p-8 text-center text-slate-400 font-medium">
                                    No hay cuentas registradas.
                                </td>
                            </tr>
                        ) : (
                            bancos.map(b => (
                                <tr key={b.id} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    <td className="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">{b.banco}</td>
                                    <td className="px-6 py-4 text-slate-600 dark:text-slate-400 font-mono text-sm">
                                        {b.tipo_cuenta} • {b.numero_cuenta}
                                    </td>
                                    <td className="px-6 py-4 text-center flex justify-center gap-2">
                                        <button
                                            onClick={() => onEditarBanco(b)}
                                            className="text-blue-500 bg-blue-50 hover:bg-blue-600 hover:text-white p-2 rounded-lg transition-colors"
                                            title="Editar"
                                        >
                                            <Pencil size={16} strokeWidth={1.75} />
                                        </button>
                                        <button
                                            onClick={() => onEliminarBanco(b.id)}
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

export default PerfilEmpresaBancos;
