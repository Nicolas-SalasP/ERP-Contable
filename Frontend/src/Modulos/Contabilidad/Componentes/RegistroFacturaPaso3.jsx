import React from 'react';
import BuscadorCuentaContable from './BuscadorCuentaContable';

const formatCurrency = (value) => {
    if (!value && value !== 0) return '';
    return new Intl.NumberFormat('es-CL').format(value.toString().replace(/\D/g, ''));
};

const RegistroFacturaPaso3 = ({
    formData,
    ivaInvalido,
    onTieneIvaChange,
    onCuentaDestinoChange,
    onCuentaIvaChange,
    onCuentaProveedorChange,
    onIvaManualChange,
}) => {
    const esNotaCredito = formData.tipoDocumento === 'NOTA_CREDITO';

    return (
        <div className="animate-fade-in-up">
            <div className="flex justify-center mb-6 md:mb-10">
                <label className={`flex w-full md:w-auto justify-center items-center space-x-3 cursor-pointer px-6 py-3 rounded-full border transition-all select-none ${
                    formData.tieneIva ? 'bg-blue-50 border-blue-200' : 'bg-slate-50 border-slate-200 opacity-75'
                }`}>
                    <input
                        type="checkbox"
                        checked={formData.tieneIva}
                        onChange={(e) => onTieneIvaChange(e.target.checked)}
                        className="form-checkbox h-5 w-5 text-blue-600 rounded focus:ring-blue-500"
                    />
                    <span className="text-sm font-bold text-slate-700">Documento Afecto a IVA (19%)</span>
                </label>
            </div>

            <div className="max-w-5xl mx-auto border border-slate-200 rounded-xl shadow-lg bg-white mb-36">
                <div className="bg-slate-50 rounded-t-xl px-6 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-start md:items-center">
                    <h3 className="font-bold text-slate-700">Previsualización y Clasificación del Asiento</h3>
                    <span className="text-xs font-mono text-slate-400 mt-1 md:mt-0">OBLIGATORIO</span>
                </div>

                <div className="flex flex-col">
                    <div className="hidden md:flex bg-white text-xs font-bold text-slate-500 uppercase tracking-wider py-3 px-6 border-b border-slate-100">
                        <div className="w-1/2">Cuenta Contable (Clasificación)</div>
                        <div className="w-1/4 text-right text-emerald-600">Debe</div>
                        <div className="w-1/4 text-right text-red-600">Haber</div>
                    </div>

                    {/* FILA GASTO / DESTINO */}
                    <div className="flex flex-col md:flex-row p-5 md:py-4 md:px-6 gap-4 md:gap-0 items-start md:items-center bg-white border-b border-slate-100 transition relative z-30">
                        <div className="w-full md:w-1/2 pr-0 md:pr-10">
                            <span className="font-bold text-slate-800 block text-xs uppercase mb-1">1. Cuenta Destino (Gasto / Activo)</span>
                            <BuscadorCuentaContable
                                cuentaSeleccionada={formData.cuentaDestino}
                                setCuentaSeleccionada={onCuentaDestinoChange}
                            />
                        </div>
                        <div className="w-full md:w-1/2 flex flex-col md:flex-row gap-2 md:gap-0 items-center mt-2 md:mt-0">
                            <div className="w-full md:w-1/2 flex justify-between md:block text-right md:pr-6">
                                <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Debe:</span>
                                {!esNotaCredito ? (
                                    <span className="font-bold text-slate-900 text-lg bg-emerald-50/50 border border-emerald-100 px-3 py-1 rounded shadow-sm">
                                        {formatCurrency(formData.montoNeto)}
                                    </span>
                                ) : <span className="text-slate-300">-</span>}
                            </div>
                            <div className="w-full md:w-1/2 flex justify-between md:block text-right">
                                <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Haber:</span>
                                {esNotaCredito ? (
                                    <span className="font-bold text-slate-900 text-lg bg-red-50/30 px-3 py-1 rounded shadow-sm">
                                        {formatCurrency(formData.montoNeto)}
                                    </span>
                                ) : <span className="text-slate-300">-</span>}
                            </div>
                        </div>
                    </div>

                    {/* FILA IVA */}
                    {formData.tieneIva && (
                        <div className={`flex flex-col md:flex-row p-5 md:py-4 md:px-6 gap-4 md:gap-0 items-start md:items-center border-b border-slate-100 relative z-20 transition ${
                            ivaInvalido ? 'bg-red-50' : 'hover:bg-blue-50/30'
                        }`}>
                            <div className="w-full md:w-1/2 pr-0 md:pr-10">
                                <span className="font-bold text-slate-800 block text-xs uppercase mb-1">2. Cuenta de Impuesto (IVA CF)</span>
                                <BuscadorCuentaContable
                                    cuentaSeleccionada={formData.cuentaIva}
                                    setCuentaSeleccionada={onCuentaIvaChange}
                                />
                                {ivaInvalido && (
                                    <span className="text-xs font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded mt-2 inline-block">
                                        ⚠️ Monto Inválido
                                    </span>
                                )}
                            </div>
                            <div className="w-full md:w-1/2 flex flex-col md:flex-row gap-3 md:gap-0 items-center mt-2 md:mt-0">
                                <div className="w-full md:w-1/2 flex justify-between md:justify-end items-center md:pr-6">
                                    <span className="md:hidden text-xs font-bold text-blue-400 uppercase mr-4">Debe:</span>
                                    {!esNotaCredito ? (
                                        <div className="relative w-full md:w-full">
                                            <span className="absolute left-3 top-2 text-blue-400 font-bold text-sm">$</span>
                                            <input
                                                type="text"
                                                value={formData.montoIvaVisual}
                                                onChange={onIvaManualChange}
                                                className={`w-full text-right font-bold text-blue-700 bg-white border rounded-md py-1.5 pl-6 pr-2 outline-none focus:ring-2 shadow-sm ${
                                                    ivaInvalido ? 'border-red-500 ring-red-200' : 'border-blue-200 focus:ring-blue-500'
                                                }`}
                                            />
                                        </div>
                                    ) : <span className="text-slate-300">-</span>}
                                </div>
                                <div className="w-full md:w-1/2 flex justify-between md:justify-end items-center">
                                    <span className="md:hidden text-xs font-bold text-gray-400 uppercase mr-4">Haber:</span>
                                    {esNotaCredito ? (
                                        <div className="relative w-full md:w-full">
                                            <span className="absolute left-3 top-2 text-blue-400 font-bold text-sm">$</span>
                                            <input
                                                type="text"
                                                value={formData.montoIvaVisual}
                                                onChange={onIvaManualChange}
                                                className={`w-full text-right font-bold text-blue-700 bg-white border rounded-md py-1.5 pl-6 pr-2 outline-none focus:ring-2 shadow-sm ${
                                                    ivaInvalido ? 'border-red-500 ring-red-200' : 'border-blue-200 focus:ring-blue-500'
                                                }`}
                                            />
                                        </div>
                                    ) : <span className="text-slate-300">-</span>}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* FILA PROVEEDORES */}
                    <div className="flex flex-col md:flex-row p-5 md:py-4 md:px-6 gap-4 md:gap-0 items-start md:items-center bg-slate-50 transition rounded-b-xl relative z-10">
                        <div className="w-full md:w-1/2 pr-0 md:pr-10">
                            <span className="font-bold text-slate-800 block text-xs uppercase mb-1">3. Cuenta Proveedor (Pasivo)</span>
                            <BuscadorCuentaContable
                                cuentaSeleccionada={formData.cuentaProveedor}
                                setCuentaSeleccionada={onCuentaProveedorChange}
                            />
                        </div>
                        <div className="w-full md:w-1/2 flex flex-col md:flex-row gap-2 md:gap-0 mt-2 md:mt-0">
                            <div className="w-full md:w-1/2 flex justify-between md:block text-right md:pr-6">
                                <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Debe:</span>
                                {esNotaCredito && (
                                    <span className="font-bold text-slate-900 text-lg bg-emerald-50/50 border border-emerald-100 px-2 py-1 rounded">
                                        {formData.montoVisual}
                                    </span>
                                )}
                            </div>
                            <div className="w-full md:w-1/2 flex justify-between md:block text-right">
                                <span className="md:hidden text-xs font-bold text-gray-400 uppercase">Haber:</span>
                                {!esNotaCredito ? (
                                    <span className="font-bold text-slate-900 text-lg bg-red-50/30 px-2 py-1 rounded shadow-sm">
                                        {formData.montoVisual}
                                    </span>
                                ) : <span className="text-slate-300">-</span>}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default RegistroFacturaPaso3;
