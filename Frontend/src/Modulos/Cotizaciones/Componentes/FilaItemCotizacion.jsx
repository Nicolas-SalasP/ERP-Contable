import React from 'react';
import { Trash2 } from 'lucide-react';

const FilaItemCotizacion = ({ index, item, onChange, onRemove }) => {
    const handleFocus = (e) => e.target.select();

    return (
        <div className="flex gap-6 items-start bg-white p-6 rounded-2xl border border-slate-200 shadow-sm relative group transition-all hover:border-emerald-200 hover:shadow-md">

            <div className="flex flex-col flex-1 gap-4">
                <div className="relative">
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 ml-1">Producto o Servicio</label>
                    <input
                        type="text"
                        className="w-full font-bold text-slate-800 bg-transparent border-b-2 border-slate-100 pb-1.5 outline-none focus:border-emerald-500 transition-colors text-base"
                        value={item.productoNombre}
                        onChange={(e) => onChange(index, 'productoNombre', e.target.value)}
                        required
                    />
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 ml-1">Descripción Detallada</label>
                    <textarea
                        placeholder="Describa aquí alcances, especificaciones técnicas o condiciones especiales..."
                        className="w-full text-xs text-slate-600 bg-slate-50 p-4 rounded-xl border border-slate-100 outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/10 focus:border-emerald-500 resize-none transition-all"
                        rows="3"
                        value={item.descripcion || ''}
                        onChange={(e) => onChange(index, 'descripcion', e.target.value)}
                    />
                </div>
            </div>

            <div className="w-24">
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 text-center">Cantidad</label>
                <input
                    type="number"
                    min="1"
                    className="w-full border-2 border-slate-100 p-2.5 rounded-xl outline-none text-center font-bold text-slate-700 focus:border-emerald-500 focus:bg-emerald-50/30 transition-all"
                    value={item.cantidad}
                    onFocus={handleFocus}
                    onChange={(e) => onChange(index, 'cantidad', e.target.value)}
                    required
                />
            </div>

            <div className="w-40">
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 text-right mr-1">Precio Unitario</label>
                <div className="relative">
                    <span className="absolute left-3.5 top-2.5 text-slate-400 font-bold">$</span>
                    <input
                        type="number"
                        min="0"
                        className="w-full border-2 border-slate-100 p-2.5 pl-8 rounded-xl outline-none text-right font-bold text-slate-700 focus:border-emerald-500 focus:bg-emerald-50/30 transition-all"
                        value={item.precioUnitario}
                        onFocus={handleFocus}
                        onChange={(e) => onChange(index, 'precioUnitario', e.target.value)}
                        required
                    />
                </div>
            </div>

            <div className="w-36 text-right pt-8">
                <span className="text-[10px] block font-bold text-slate-300 uppercase mb-1">Subtotal Item</span>
                <span className="block font-black text-slate-900 text-xl tracking-tighter">
                    ${(Number(item.cantidad) * Number(item.precioUnitario)).toLocaleString('es-CL')}
                </span>
            </div>

            <button
                type="button"
                onClick={() => onRemove(index)}
                className="mt-9 text-slate-200 hover:text-red-500 transition-all p-2 hover:bg-red-50 rounded-lg"
                title="Quitar esta línea"
            >
                <Trash2 size={24} strokeWidth={1.75} />
            </button>
        </div>
    );
};

export default FilaItemCotizacion;