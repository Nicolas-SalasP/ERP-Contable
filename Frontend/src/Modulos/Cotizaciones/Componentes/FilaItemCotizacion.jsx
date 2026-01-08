import React from 'react';

const FilaItemCotizacion = ({ index, item, onChange, onRemove }) => {
    const handleChange = (e) => {
        const { name, value } = e.target;
        onChange(index, name, value);
    };

    return (
        <div className="flex gap-4 mb-3 items-end border-b pb-3 border-gray-100">
            <div className="flex-1">
                <label className="block text-xs text-gray-500 uppercase font-bold mb-1">Producto / Servicio</label>
                <input
                    type="text"
                    name="productoNombre"
                    value={item.productoNombre}
                    onChange={handleChange}
                    placeholder="Descripción del ítem"
                    className="w-full border p-2 rounded text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                    required
                />
            </div>
            <div className="w-24">
                <label className="block text-xs text-gray-500 uppercase font-bold mb-1">Cant.</label>
                <input
                    type="number"
                    name="cantidad"
                    value={item.cantidad}
                    onChange={handleChange}
                    min="1"
                    className="w-full border p-2 rounded text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                    required
                />
            </div>
            <div className="w-32">
                <label className="block text-xs text-gray-500 uppercase font-bold mb-1">Precio Unit.</label>
                <input
                    type="number"
                    name="precioUnitario"
                    value={item.precioUnitario}
                    onChange={handleChange}
                    min="0"
                    step="0.01"
                    className="w-full border p-2 rounded text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                    required
                />
            </div>
            <div className="w-32">
                <label className="block text-xs text-gray-500 uppercase font-bold mb-1">Subtotal</label>
                <div className="p-2 bg-gray-50 rounded text-sm text-right font-medium">
                    ${(item.cantidad * item.precioUnitario).toLocaleString()}
                </div>
            </div>
            <button
                type="button"
                onClick={() => onRemove(index)}
                className="bg-red-50 text-red-600 p-2 rounded hover:bg-red-600 hover:text-white transition-colors"
                title="Eliminar fila"
            >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
            </button>
        </div>
    );
};

export default FilaItemCotizacion;