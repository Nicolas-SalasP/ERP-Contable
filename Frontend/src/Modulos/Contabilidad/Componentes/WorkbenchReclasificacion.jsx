import React from 'react';
import BuscadorCuentasReclasificar from './BuscadorCuentasReclasificar';
import { X, Lock, Download } from 'lucide-react';
import { formatearMoneda } from '../../../Utilidades/formato';

const formatCurrency = formatearMoneda;

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
        <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-visible animate-fade-in flex flex-col">
            <div className="bg-slate-50 dark:bg-slate-900 p-4 md:p-8 border-b border-slate-200 dark:border-slate-700 rounded-t-2xl">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h2 className="text-xl font-black text-slate-800 dark:text-slate-200">
                            Asiento Contable N° {facturaActiva?.codigo_asiento}
                        </h2>
                        <p className="text-sm text-slate-500 dark:text-slate-400 font-medium">
                            {facturaActiva?.tipo_documento === 'NOTA_CREDITO' ? 'NC' : 'Factura'} N° {facturaActiva?.numero_factura} - {facturaActiva?.proveedor?.razon_social}
                        </p>
                    </div>
                    <button
                        onClick={onCancelar}
                        className="w-full md:w-auto text-slate-500 dark:text-slate-400 hover:text-red-500 transition-colors px-4 py-2.5 bg-white dark:bg-slate-700 rounded-lg border border-slate-200 dark:border-slate-600 shadow-sm font-bold text-xs uppercase tracking-wide flex items-center justify-center gap-1.5"
                    >
                        <X size={16} strokeWidth={1.75} />
                        Cancelar
                    </button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 bg-white dark:bg-slate-800 p-4 md:p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div>
                        <label htmlFor="reclasificacion-fecha-ajuste" className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">
                            Fecha del Ajuste
                        </label>
                        <input
                            id="reclasificacion-fecha-ajuste"
                            type="date"
                            className="w-full border border-slate-300 dark:border-slate-600 rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 font-medium text-slate-700 dark:text-slate-300 transition-all text-sm bg-white dark:bg-slate-700"
                            value={formCambio.fechaContableCambio}
                            min={facturaActiva?.fecha_emision}
                            onChange={(e) => onFormCambioChange({ ...formCambio, fechaContableCambio: e.target.value })}
                        />
                        <span className="text-[10px] text-slate-400 mt-1.5 block leading-tight">
                            El reverso y el cargo quedarán en esta fecha.
                        </span>
                    </div>
                    <div>
                        <label htmlFor="reclasificacion-glosa" className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">
                            Glosa de Auditoría
                        </label>
                        <input
                            id="reclasificacion-glosa"
                            type="text"
                            className="w-full border border-slate-300 dark:border-slate-600 rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 font-medium text-slate-700 dark:text-slate-300 transition-all text-sm bg-white dark:bg-slate-700"
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

            <div className="p-4 md:p-8 flex-1 bg-white dark:bg-slate-800">
                <h3 className="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wide mb-4">
                    Líneas del Asiento Original
                </h3>

                {loadingReclasificacion ? (
                    <div className="text-center p-10 text-slate-400">
                        <svg className="animate-spin w-8 h-8 mx-auto mb-3 text-blue-500" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <p>Cargando detalles...</p>
                    </div>
                ) : (
                    <div className="border border-slate-200 dark:border-slate-700 rounded-xl overflow-visible shadow-sm">
                        <div className="hidden md:block pb-24">
                            <div className="overflow-x-auto">
                            <table className="w-full text-left">
                                <thead className="sticky top-0 z-10 bg-slate-900 text-white text-xs uppercase tracking-wider font-bold">
                                    <tr>
                                        <th className="p-4 w-1/3 first:rounded-tl-xl">Cuenta Original</th>
                                        <th className="p-4 text-right w-32">Debe</th>
                                        <th className="p-4 text-right w-32 border-r border-slate-700">Haber</th>
                                        <th className="p-4 bg-slate-800 last:rounded-tr-xl">Nueva Imputación (Buscador)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-700 text-sm font-medium">
                                    {asientoReclasificacion?.detalles?.map((linea, index) => {
                                        const isBloqueada = esBloqueada(linea.cuenta_contable);
                                        return (
                                            <tr
                                                key={index}
                                                className={isBloqueada ? 'bg-slate-50 dark:bg-slate-900 opacity-80' : 'bg-white dark:bg-slate-800 hover:bg-blue-50/20'}
                                            >
                                                <td className="p-4">
                                                    <div className="text-slate-800 dark:text-slate-200 font-bold">{linea.nombre_cuenta}</div>
                                                    <div className="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 px-2 py-0.5 rounded w-max">
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
                                                            role="button"
                                                            tabIndex={0}
                                                            onClick={onIntentarBloqueada}
                                                            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onIntentarBloqueada(); } }}
                                                            className="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase cursor-pointer hover:text-red-500 transition-colors bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 w-fit px-3 py-2 rounded-lg"
                                                        >
                                                            <Lock size={16} strokeWidth={1.75} />
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
                        </div>

                        <div className="md:hidden flex flex-col divide-y divide-slate-100 dark:divide-slate-700 pb-10">
                            {asientoReclasificacion?.detalles?.map((linea, index) => {
                                const isBloqueada = esBloqueada(linea.cuenta_contable);
                                return (
                                    <div
                                        key={index}
                                        className={`p-4 flex flex-col gap-3 ${isBloqueada ? 'bg-slate-50 dark:bg-slate-900' : 'bg-white dark:bg-slate-800'}`}
                                    >
                                        <div className="flex justify-between items-start">
                                            <div>
                                                <div className="text-slate-800 dark:text-slate-200 font-bold text-sm">{linea.nombre_cuenta}</div>
                                                <div className="text-xs text-slate-500 dark:text-slate-400 font-mono mt-1">{linea.cuenta_contable}</div>
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
                                        <div className="pt-2 border-t border-slate-100 dark:border-slate-700 overflow-visible relative">
                                            <p className="text-[10px] font-bold text-slate-400 uppercase mb-1.5">Mover a cuenta:</p>
                                            {isBloqueada ? (
                                                <div
                                                    role="button"
                                                    tabIndex={0}
                                                    onClick={onIntentarBloqueada}
                                                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onIntentarBloqueada(); } }}
                                                    className="flex items-center justify-center gap-2 text-slate-400 text-xs font-bold uppercase cursor-pointer bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 w-full py-2.5 rounded-lg"
                                                >
                                                    <Lock size={16} strokeWidth={1.75} />
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

            <div className="bg-slate-50 dark:bg-slate-900 p-4 md:p-6 border-t border-slate-200 dark:border-slate-700 mt-auto rounded-b-2xl">
                <button
                    onClick={onConfirmar}
                    disabled={!formCambio.nuevaCuenta}
                    className="w-full md:w-auto md:float-right px-6 md:px-10 py-3.5 bg-emerald-600 text-white rounded-xl font-black shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 hover:shadow-emerald-600/40 disabled:opacity-50 disabled:shadow-none transition-all flex items-center justify-center gap-2"
                >
                    <Download size={20} strokeWidth={1.75} />
                    Confirmar Ajuste
                </button>
                <div className="clear-both"></div>
            </div>
        </div>
    );
};

export default WorkbenchReclasificacion;
