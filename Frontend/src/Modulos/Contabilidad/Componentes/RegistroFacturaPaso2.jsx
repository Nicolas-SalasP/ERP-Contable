import React from 'react';

const RegistroFacturaPaso2 = ({
    formData,
    fechaInvalida,
    cuentasDisponibles,
    onChange,
    onSeleccionarCuenta,
}) => {
    return (
        <div className="max-w-3xl mx-auto animate-fade-in-up">
            <div className="mb-8 p-6 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800">
                <label htmlFor="factura-fecha-vencimiento" className="block font-bold text-slate-700 dark:text-slate-300 mb-2">
                    Fecha Vencimiento Factura
                </label>
                <input
                    id="factura-fecha-vencimiento"
                    type="date"
                    name="fechaVencimiento"
                    value={formData.fechaVencimiento}
                    onChange={onChange}
                    className={`w-full border rounded-lg p-3 text-lg outline-none focus:ring-2 ${
                        fechaInvalida
                            ? 'border-red-500 bg-red-50 ring-red-200'
                            : 'border-slate-300 dark:border-slate-600 focus:ring-blue-500 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200'
                    }`}
                />
                {fechaInvalida && (
                    <p className="text-red-600 text-sm font-bold mt-2">
                        <i className="fas fa-exclamation-triangle mr-1"></i>
                        La fecha de vencimiento no puede ser anterior a la emisión.
                    </p>
                )}
            </div>

            <h3 className="font-bold text-slate-800 dark:text-slate-200 text-lg mb-4 pl-1">
                Seleccionar Cuenta de Pago (Destino)
            </h3>
            <div className="space-y-3">
                {cuentasDisponibles.length > 0 ? (
                    cuentasDisponibles.map(cta => (
                        <div
                            key={cta.id}
                            role="button"
                            tabIndex={0}
                            onClick={() => onSeleccionarCuenta(cta.id)}
                            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onSeleccionarCuenta(cta.id); } }}
                            className={`p-5 border rounded-xl cursor-pointer flex justify-between items-center transition-all ${
                                formData.cuentaBancariaId === cta.id
                                    ? 'bg-blue-50 border-blue-500 ring-1 ring-blue-500 shadow-md'
                                    : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                            }`}
                        >
                            <div className="flex items-center gap-4">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                                    formData.cuentaBancariaId === cta.id
                                        ? 'bg-blue-200 text-blue-700'
                                        : 'bg-slate-100 dark:bg-slate-700 text-slate-400'
                                }`}>
                                    <i className="fas fa-university"></i>
                                </div>
                                <div>
                                    <p className="font-bold text-slate-800 dark:text-slate-200 text-lg">{cta.banco}</p>
                                    <p className="text-sm text-slate-500 dark:text-slate-400 font-mono tracking-wide">{cta.numero_cuenta}</p>
                                </div>
                            </div>
                            <span className="text-xs bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 px-3 py-1 rounded-full font-bold text-slate-500 dark:text-slate-400">
                                {cta.tipo_cuenta || 'Vista/Corriente'}
                            </span>
                        </div>
                    ))
                ) : (
                    <div className="text-slate-400 text-center italic border-2 border-dashed border-slate-200 dark:border-slate-700 p-8 rounded-xl bg-slate-50 dark:bg-slate-900">
                        <i className="fas fa-inbox text-3xl mb-2 opacity-50"></i>
                        <p>Este proveedor no tiene cuentas registradas.</p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default RegistroFacturaPaso2;
