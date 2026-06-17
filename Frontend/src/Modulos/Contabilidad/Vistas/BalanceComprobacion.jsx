import React, { useState } from 'react';
import { api } from '../../../Configuracion/api';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import * as XLSX from "@e965/xlsx";
import ModalGenerico from '../../../Componentes/ModalGenerico';
import { logger } from '../../../Configuracion/logger';

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
        filtro: 1,
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
                filtro: filtros.filtro,
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
            'Debe': parseFloat(c.debe) || 0,
            'Haber': parseFloat(c.haber) || 0,
            'Saldo Deudor': parseFloat(c.saldo_deudor) || 0,
            'Saldo Acreedor': parseFloat(c.saldo_acreedor) || 0,
        }));

        const worksheet = XLSX.utils.json_to_sheet(datosExcel);
        worksheet['!cols'] = [
            { wch: 10 }, { wch: 35 }, { wch: 12 },
            { wch: 16 }, { wch: 16 }, { wch: 16 }, { wch: 16 },
        ];
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Balance Comprobacion');
        XLSX.writeFile(workbook, `Balance_Comprobacion_${filtros.fecha_inicio}_${filtros.fecha_fin}.xlsx`);
    };

    const totales = resultado?.totales;
    const descuadrado = totales && (
        Math.abs(totales.debe - totales.haber) > 1 ||
        Math.abs(totales.saldo_deudor - totales.saldo_acreedor) > 1
    );

    return (
        <div className="max-w-7xl mx-auto p-6 font-sans text-slate-800">

            <ModalGenerico
                isOpen={notificacion.show}
                onClose={() => setNotificacion({ ...notificacion, show: false })}
                title={notificacion.title}
                message={notificacion.message}
                type={notificacion.type}
            />

            <div className="flex justify-between items-center mb-6 flex-wrap gap-3">
                <div className="flex items-center gap-3">
                    <h1 className="text-3xl font-bold text-slate-900">Balance de Comprobacion y Saldos</h1>
                    <AyudaModulo moduloId="balanceComprobacion" size={26} />
                </div>
            </div>

            {/* Panel de filtros */}
            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex flex-wrap gap-4 items-end">
                <div>
                    <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Fecha Inicio</label>
                    <input
                        type="date"
                        className="border border-slate-300 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none"
                        value={filtros.fecha_inicio}
                        onChange={e => setFiltros({ ...filtros, fecha_inicio: e.target.value })}
                    />
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Fecha Fin</label>
                    <input
                        type="date"
                        className="border border-slate-300 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none"
                        value={filtros.fecha_fin}
                        onChange={e => setFiltros({ ...filtros, fecha_fin: e.target.value })}
                    />
                </div>
                <div>
                    <label className="block text-[10px] font-bold text-blue-600 uppercase mb-1">Auditoria</label>
                    <select
                        className="border border-blue-200 bg-blue-50 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none font-bold text-slate-700"
                        value={filtros.filtro}
                        onChange={e => setFiltros({ ...filtros, filtro: Number(e.target.value) })}
                    >
                        <option value="1">1 - Conciliado / Validos</option>
                        <option value="0">0 - Historia Completa (Todo)</option>
                        <option value="2">2 - Anulados / Internos</option>
                    </select>
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
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Excel
                    </button>
                </div>
            </div>

            {/* Banner de descuadre */}
            {descuadrado && (
                <div className="mb-4 bg-red-50 border border-red-300 rounded-lg px-4 py-3 text-red-700 font-semibold text-sm">
                    Advertencia: Balance descuadrado — Debe diferente a Haber o Saldo Deudor diferente a Saldo Acreedor. Revise los asientos del periodo.
                </div>
            )}

            {/* Tabla principal */}
            <div className="bg-white rounded-lg shadow border border-slate-200 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead className="bg-slate-100 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-200">
                            <tr>
                                <th className="px-4 py-3 border-r border-slate-200">Codigo</th>
                                <th className="px-4 py-3 border-r border-slate-200">Cuenta</th>
                                <th className="px-4 py-3 border-r border-slate-200">Tipo</th>
                                <th className="px-4 py-3 text-right border-r border-slate-200">Debe</th>
                                <th className="px-4 py-3 text-right border-r border-slate-200">Haber</th>
                                <th className="px-4 py-3 text-right border-r border-slate-200">Saldo Deudor</th>
                                <th className="px-4 py-3 text-right">Saldo Acreedor</th>
                            </tr>
                        </thead>
                        <tbody className="text-xs divide-y divide-slate-100">
                            {loading ? (
                                <tr>
                                    <td colSpan="7" className="p-8 text-center text-slate-400">
                                        <i className="fas fa-spinner fa-spin text-slate-300 text-2xl mb-2 block"></i>
                                        Cargando...
                                    </td>
                                </tr>
                            ) : !resultado ? (
                                <tr>
                                    <td colSpan="7" className="p-8 text-center text-slate-400">
                                        <i className="fas fa-table text-slate-300 text-2xl mb-2 block"></i>
                                        Seleccione un periodo y presione Consultar.
                                    </td>
                                </tr>
                            ) : resultado.cuentas.length === 0 ? (
                                <tr>
                                    <td colSpan="7" className="p-8 text-center text-slate-400">
                                        No hay cuentas con movimientos en el periodo seleccionado.
                                    </td>
                                </tr>
                            ) : (
                                resultado.cuentas.map((cuenta, idx) => (
                                    <tr key={idx} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-4 py-2 font-mono font-bold text-slate-600 border-r border-slate-100">
                                            {cuenta.codigo}
                                        </td>
                                        <td className="px-4 py-2 border-r border-slate-100 text-slate-700">
                                            {cuenta.nombre}
                                        </td>
                                        <td className="px-4 py-2 border-r border-slate-100 text-slate-500">
                                            {cuenta.tipo}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono border-r border-slate-100 text-emerald-600">
                                            {formatMoney(cuenta.debe) || '-'}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono border-r border-slate-100 text-slate-600">
                                            {formatMoney(cuenta.haber) || '-'}
                                        </td>
                                        <td className={`px-4 py-2 text-right font-mono border-r border-slate-100 ${parseFloat(cuenta.saldo_deudor) > 0 ? 'bg-emerald-50 text-emerald-700' : 'text-slate-400'}`}>
                                            {formatMoney(cuenta.saldo_deudor) || '-'}
                                        </td>
                                        <td className={`px-4 py-2 text-right font-mono ${parseFloat(cuenta.saldo_acreedor) > 0 ? 'bg-blue-50 text-blue-700' : 'text-slate-400'}`}>
                                            {formatMoney(cuenta.saldo_acreedor) || '-'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        {totales && (
                            <tfoot>
                                <tr className={`text-xs font-bold border-t-2 border-slate-300 ${descuadrado ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700'}`}>
                                    <td className="px-4 py-3 border-r border-slate-200" colSpan="3">
                                        TOTALES
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono border-r border-slate-200">
                                        {formatMoney(totales.debe) || '-'}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono border-r border-slate-200">
                                        {formatMoney(totales.haber) || '-'}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono border-r border-slate-200">
                                        {formatMoney(totales.saldo_deudor) || '-'}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono">
                                        {formatMoney(totales.saldo_acreedor) || '-'}
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
