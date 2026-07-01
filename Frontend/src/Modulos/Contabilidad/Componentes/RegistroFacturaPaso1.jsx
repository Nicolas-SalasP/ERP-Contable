import React from 'react';
import { X } from 'lucide-react';

const TIPOS_GASTO_ART59 = [
    { value: 'intereses',          label: 'Intereses (4%)' },
    { value: 'regalias',           label: 'Regalías (30%)' },
    { value: 'servicios_tecnicos', label: 'Servicios Técnicos (15%)' },
    { value: 'honorarios',         label: 'Honorarios (15%)' },
    { value: 'otros',              label: 'Otros (35%)' },
];

const RegistroFacturaPaso1 = ({
    formData,
    busqueda,
    sugerencias,
    mostrarSugerencias,
    searchRef,
    onBusquedaChange,
    onMostrarSugerencias,
    onSeleccionarProveedor,
    onLimpiarProveedor,
    onChange,
    onMontoChange,
    onTipoCambioChange,
    onMontoOrigenChange,
}) => {
    return (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 animate-fade-in-up">
            <div className="flex flex-col gap-4">
                <label className="text-sm font-bold text-slate-700 uppercase tracking-wide">
                    Proveedor
                </label>

                {!formData.proveedorId ? (
                    <div className="relative z-20" ref={searchRef}>
                        <div className="relative group">
                            <span className="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i className="fas fa-search"></i>
                            </span>
                            <input
                                type="text"
                                placeholder="Buscar RUT, Razón Social..."
                                value={busqueda}
                                onChange={onBusquedaChange}
                                onFocus={() => { if (busqueda) onMostrarSugerencias(true); }}
                                className="w-full pl-11 pr-4 py-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm text-lg text-slate-800 dark:text-slate-200"
                                autoFocus
                            />
                        </div>

                        {mostrarSugerencias && (
                            <div className="absolute w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 mt-2 rounded-xl shadow-2xl max-h-80 overflow-y-auto z-50">
                                {sugerencias.length > 0 ? sugerencias.map(p => (
                                    <div
                                        key={p.id}
                                        onClick={() => onSeleccionarProveedor(p)}
                                        className="p-4 hover:bg-blue-50 cursor-pointer border-b last:border-0 border-slate-100 dark:border-slate-800 transition-colors group"
                                    >
                                        <p className="font-bold text-slate-800 dark:text-slate-200 group-hover:text-blue-700">
                                            {p.razon_social}
                                        </p>
                                        <div className="flex justify-between items-center mt-1">
                                            <span className="text-sm text-slate-500 dark:text-slate-400 font-mono">{p.rut}</span>
                                            <span className="text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 px-2 py-0.5 rounded group-hover:bg-blue-100 group-hover:text-blue-700 transition-colors">
                                                #{p.codigo_interno}
                                            </span>
                                        </div>
                                    </div>
                                )) : (
                                    <div className="p-6 text-center text-slate-400">
                                        No se encontraron resultados para "{busqueda}"
                                    </div>
                                )}
                            </div>
                        )}
                        <p className="text-xs text-slate-400 mt-2 ml-1">
                            * Escriba para buscar en la base de datos de proveedores.
                        </p>
                    </div>
                ) : (
                    <div className="bg-blue-50 border border-blue-200 rounded-xl p-6 relative group hover:shadow-md transition-all">
                        <div className="absolute top-0 left-0 w-1.5 h-full bg-blue-500 rounded-l-xl"></div>
                        <div className="flex justify-between items-start">
                            <div>
                                <span className="bg-blue-200 text-blue-800 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wide">
                                    Proveedor Seleccionado
                                </span>
                                <h3 className="font-bold text-slate-800 text-xl mt-2">
                                    {formData.proveedorNombre}
                                </h3>
                                <p className="text-slate-600 font-mono text-sm mt-1">{formData.rut}</p>
                                <div className="flex gap-2 mt-4">
                                    <span className="px-3 py-1 bg-white border border-blue-100 rounded-md text-xs font-bold text-slate-600 shadow-sm">
                                        {formData.pais}
                                    </span>
                                    <span className="px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-md text-xs font-bold shadow-sm">
                                        {formData.moneda}
                                    </span>
                                </div>
                            </div>
                            <button
                                onClick={onLimpiarProveedor}
                                className="text-slate-400 hover:text-red-500 hover:bg-white p-2 rounded-full transition-all shadow-sm"
                                title="Cambiar proveedor"
                            >
                                <X size={20} strokeWidth={1.75} />
                            </button>
                        </div>
                    </div>
                )}

                {formData.pais === 'Extranjero' && (
                    <div className="mt-4 bg-amber-50 border border-amber-200 dark:bg-amber-900/20 dark:border-amber-700 rounded-xl p-4 space-y-3">
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wide">
                                Documento del Exterior — Conversion a CLP
                            </span>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase">
                                    Monto en {formData.moneda}
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="9999999999"
                                    className="w-full border border-amber-300 dark:border-amber-600 rounded-lg p-2.5 text-sm font-mono outline-none focus:ring-2 focus:ring-amber-400/30 focus:border-amber-500 bg-white dark:bg-slate-700"
                                    placeholder="Ej: 23.80"
                                    value={formData.montoOrigenDivisa}
                                    onChange={e => onMontoOrigenChange(e.target.value)}
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase">
                                    Tipo de Cambio (CLP/{formData.moneda})
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="99999"
                                    className="w-full border border-amber-300 dark:border-amber-600 rounded-lg p-2.5 text-sm font-mono outline-none focus:ring-2 focus:ring-amber-400/30 focus:border-amber-500 bg-white dark:bg-slate-700"
                                    placeholder="Ej: 950.00"
                                    value={formData.tipoCambio}
                                    onChange={e => onTipoCambioChange(e.target.value)}
                                />
                                <p className="text-[10px] text-slate-400 mt-1">Dolar observado SII de la fecha de emision</p>
                            </div>
                        </div>
                        {formData.montoOrigenDivisa && formData.tipoCambio && (
                            <div className="bg-amber-100 dark:bg-amber-900/40 rounded-lg p-3 text-center">
                                <span className="text-xs text-amber-600 dark:text-amber-400 font-bold uppercase">Total en CLP: </span>
                                <span className="font-mono font-bold text-amber-800 dark:text-amber-200 text-lg">
                                    {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(
                                        Math.round(parseFloat(formData.montoOrigenDivisa) * parseFloat(formData.tipoCambio))
                                    )}
                                </span>
                            </div>
                        )}

                        <div className="border-t border-amber-200 dark:border-amber-700 pt-3 mt-1">
                            <p className="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wide mb-2">
                                Retención Art. 59 LIR (opcional)
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase">Tipo de Gasto</label>
                                    <select
                                        name="tipoGastoArt59"
                                        value={formData.tipoGastoArt59}
                                        onChange={onChange}
                                        className="w-full border border-amber-300 dark:border-amber-600 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-400/30 focus:border-amber-500 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                                    >
                                        <option value="">Sin retención</option>
                                        {TIPOS_GASTO_ART59.map(t => (
                                            <option key={t.value} value={t.value}>{t.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1 uppercase">Monto Retención (CLP)</label>
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        name="retencionArt59"
                                        value={formData.retencionArt59}
                                        onChange={onChange}
                                        disabled={!formData.tipoGastoArt59}
                                        className="w-full border border-amber-300 dark:border-amber-600 rounded-lg p-2.5 text-sm font-mono outline-none focus:ring-2 focus:ring-amber-400/30 focus:border-amber-500 bg-white dark:bg-slate-700 disabled:bg-slate-100 dark:disabled:bg-slate-800"
                                        placeholder="Ej: 150000"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
                            Tipo Documento
                        </label>
                        <select
                            name="tipoDocumento"
                            value={formData.tipoDocumento}
                            onChange={onChange}
                            className="w-full border border-slate-300 dark:border-slate-600 rounded-lg py-3 px-3 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700"
                        >
                            <option value="FACTURA">Factura</option>
                            <option value="BOLETA">Boleta</option>
                            <option value="NOTA_CREDITO">Nota de Crédito</option>
                            <option value="NOTA_DEBITO">Nota de Débito</option>
                        </select>
                        {/* AUDITORIA FE-BE: para NOTA_CREDITO el backend valida que monto <= factura original */}
                        {formData.tipoDocumento === 'NOTA_CREDITO' && (
                            <div className="mt-2">
                                <label className="block text-xs font-bold text-amber-700 mb-1">
                                    ID de Factura Original (requerido para NC)
                                </label>
                                <input
                                    type="number"
                                    name="factura_referencia_id"
                                    value={formData.factura_referencia_id || ''}
                                    onChange={onChange}
                                    placeholder="Ej: 123"
                                    className="w-full border border-amber-300 rounded-lg py-2 px-2 text-sm focus:ring-2 focus:ring-amber-500 outline-none bg-amber-50"
                                />
                                <p className="text-[10px] text-amber-700 mt-1">
                                    El monto de la NC no puede ser mayor al de la factura original.
                                </p>
                            </div>
                        )}
                    </div>
                    <div>
                        <label className="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
                            N° Documento
                        </label>
                        <input
                            type="text"
                            name="numeroFactura"
                            value={formData.numeroFactura}
                            onChange={onChange}
                            placeholder="Ej: 123456"
                            maxLength={50}
                            className="w-full border border-slate-300 dark:border-slate-600 rounded-lg py-3 px-3 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700"
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide" title="Fecha que aparece en el PDF">
                            Emisión (Doc)
                        </label>
                        <input
                            type="date"
                            name="fechaEmision"
                            value={formData.fechaEmision}
                            onChange={onChange}
                            className="w-full border border-slate-300 dark:border-slate-600 rounded-lg py-3 px-3 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-slate-600 dark:text-slate-400 font-medium bg-white dark:bg-slate-700"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-blue-700 mb-2 uppercase tracking-wide" title="Fecha que irá al Libro Diario">
                            Contable (Libro)
                        </label>
                        <input
                            type="date"
                            name="fechaContable"
                            value={formData.fechaContable}
                            onChange={onChange}
                            className="w-full border border-blue-300 bg-blue-50 rounded-lg py-3 px-3 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-slate-600 font-medium"
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
                        Monto Bruto Total
                    </label>
                    <div className="relative rounded-lg shadow-sm">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <span className="text-slate-400 font-bold text-xl">$</span>
                        </div>
                        <input
                            type="text"
                            name="monto_total"
                            value={formData.montoVisual}
                            onChange={onMontoChange}
                            readOnly={formData.esDocumentoExterior}
                            placeholder={formData.esDocumentoExterior ? 'Se calcula desde los campos superiores' : '0'}
                            maxLength={15}
                            className={`block w-full !pl-10 rounded-lg border py-4 pl-10 pr-12 text-2xl font-bold outline-none tracking-wide ${
                                formData.esDocumentoExterior
                                    ? 'border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-200 cursor-not-allowed'
                                    : 'border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700'
                            }`}
                        />
                        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4">
                            <span className="text-slate-400 text-sm font-bold">CLP</span>
                        </div>
                    </div>
                    <p className="text-xs text-slate-400 mt-2 text-right">
                        {formData.esDocumentoExterior
                            ? 'Calculado automaticamente desde monto origen × tipo de cambio.'
                            : 'Ingresa el valor total con IVA incluido.'}
                    </p>
                </div>
            </div>
        </div>
    );
};

export default RegistroFacturaPaso1;
