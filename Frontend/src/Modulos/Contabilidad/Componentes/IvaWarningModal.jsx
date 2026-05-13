import React, { useState } from 'react';

const formatCurrency = (value) => {
    if (!value && value !== 0) return '';
    return new Intl.NumberFormat('es-CL').format(value.toString().replace(/\D/g, ''));
};

const IvaWarningModal = ({ isOpen, onClose, onConfirm, calculado, ingresado }) => {
    const [motivo, setMotivo] = useState('');
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4 animate-fade-in">
            <div className="bg-white rounded-lg shadow-2xl p-6 max-w-md w-full border-t-4 border-yellow-500">
                <h3 className="text-lg font-bold text-gray-900 mb-2">
                    ⚠️ Diferencia de Impuesto Detectada
                </h3>
                <p className="text-sm text-gray-600 mb-4">
                    El IVA ingresado difiere del cálculo teórico (19%).
                </p>
                <div className="bg-gray-50 p-3 rounded mb-4 text-sm grid grid-cols-2 gap-4">
                    <div>
                        <span className="block text-gray-500 text-xs">Calculado (19%)</span>
                        <span className="font-bold text-gray-500 text-lg">${formatCurrency(calculado)}</span>
                    </div>
                    <div>
                        <span className="block text-gray-900 text-xs">Ingresado (Real)</span>
                        <span className="font-bold text-emerald-600 text-lg">${formatCurrency(ingresado)}</span>
                    </div>
                </div>
                <label className="block text-sm font-bold text-gray-700 mb-1">
                    Motivo (Para Auditoría)
                </label>
                <textarea
                    className="w-full border rounded p-2 text-sm focus:ring-yellow-500 outline-none"
                    rows="2"
                    placeholder="Ej: Impuesto específico..."
                    value={motivo}
                    onChange={(e) => setMotivo(e.target.value)}
                />
                <div className="flex justify-end space-x-3 mt-6">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded text-sm"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={() => onConfirm(motivo)}
                        className="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded text-sm font-bold shadow"
                    >
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    );
};

export default IvaWarningModal;
