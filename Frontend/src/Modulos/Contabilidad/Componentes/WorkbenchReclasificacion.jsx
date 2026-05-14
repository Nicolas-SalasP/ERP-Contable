import React from 'react';
import BuscadorCuentasReclasificar from './BuscadorCuentasReclasificar';

const formatCurrency = (amount) =>
    new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);

const CUENTAS_BLOQUEADAS = ['110001', '210101', '210102'];
const esBloqueada = (codigoCuenta) => CUENTAS_BLOQUEADAS.includes(codigoCuenta);

const WorkbenchReclasificacion = ({
    facturaActiva,
    asientoReclasificacion,
    loadingReclasificacion,
    cuentasPlan,
    formCambio,
    onFormCambioChange,
    onCancelar,
    onConfirmar,
    onIntentarBloqueada,
}) => {
    const handleSeleccionCuenta = (val) => {
        onFormCambioChange({ ...formCambio, nuevaCuenta: val });
    };

    return (
        <div className="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-visible animate-fade-in flex flex-col">
            {/* Header */}
            <div className="bg-slate-50 p-4 md:p-8 border-b border-slate-200 rounded-t-2xl">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h2 className="text-xl font-black text-slate-800">
                            Asiento Contable N° {facturaActiva?.codigo_asiento}
                        </h2>
                        <p className="text-sm text-slate-500 font-medium">
                            {facturaActiva?.tipo_documento === 'NOTA_CREDITO' ? 'NC' : 'Factura'} N° {facturaActiva?.numero_factura} - {facturaActiva?.proveedor?.razon_social}
                        </p>
                    </div>
                    <button
                        onClick={onCancelar}
                        className="w-full md:w-auto text-slate-500 hover:text-red-500 transition-colors px-4 py-2.5 bg-white rounded-lg border border-slate-200 shadow-sm font-bold text-xs uppercase tracking-wide flex items-center justify-center gap-1.5"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancelar
                    </button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 bg-white p-4 md:p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                            Fecha del Ajuste
                        </label>
                        <input
                            type="date"
                            className="w-full border border-slate-300 rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 font-medium text-slate-700 transition-all text-sm"
                            value={formCambio.fechaContableCambio}
                            min={facturaActiva?.fecha_emision}
                            onChange={(e) => onFormCambioChange({ ...formCambio, fechaContableCambio: e.target.value })}
                        />
                        <span className="text-[10px] text-slate-400 mt-1.5 block leading-tight">
                            El reverso y el cargo quedarán en esta fecha.
                        </span>
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                            Glosa de Auditoría
                        </label>
                        <input
                            type="text"
                            className="w-full border border-slate-300 rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 font-medium text-slate-700 transition-all text-sm"
                            value={formCambio.nuevaGlosa}
                            onChange={(e) => onFormCambioChange({ ...formCambio, nuevaGlosa: e.target.value })}
                            placeholder="Motivo del cambio..."
                        />
                        <span className="text-[10px] text-slate-400 mt-1.5 block leading-tight">
                            Justificación obligatoria para el historial.
                        </span>
                    </div>
                </div>
            </div>

            {/* Detalles del asiento */}
            <div className="p-4 md:p-8 flex-1 bg-white">
                <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">
                    Líneas del Asiento Original
                </h3>

                {loadingReclasificacion ? (
                    <div className="text-center p-10 text-slate-400">
                        <svg className="animate-spin w-8 h-8 mx-auto mb-3 text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p>Cargando detalles...</p>
                    </div>
                ) : (
                    <div className="border border-slate-200 rounded-xl overflow-visible shadow-sm">
                        {/* Vista desktop: tabla */}
                        <div className="hidden md:block overflow-visible pb-24">
                            <table className="w-full text-left">
                                <thead className="bg-slate-900 text-white text-xs uppercase tracking-wider font-bold">
                                    <tr>
                                        <th className="p-4 w-1/3 first:rounded-tl-xl">Cuenta Original</th>
                                        <th className="p-4 text-right w-32">Debe</th>
                                        <th className="p-4 text-right w-32 border-r border-slate-700">Haber</th>
                                        <th className="p-4 bg-slate-800 last:rounded-tr-xl">Nueva Imputación (Buscador)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-sm font-medium">
                                    {asientoReclasificacion?.detalles?.map((linea, index) => {
                                        const isBloqueada = esBloqueada(linea.cuenta_contable);
                                        return (
                                            <tr
                                                key={index}
                                                className={isBloqueada ? 'bg-slate-50 opacity-80' : 'bg-white hover:bg-blue-50/20'}
                                            >
                                                <td className="p-4">
                                                    <div className="text-slate-800 font-bold">{linea.nombre_cuenta}</div>
                                                    <div className="text-xs text-slate-500 font-mono mt-0.5 bg-white border border-slate-200 px-2 py-0.5 rounded w-max">
                                                        {linea.cuenta_contable}
                                                    </div>
                                                </td>
                                                <td className="p-4 text-right font-mono text-emerald-600">
                                                    {parseFloat(linea.debe) > 0 ? formatCurrency(linea.debe) : '-'}
                                                </td>
                                                <td className="p-4 text-right font-mono text-red-600 border-r border-slate-100">
                                                    {parseFloat(linea.haber) > 0 ? formatCurrency(linea.haber) : '-'}
                                                </td>
                                                <td className="p-4">
                                                    {isBloqueada ? (
                                                        <div
                                                            onClick={onIntentarBloqueada}
                                                            className="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase cursor-pointer hover:text-red-500 transition-colors bg-slate-100 border border-slate-200 w-fit px-3 py-2 rounded-lg"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                            </svg>
                                                            Cuenta Restringida
                                                        </div>
                                                    ) : (
                                                        <BuscadorCuentasReclasificar
                                                            cuentas={cuentasPlan}
                                                            valor={formCambio.nuevaCuenta}
                                                            onChange={handleSeleccionCuenta}
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Vista mobile: cards */}
                        <div className="md:hidden flex flex-col divide-y divide-slate-100 pb-10">
                            {asientoReclasificacion?.detalles?.map((linea, index) => {
                                const isBloqueada = esBloqueada(linea.cuenta_contable);
                                return (
                                    <div
                                        key={index}
                                        className={`p-4 flex flex-col gap-3 ${isBloqueada ? 'bg-slate-50' : 'bg-white'}`}
                                    >
                                        <div className="flex justify-between items-start">
                                            <div>
                                                <div className="text-slate-800 font-bold text-sm">{linea.nombre_cuenta}</div>
                                                <div className="text-xs text-slate-500 font-mono mt-1">{linea.cuenta_contable}</div>
                                            </div>
                                            <div className="text-right">
                                                {parseFloat(linea.debe) > 0 && (
                                                    <div className="text-emerald-600 font-mono font-bold text-sm">+{formatCurrency(linea.debe)}</div>
                                                )}
                                                {parseFloat(linea.haber) > 0 && (
                                                    <div className="text-red-600 font-mono font-bold text-sm">-{formatCurrency(linea.haber)}</div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="pt-2 border-t border-slate-100 overflow-visible relative">
                                            <p className="text-[10px] font-bold text-slate-400 uppercase mb-1.5">Mover a cuenta:</p>
                                            {isBloqueada ? (
                                                <div
                                                    onClick={onIntentarBloqueada}
                                                    className="flex items-center justify-center gap-2 text-slate-400 text-xs font-bold uppercase cursor-pointer bg-slate-100 border border-slate-200 w-full py-2.5 rounded-lg"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                    </svg>
                                                    Restringida
                                                </div>
                                            ) : (
                                                <BuscadorCuentasReclasificar
                                                    cuentas={cuentasPlan}
                                                    valor={formCambio.nuevaCuenta}
                                                    onChange={handleSeleccionCuenta}
                                                />
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            {/* Footer con boton confirmar */}
            <div className="bg-slate-50 p-4 md:p-6 border-t border-slate-200 mt-auto rounded-b-2xl">
                <button
                    onClick={onConfirmar}
                    disabled={!formCambio.nuevaCuenta}
                    className="w-full md:w-auto md:float-right px-6 md:px-10 py-3.5 bg-emerald-600 text-white rounded-xl font-black shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 hover:shadow-emerald-600/40 disabled:opacity-50 disabled:shadow-none transition-all flex items-center justify-center gap-2"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    Confirmar Ajuste
                </button>
                <div className="clear-both"></div>
            </div>
        </div>
    );
};

export default WorkbenchReclasificacion;
