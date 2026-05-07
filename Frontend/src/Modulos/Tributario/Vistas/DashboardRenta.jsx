import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import ModalMapeoSII from './ModalMapeoSII';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from "@e965/xlsx";

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const DashboardRenta = () => {
    const [anio, setAnio] = useState(new Date().getFullYear());
    const [datosRenta, setDatosRenta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [mostrarManual, setMostrarManual] = useState(false);
    const [mostrarMapeo, setMostrarMapeo] = useState(false);

    const cargarPreRenta = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.get(`/renta/pre-calculo/${anio}`);
            if (res.success) {
                const data = res.data;
                const adaptedData = {
                    ...data,
                    resumen: {
                        total_ingresos: data.ingresos.ventas_netas + data.ingresos.otros_ingresos,
                        total_egresos: data.gastos.costos_directos + data.gastos.depreciacion + data.gastos.remuneraciones,
                        base_imponible: data.resultado.base_imponible,
                        impuesto_determinado: data.resultado.impuesto_renta
                    },
                    desglose: {
                        ingresos_giro: data.ingresos.ventas_netas,
                        otros_ingresos: data.ingresos.otros_ingresos,
                        compras: data.gastos.costos_directos,
                        depreciacion: data.gastos.depreciacion,
                        remuneraciones_pagadas: data.gastos.remuneraciones,
                        honorarios_pagados: 0,
                        arriendos_pagados: 0,
                        gastos_generales: 0
                    },
                    regimen_tributario: data.regimen_tributario || '14_A',
                    regla_calculo: data.regla_calculo || 'DEVENGADO',
                    tasa_impuesto: data.resultado.tasa_impuesto
                };
                setDatosRenta(adaptedData);
            } else {
                setError('No se pudo cargar la información tributaria.');
            }
        } catch (err) {
            setError(err.message || 'Error de conexión al obtener datos de Renta.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarPreRenta();
    }, [anio]);

    const regimen_tributario = datosRenta?.regimen_tributario || '14_A';
    const regla_calculo = datosRenta?.regla_calculo || 'DEVENGADO';
    const esFlujoCaja = regla_calculo === 'FLUJO_DE_CAJA';
    const esTransparente = regimen_tributario === '14_D8';
    const esGeneral = regimen_tributario === '14_A';
    const nombreRegimen =
        esTransparente ? '14 D N°8 (Pro Pyme Transparente)' :
            esGeneral ? '14 A (General Semi Integrado)' :
                '14 D N°3 (Pro Pyme General)';

    const handleExportarPDF = () => {
        const doc = new jsPDF();
        const { resumen, desglose, anio_comercial, anio_tributario, tasa_impuesto } = datosRenta;

        doc.setFontSize(18);
        doc.setTextColor(30, 58, 138);
        doc.text(`Papel de Trabajo - Operación Renta AT ${anio_tributario}`, 14, 20);
        doc.setFontSize(10);
        doc.setTextColor(71, 85, 105);
        doc.text(`Año Comercial: ${anio_comercial}`, 14, 28);
        doc.text(`Régimen Tributario: ${nombreRegimen}`, 14, 34);
        doc.text(`Fecha de Generación: ${new Date().toLocaleDateString('es-CL')}`, 14, 40);

        autoTable(doc, {
            startY: 48,
            head: [['Resumen Tributario', 'Monto (CLP)']],
            body: [
                ['Total Ingresos', formatCurrency(resumen.total_ingresos)],
                ['Total Egresos', formatCurrency(resumen.total_egresos)],
                ['BASE IMPONIBLE (Utilidad Tributaria)', formatCurrency(resumen.base_imponible)],
                [`Impuesto Determinado (Tasa ${tasa_impuesto}%)`, formatCurrency(resumen.impuesto_determinado)],
            ],
            theme: 'grid',
            headStyles: { fillColor: [79, 70, 229] },
            columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } },
        });

        autoTable(doc, {
            startY: doc.lastAutoTable.finalY + 12,
            head: [['Detalle Analítico (Egresos e Ingresos)', 'Monto (CLP)']],
            body: [
                ['(+) Ingresos del Giro', formatCurrency(desglose.ingresos_giro)],
                ['(+) Otros Ingresos', formatCurrency(desglose.otros_ingresos)],
                ['(-) Compras y Proveedores', formatCurrency(desglose.compras)],
                ['(-) Depreciación de Activos Fijos', formatCurrency(desglose.depreciacion)],
                ['(-) Remuneraciones Pagadas', formatCurrency(desglose.remuneraciones_pagadas)],
                ['(-) Honorarios Pagados', formatCurrency(desglose.honorarios_pagados)],
                ['(-) Arriendos y Gastos Generales', formatCurrency(desglose.arriendos_pagados + desglose.gastos_generales)],
            ],
            theme: 'striped',
            headStyles: { fillColor: [51, 65, 85] },
            columnStyles: { 1: { halign: 'right' } },
        });

        doc.save(`Respaldo_Renta_AT${anio_tributario}.pdf`);
    };

    const handleExportarExcel = () => {
        const { resumen, desglose, anio_comercial, anio_tributario, tasa_impuesto } = datosRenta;
        const data = [
            ['Papel de Trabajo - Operación Renta'],
            ['Año Comercial', anio_comercial],
            ['Año Tributario', anio_tributario],
            ['Régimen Tributario', nombreRegimen],
            [''],
            ['--- RESUMEN ---', 'Monto ($)'],
            ['Total Ingresos', resumen.total_ingresos],
            ['Total Egresos', resumen.total_egresos],
            ['Base Imponible', resumen.base_imponible],
            [`Impuesto Determinado (${tasa_impuesto}%)`, resumen.impuesto_determinado],
            [''],
            ['--- DESGLOSE ---', 'Monto ($)'],
            ['Ingresos del Giro', desglose.ingresos_giro],
            ['Otros Ingresos', desglose.otros_ingresos],
            ['Compras y Proveedores', desglose.compras],
            ['Depreciación', desglose.depreciacion],
            ['Remuneraciones Pagadas', desglose.remuneraciones_pagadas],
            ['Honorarios Pagados', desglose.honorarios_pagados],
            ['Gastos Generales y Arriendos', (desglose.arriendos_pagados + desglose.gastos_generales)],
        ];

        const ws = XLSX.utils.aoa_to_sheet(data);

        ws['!cols'] = [{ wch: 35 }, { wch: 15 }];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, `Renta AT ${anio_tributario}`);

        XLSX.writeFile(wb, `Calculo_Renta_AT${anio_tributario}.xlsx`);
    };

    if (loading && !datosRenta) {
        return (
            <div className="flex flex-col justify-center items-center min-h-[60vh] text-slate-400">
                <i className="fas fa-circle-notch fa-spin text-4xl mb-4"></i>
                <p className="font-medium">Calculando Base Imponible Tributaria...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-8 text-center bg-rose-50 border border-rose-200 rounded-xl m-6">
                <i className="fas fa-exclamation-triangle text-3xl text-rose-500 mb-3"></i>
                <h3 className="text-lg font-bold text-rose-800">{error}</h3>
                <button onClick={cargarPreRenta} className="mt-4 px-4 py-2 bg-white text-rose-600 font-bold border border-rose-200 rounded-lg hover:bg-rose-50">Reintentar</button>
            </div>
        );
    }

    const { resumen, desglose, tasa_impuesto } = datosRenta;

    return (
        <div className="p-4 md:p-6 lg:p-8 max-w-7xl mx-auto bg-slate-50 min-h-screen animate-fade-in">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center text-2xl shadow-sm shrink-0">
                        <i className="fas fa-landmark"></i>
                    </div>
                    <div>
                        <h1 className="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">Operación Renta</h1>
                        <p className="text-slate-500 font-medium text-sm md:text-base mt-0.5">
                            Régimen {nombreRegimen} • Base {esFlujoCaja ? 'Percibida/Pagada' : 'Devengada'}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                    <div className="flex bg-white rounded-lg border border-slate-200 shadow-sm p-1">
                        <button onClick={handleExportarPDF} className="px-3 py-1.5 text-rose-600 hover:bg-rose-50 rounded font-bold text-sm transition-colors flex items-center gap-2" title="Descargar Papel de Trabajo PDF">
                            <i className="fas fa-file-pdf"></i> PDF
                        </button>
                        <div className="w-px bg-slate-200 mx-1"></div>
                        <button onClick={handleExportarExcel} className="px-3 py-1.5 text-emerald-600 hover:bg-emerald-50 rounded font-bold text-sm transition-colors flex items-center gap-2" title="Exportar a Excel">
                            <i className="fas fa-file-excel"></i> Excel
                        </button>
                    </div>

                    <button onClick={() => setMostrarMapeo(true)} className="w-full sm:w-auto px-4 py-2 bg-white text-indigo-600 font-bold border border-slate-200 hover:bg-indigo-50 rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                        <i className="fas fa-project-diagram"></i> Mapear Cuentas
                    </button>

                    <button onClick={() => setMostrarManual(true)} className="w-full sm:w-auto px-4 py-2 bg-indigo-600 text-white font-bold border border-indigo-700 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                        <i className="fas fa-book-open"></i> Guía Tributaria
                    </button>
                    <div className="w-full sm:w-auto bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-sm flex items-center gap-3 ml-2">
                        <span className="text-sm font-bold text-slate-600 uppercase">Año:</span>
                        <select value={anio} onChange={(e) => setAnio(parseInt(e.target.value))} className="bg-slate-50 border border-slate-200 text-slate-800 font-bold py-1.5 px-3 rounded-md outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer">
                            <option value={2025}>2025 (AT 2026)</option>
                            <option value={2026}>2026 (AT 2027)</option>
                            <option value={2027}>2027 (AT 2028)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <div className="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-b-4 border-b-emerald-500 relative group">
                    <p className="text-xs font-bold text-slate-500 uppercase flex items-center gap-1.5"><i className="fas fa-arrow-down text-emerald-500"></i> Ingresos {esFlujoCaja ? 'Percibidos' : 'Devengados'}</p>
                    <h3 className="text-2xl font-black text-slate-800 mt-2">{formatCurrency(resumen.total_ingresos)}</h3>
                    <p className="text-[10px] text-slate-400 mt-1">{esFlujoCaja ? 'Ventas cobradas en el año.' : 'Todas las facturas emitidas, pagadas o no.'}</p>
                </div>
                <div className="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-b-4 border-b-rose-500">
                    <p className="text-xs font-bold text-slate-500 uppercase flex items-center gap-1.5"><i className="fas fa-arrow-up text-rose-500"></i> Egresos {esFlujoCaja ? 'Pagados' : 'Devengados'}</p>
                    <h3 className="text-2xl font-black text-slate-800 mt-2">{formatCurrency(resumen.total_egresos)}</h3>
                    <p className="text-[10px] text-slate-400 mt-1">{esFlujoCaja ? 'Compras efectivamente pagadas.' : 'Gastos facturados totales.'}</p>
                </div>
                <div className="bg-indigo-600 p-5 rounded-2xl shadow-md text-white relative overflow-hidden">
                    <div className="absolute -right-4 -top-4 opacity-10 text-6xl"><i className="fas fa-calculator"></i></div>
                    <p className="text-indigo-100 text-xs font-bold uppercase relative z-10">{esTransparente ? 'Base a Asignar a Socios' : 'Base Imponible (Utilidad)'}</p>
                    <h3 className="text-3xl font-black mt-1 relative z-10">{formatCurrency(resumen.base_imponible)}</h3>
                </div>
                <div className={`${esTransparente ? 'bg-slate-200 text-slate-500' : 'bg-slate-800 text-white'} p-5 rounded-2xl shadow-md relative overflow-hidden`}>
                    <div className="absolute -right-4 -top-4 opacity-10 text-6xl"><i className="fas fa-file-invoice-dollar"></i></div>
                    <div className="flex justify-between items-center relative z-10">
                        <p className={`text-xs font-bold uppercase ${esTransparente ? 'text-slate-600' : 'text-slate-300'}`}>Impuesto 1ra Categoría</p>
                        <span className={`${esTransparente ? 'bg-slate-300 text-slate-700' : 'bg-slate-700 text-white'} px-2 py-0.5 rounded text-xs font-bold`}>{tasa_impuesto}%</span>
                    </div>
                    <h3 className={`text-3xl font-black mt-1 relative z-10 ${esTransparente ? 'text-slate-400' : 'text-emerald-400'}`}>{formatCurrency(resumen.impuesto_determinado)}</h3>
                    <p className={`text-[10px] mt-1 relative z-10 ${esTransparente ? 'text-slate-500' : 'text-slate-400'}`}>
                        {esTransparente ? 'Empresa exenta. Pagan los dueños.' : 'Proyección a pagar en Abril.'}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div className="bg-emerald-50 px-5 py-4 border-b border-emerald-100 flex justify-between items-center">
                        <h3 className="font-bold text-emerald-800"><i className="fas fa-plus-circle mr-2"></i>Detalle de Ingresos</h3>
                        <span className="text-sm font-black text-emerald-700">{formatCurrency(resumen.total_ingresos)}</span>
                    </div>
                    <div className="p-0 flex-grow">
                        <table className="w-full text-left text-sm">
                            <tbody className="divide-y divide-slate-100">
                                <tr className="hover:bg-slate-50">
                                    <td className="px-5 py-4"><p className="text-slate-700 font-bold">Ingresos del Giro (Ventas)</p></td>
                                    <td className="px-5 py-4 font-black text-slate-800 text-right">{formatCurrency(desglose.ingresos_giro)}</td>
                                </tr>
                                <tr className="hover:bg-slate-50">
                                    <td className="px-5 py-4"><p className="text-slate-700 font-bold">Otros Ingresos</p></td>
                                    <td className="px-5 py-4 font-black text-slate-800 text-right">{formatCurrency(desglose.otros_ingresos)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div className="p-4 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
                        <i className="fas fa-info-circle text-emerald-500 mr-1"></i> <strong>Regla del Régimen:</strong> {esFlujoCaja ? 'Solo se consideran ventas pagadas por el cliente.' : 'Se consideran TODAS las ventas facturadas, independiente de su cobro.'}
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div className="bg-rose-50 px-5 py-4 border-b border-rose-100 flex justify-between items-center">
                        <h3 className="font-bold text-rose-800"><i className="fas fa-minus-circle mr-2"></i>Detalle de Egresos</h3>
                        <span className="text-sm font-black text-rose-700">{formatCurrency(resumen.total_egresos)}</span>
                    </div>
                    <div className="p-0 flex-grow">
                        <table className="w-full text-left text-sm">
                            <tbody className="divide-y divide-slate-100">
                                <tr className="hover:bg-slate-50">
                                    <td className="px-5 py-3"><p className="text-slate-700 font-bold">Compras y Proveedores</p></td>
                                    <td className="px-5 py-3 font-black text-slate-800 text-right">{formatCurrency(desglose.compras)}</td>
                                </tr>
                                <tr className="hover:bg-slate-50">
                                    <td className="px-5 py-3">
                                        <p className="text-slate-700 font-bold">{esFlujoCaja ? 'Depreciación Instantánea (Activos)' : 'Depreciación Contable Normal'}</p>
                                    </td>
                                    <td className="px-5 py-3 font-black text-slate-800 text-right">{formatCurrency(desglose.depreciacion)}</td>
                                </tr>
                                <tr className="hover:bg-slate-50">
                                    <td className="px-5 py-3"><p className="text-slate-700 font-bold">Remuneraciones Pagadas</p></td>
                                    <td className="px-5 py-3 font-black text-slate-800 text-right">{formatCurrency(desglose.remuneraciones_pagadas)}</td>
                                </tr>
                                <tr className="hover:bg-slate-50">
                                    <td className="px-5 py-3"><p className="text-slate-700 font-bold">Honorarios Pagados</p></td>
                                    <td className="px-5 py-3 font-black text-slate-800 text-right">{formatCurrency(desglose.honorarios_pagados)}</td>
                                </tr>
                                <tr className="hover:bg-slate-50">
                                    <td className="px-5 py-3"><p className="text-slate-700 font-bold">Gastos Generales y Arriendos</p></td>
                                    <td className="px-5 py-3 font-black text-slate-800 text-right">{formatCurrency(desglose.arriendos_pagados + desglose.gastos_generales)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div className="p-4 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
                        <i className="fas fa-info-circle text-rose-500 mr-1"></i> <strong>Regla del Régimen:</strong> {esFlujoCaja ? 'Los gastos se rebajan solo al pagarse desde el banco.' : 'Los gastos se rebajan al momento de facturarse.'}
                    </div>
                </div>
            </div>

            {mostrarMapeo && (
                <ModalMapeoSII onClose={() => { setMostrarMapeo(false); cargarPreRenta(); }} />
            )}

            {mostrarManual && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh] animate-fade-in-up">
                        <div className="flex justify-between items-center p-6 border-b border-slate-100 bg-indigo-600 text-white">
                            <h3 className="text-xl font-black flex items-center gap-3">
                                <i className="fas fa-university text-indigo-200"></i>
                                Ficha Técnica: Régimen {nombreRegimen}
                            </h3>
                            <button onClick={() => setMostrarManual(false)} className="text-indigo-200 hover:text-white transition-colors text-2xl">
                                <i className="fas fa-times"></i>
                            </button>
                        </div>

                        <div className="p-6 overflow-y-auto bg-slate-50 space-y-6 text-slate-700">
                            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                                <h4 className="font-bold text-indigo-700 mb-2 flex items-center gap-2">
                                    <i className="fas fa-info-circle"></i> ¿Qué significa este régimen?
                                </h4>
                                <p className="text-sm leading-relaxed">
                                    {esGeneral && "El Régimen General (14 A) está diseñado para grandes empresas. Se basa en contabilidad completa y tributa por rentas devengadas, permitiendo a los dueños usar el 100% del impuesto pagado por la empresa como crédito, pero con una retención del 35% en la práctica (crédito parcial del 65%)."}
                                    {esTransparente && "El Régimen Pro Pyme Transparente (14 D N°8) libera a la empresa del Impuesto de Primera Categoría. La utilidad se asigna directamente a los socios, quienes tributan en su Global Complementario, optimizando el flujo de caja al no 'congelar' dinero en impuestos de la empresa."}
                                    {!esGeneral && !esTransparente && "El Régimen Pro Pyme General (14 D N°3) ofrece una tasa reducida de impuesto y contabilidad simplificada. Está enfocado en proteger el crecimiento de la pequeña empresa permitiendo depreciación instantánea de sus activos."}
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="bg-white p-4 rounded-xl border border-slate-200">
                                    <h5 className="font-black text-[10px] uppercase tracking-widest text-slate-400 mb-3">Reconocimiento de Ingresos</h5>
                                    <div className="flex items-start gap-3">
                                        <div className={`mt-1 h-2 w-2 rounded-full shrink-0 ${esFlujoCaja ? 'bg-emerald-500' : 'bg-blue-500'}`}></div>
                                        <p className="text-xs font-medium">
                                            {esFlujoCaja
                                                ? "Base Percibida: Solo tributas por las ventas que ya han sido depositadas en tu cuenta bancaria."
                                                : "Base Devengada: Tributas por el total de facturas emitidas, aunque el cliente aún no te haya pagado."}
                                        </p>
                                    </div>
                                </div>
                                <div className="bg-white p-4 rounded-xl border border-slate-200">
                                    <h5 className="font-black text-[10px] uppercase tracking-widest text-slate-400 mb-3">Tratamiento de Activos</h5>
                                    <div className="flex items-start gap-3">
                                        <div className={`mt-1 h-2 w-2 rounded-full shrink-0 ${esFlujoCaja ? 'bg-emerald-500' : 'bg-blue-500'}`}></div>
                                        <p className="text-xs font-medium">
                                            {esFlujoCaja
                                                ? "Depreciación Instantánea: El valor total de cualquier compra de activo fijo se rebaja de la utilidad el mismo año de compra."
                                                : "Depreciación Lineal: Los activos pierden valor mes a mes según su vida útil técnica definida por el SII."}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-indigo-50 p-5 rounded-xl border border-indigo-100">
                                <h4 className="font-bold text-indigo-800 mb-3 text-sm italic">Consideraciones del Analista ERP:</h4>
                                <ul className="space-y-3">
                                    <li className="flex items-center gap-3 text-xs font-bold text-indigo-900">
                                        <i className="fas fa-check-double text-indigo-500"></i>
                                        Tasa de Impuesto: {tasa_impuesto}% sobre la Base Imponible calculada.
                                    </li>
                                    <li className="flex items-center gap-3 text-xs font-bold text-indigo-900">
                                        <i className="fas fa-check-double text-indigo-500"></i>
                                        Crédito PPM: Se descuenta el {datosRenta.creditos.ppm_acumulado > 0 ? '100%' : '0%'} de los pagos provisionales realizados.
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div className="p-5 border-t border-slate-100 bg-white flex justify-end">
                            <button onClick={() => setMostrarManual(false)} className="px-8 py-3 bg-slate-900 hover:bg-black text-white font-black rounded-xl transition-all shadow-lg text-sm">
                                Entendido, cerrar guía
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default DashboardRenta;