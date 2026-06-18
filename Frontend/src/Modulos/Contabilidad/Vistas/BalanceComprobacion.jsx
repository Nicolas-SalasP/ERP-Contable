import React, { useState } from 'react';
import { api } from '../../../Configuracion/api';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import * as XLSX from "@e965/xlsx";
import ModalGenerico from '../../../Componentes/ModalGenerico';
import { logger } from '../../../Configuracion/logger';
import { Download } from 'lucide-react';

const formatMoney = (amount) => {
    if (!amount || parseFloat(amount) === 0) return '';
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
};

const hoy = new Date().toISOString().split('T')[0];
const primerDiaMes = new Date().toISOString().slice(0, 7) + '-01';

const BalanceComprobacion = () => {
    const [filtros, setFiltros] = useState({
        fecha_inicio: primerDiaMes,
        fecha_fin: hoy,
    });

    const [resultado, setResultado] = useState(null);
    const [loading, setLoading] = useState(false);
    const [notificacion, setNotificacion] = useState({
        show: false,
        title: '',
        message: '',
        type: 'info',
    });

    const consultar = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                fecha_inicio: filtros.fecha_inicio,
                fecha_fin: filtros.fecha_fin,
                filtro: 1,
            }).toString();

            const res = await api.get(`/contabilidad/reportes/balance-comprobacion?${params}`);

            if (res.success) {
                setResultado(res.data);
            } else {
                setResultado(null);
                setNotificacion({
                    show: true,
                    title: 'Error',
                    message: res.message || 'No se pudieron cargar los datos.',
                    type: 'danger',
                });
            }
        } catch (error) {
            logger.error('Error cargando balance de comprobacion', error);
            setResultado(null);
            setNotificacion({
                show: true,
                title: 'Error',
                message: 'No se pudo conectar con el servidor.',
                type: 'danger',
            });
        } finally {
            setLoading(false);
        }
    };

    const exportarExcel = () => {
        if (!resultado || resultado.cuentas.length === 0) {
            setNotificacion({
                show: true,
                title: 'Sin datos',
                message: 'No hay cuentas para exportar en el rango seleccionado.',
                type: 'warning',
            });
            return;
        }

        const datosExcel = resultado.cuentas.map(c => ({
            'Código': c.codigo,
            'Cuenta': c.nombre,
            'Tipo': c.tipo,
            'Ant. Debe': parseFloat(c.anterior.debe) || 0,
            'Ant. Haber': parseFloat(c.anterior.haber) || 0,
            'Ant. Saldo Deudor': parseFloat(c.anterior.saldo_deudor) || 0,
            'Ant. Saldo Acreedor': parseFloat(c.anterior.saldo_acreedor) || 0,
            'Mens. Debe': parseFloat(c.mensual.debe) || 0,
            'Mens. Haber': parseFloat(c.mensual.haber) || 0,
            'Mens. Saldo Deudor': parseFloat(c.mensual.saldo_deudor) || 0,
            'Mens. Saldo Acreedor': parseFloat(c.mensual.saldo_acreedor) || 0,
            'Acum. Debe': parseFloat(c.acumulado.debe) || 0,
            'Acum. Haber': parseFloat(c.acumulado.haber) || 0,
            'Acum. Saldo Deudor': parseFloat(c.acumulado.saldo_deudor) || 0,
            'Acum. Saldo Acreedor': parseFloat(c.acumulado.saldo_acreedor) || 0,
        }));

        const worksheet = XLSX.utils.json_to_sheet(datosExcel);
        worksheet['!cols'] = [
            { wch: 10 }, { wch: 35 }, { wch: 12 },
            { wch: 14 }, { wch: 14 }, { wch: 16 }, { wch: 16 },
            { wch: 14 }, { wch: 14 }, { wch: 16 }, { wch: 16 },
            { wch: 14 }, { wch: 14 }, { wch: 16 }, { wch: 16 },
        ];
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Balance Comprobacion');
        XLSX.writeFile(workbook, `Balance_Comprobacion_${filtros.fecha_inicio}_${filtros.fecha_fin}.xlsx`);
    };

    const totales = resultado?.totales;
    const descuadrado = totales && (
        Math.abs(totales.mensual.debe - totales.mensual.haber) > 1 ||
        Math.abs(totales.acumulado.saldo_deudor - totales.acumulado.saldo_acreedor) > 1
    );

    const celNum = (valor, colorPos, bgPos) => {
        const v = parseFloat(valor);
        if (!v || v === 0) return <span className="text-slate-300">-</span>;
        return (
            <span className={bgPos ? `${bgPos} ${colorPos}` : colorPos}>
                {formatMoney(v)}
            </span>
        );
    };

    return (
        <div className="max-w-full mx-auto p-6 font-sans text-slate-800 dark:text-slate-200">

            <ModalGenerico
                isOpen={notificacion.show}
                onClose={() => setNotificacion({ ...notificacion, show: false })}
                title={notificacion.title}
                message={notificacion.message}
                type={notificacion.type}
            />

            <div className="flex justify-between items-center mb-6 flex-wrap gap-3">
                <div className="flex items-center gap-3 flex-wrap">
                    <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">Balance de Comprobacion y Saldos</h1>
                    <AyudaModulo moduloId="balanceComprobacion" size={26} />
                </div>
            </div>

            <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm mb-6 flex flex-wrap gap-4 items-end">
                <div>
                    <label className="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Fecha Inicio</label>
                    <input
                        type="date"
                        className="border border-slate-300 dark:border-slate-600 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                        value={filtros.fecha_inicio}
                        onChange={e => setFiltros({ ...filtros, fecha_inicio: e.target.value })}
                    />
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Fecha Fin</label>
                    <input
                        type="date"
                        className="border border-slate-300 dark:border-slate-600 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200"
                        value={filtros.fecha_fin}
                        onChange={e => setFiltros({ ...filtros, fecha_fin: e.target.value })}
                    />
                </div>

                <div className="flex gap-2">
                    <button
                        onClick={consultar}
                        className="bg-blue-600 text-white px-5 py-2 rounded-lg font-bold hover:bg-blue-700 text-sm shadow-sm transition-all active:scale-95"
                    >
                        Consultar
                    </button>
                    <button
                        onClick={exportarExcel}
                        className="bg-emerald-600 text-white px-5 py-2 rounded-lg font-bold hover:bg-emerald-700 text-sm flex items-center gap-2 shadow-sm transition-all active:scale-95"
                    >
                        <Download size={16} strokeWidth={1.75} />
                        Excel
                    </button>
                </div>
            </div>

            {descuadrado && (
                <div className="mb-4 bg-red-50 border border-red-300 rounded-lg px-4 py-3 text-red-700 font-semibold text-sm">
                    Advertencia: Balance descuadrado — Debe diferente a Haber o Saldo Deudor diferente a Saldo Acreedor. Revise los asientos del periodo.
                </div>
            )}

            <div className="bg-white dark:bg-slate-800 rounded-lg shadow border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead className="text-[10px] uppercase font-bold border-b border-slate-200">
                            <tr>
                                <th rowSpan="2" className="px-3 py-2 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 border-r border-slate-200 dark:border-slate-600 align-middle whitespace-nowrap">Codigo</th>
                                <th rowSpan="2" className="px-3 py-2 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 border-r border-slate-200 dark:border-slate-600 align-middle whitespace-nowrap">Cuenta</th>
                                <th rowSpan="2" className="px-3 py-2 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 border-r border-slate-200 dark:border-slate-600 align-middle whitespace-nowrap">Tipo</th>
                                <th colSpan="2" className="px-3 py-2 bg-amber-50 text-amber-700 text-center border-r border-amber-200 whitespace-nowrap">Periodo Anterior</th>
                                <th colSpan="4" className="px-3 py-2 bg-blue-50 text-blue-700 text-center border-r border-blue-200 whitespace-nowrap">Movimientos Periodo</th>
                                <th colSpan="2" className="px-3 py-2 bg-emerald-50 text-emerald-700 text-center whitespace-nowrap">Acumulado Año</th>
                            </tr>
                            <tr>
                                <th className="px-3 py-2 bg-amber-50 text-amber-600 text-right border-r border-amber-100 whitespace-nowrap">SD</th>
                                <th className="px-3 py-2 bg-amber-50 text-amber-600 text-right border-r border-slate-200 whitespace-nowrap">SA</th>
                                <th className="px-3 py-2 bg-blue-50 text-blue-600 text-right border-r border-blue-100 whitespace-nowrap">Debe</th>
                                <th className="px-3 py-2 bg-blue-50 text-blue-600 text-right border-r border-blue-100 whitespace-nowrap">Haber</th>
                                <th className="px-3 py-2 bg-blue-50 text-blue-600 text-right border-r border-blue-100 whitespace-nowrap">SD</th>
                                <th className="px-3 py-2 bg-blue-50 text-blue-600 text-right border-r border-slate-200 whitespace-nowrap">SA</th>
                                <th className="px-3 py-2 bg-emerald-50 text-emerald-600 text-right border-r border-emerald-100 whitespace-nowrap">SD</th>
                                <th className="px-3 py-2 bg-emerald-50 text-emerald-600 text-right whitespace-nowrap">SA</th>
                            </tr>
                        </thead>
                        <tbody className="text-xs divide-y divide-slate-100 dark:divide-slate-700">
                            {loading ? (
                                <tr>
                                    <td colSpan="11" className="p-8 text-center text-slate-400">
                                        <i className="fas fa-spinner fa-spin text-slate-300 text-2xl mb-2 block"></i>
                                        Cargando...
                                    </td>
                                </tr>
                            ) : !resultado ? (
                                <tr>
                                    <td colSpan="11" className="p-8 text-center text-slate-400">
                                        <i className="fas fa-table text-slate-300 text-2xl mb-2 block"></i>
                                        Seleccione un periodo y presione Consultar.
                                    </td>
                                </tr>
                            ) : resultado.cuentas.length === 0 ? (
                                <tr>
                                    <td colSpan="11" className="p-8 text-center text-slate-400">
                                        No hay cuentas con movimientos en el periodo seleccionado.
                                    </td>
                                </tr>
                            ) : (
                                resultado.cuentas.map((cuenta, idx) => (
                                    <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                        <td className="px-3 py-2 font-mono font-bold text-slate-600 dark:text-slate-400 border-r border-slate-100 dark:border-slate-700 whitespace-nowrap">
                                            {cuenta.codigo}
                                        </td>
                                        <td className="px-3 py-2 border-r border-slate-100 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                                            {cuenta.nombre}
                                        </td>
                                        <td className="px-3 py-2 border-r border-slate-100 dark:border-slate-700 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                            {cuenta.tipo}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono border-r border-amber-100 whitespace-nowrap">
                                            {celNum(cuenta.anterior.saldo_deudor, 'text-amber-700')}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono border-r border-slate-200 whitespace-nowrap">
                                            {celNum(cuenta.anterior.saldo_acreedor, 'text-amber-600')}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono border-r border-blue-100 whitespace-nowrap">
                                            {celNum(cuenta.mensual.debe, 'text-blue-700')}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono border-r border-blue-100 whitespace-nowrap">
                                            {celNum(cuenta.mensual.haber, 'text-blue-600')}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono border-r border-blue-100 whitespace-nowrap">
                                            {celNum(cuenta.mensual.saldo_deudor, 'text-blue-700', 'bg-blue-50')}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono border-r border-slate-200 whitespace-nowrap">
                                            {celNum(cuenta.mensual.saldo_acreedor, 'text-blue-600', 'bg-blue-50')}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono border-r border-emerald-100 whitespace-nowrap">
                                            {celNum(cuenta.acumulado.saldo_deudor, 'text-emerald-700', 'bg-emerald-50')}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono whitespace-nowrap">
                                            {celNum(cuenta.acumulado.saldo_acreedor, 'text-emerald-600', 'bg-emerald-50')}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        {totales && (
                            <tfoot>
                                <tr className={`text-xs font-bold border-t-2 border-slate-300 ${descuadrado ? 'bg-red-50 text-red-700' : 'text-slate-700'}`}>
                                    <td className="px-3 py-3 border-r border-slate-200" colSpan="3">
                                        TOTALES
                                    </td>
                                    <td className={`px-3 py-3 text-right font-mono border-r border-amber-100 whitespace-nowrap ${descuadrado ? '' : 'bg-amber-50 text-amber-700'}`}>
                                        {formatMoney(totales.anterior.saldo_deudor) || '-'}
                                    </td>
                                    <td className={`px-3 py-3 text-right font-mono border-r border-slate-200 whitespace-nowrap ${descuadrado ? '' : 'bg-amber-50 text-amber-600'}`}>
                                        {formatMoney(totales.anterior.saldo_acreedor) || '-'}
                                    </td>
                                    <td className={`px-3 py-3 text-right font-mono border-r border-blue-100 whitespace-nowrap ${descuadrado ? '' : 'bg-blue-50 text-blue-700'}`}>
                                        {formatMoney(totales.mensual.debe) || '-'}
                                    </td>
                                    <td className={`px-3 py-3 text-right font-mono border-r border-blue-100 whitespace-nowrap ${descuadrado ? '' : 'bg-blue-50 text-blue-600'}`}>
                                        {formatMoney(totales.mensual.haber) || '-'}
                                    </td>
                                    <td className={`px-3 py-3 text-right font-mono border-r border-blue-100 whitespace-nowrap ${descuadrado ? '' : 'bg-blue-50 text-blue-700'}`}>
                                        {formatMoney(totales.mensual.saldo_deudor) || '-'}
                                    </td>
                                    <td className={`px-3 py-3 text-right font-mono border-r border-slate-200 whitespace-nowrap ${descuadrado ? '' : 'bg-blue-50 text-blue-600'}`}>
                                        {formatMoney(totales.mensual.saldo_acreedor) || '-'}
                                    </td>
                                    <td className={`px-3 py-3 text-right font-mono border-r border-emerald-100 whitespace-nowrap ${descuadrado ? '' : 'bg-emerald-50 text-emerald-700'}`}>
                                        {formatMoney(totales.acumulado.saldo_deudor) || '-'}
                                    </td>
                                    <td className={`px-3 py-3 text-right font-mono whitespace-nowrap ${descuadrado ? '' : 'bg-emerald-50 text-emerald-600'}`}>
                                        {formatMoney(totales.acumulado.saldo_acreedor) || '-'}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </div>
    );
};

export default BalanceComprobacion;
