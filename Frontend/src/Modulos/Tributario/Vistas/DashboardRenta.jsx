import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import ModalMapeoSII from './ModalMapeoSII';

// Importaciones para PDF y Excel
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from 'xlsx';

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
                setDatosRenta(res.data);
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

    // =========================================================================
    // EXPORTACIÓN A PDF (CORREGIDO)
    // =========================================================================
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
    
    // =========================================================================
    // EXPORTACIÓN A EXCEL
    // =========================================================================
    const handleExportarExcel = () => {
        const { resumen, desglose, anio_comercial, anio_tributario, tasa_impuesto } = datosRenta;

        // Creamos una matriz (filas y columnas)
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

        // Convertir la matriz a una hoja de cálculo
        const ws = XLSX.utils.aoa_to_sheet(data);
        
        // Ajustar ancho de columnas para que se vea bonito
        ws['!cols'] = [{ wch: 35 }, { wch: 15 }];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, `Renta AT ${anio_tributario}`);
        
        // Descargar el archivo
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

    const { resumen, desglose, regimen_tributario, tasa_impuesto, regla_calculo } = datosRenta;
    const esFlujoCaja = regla_calculo === 'FLUJO_DE_CAJA';
    const esTransparente = regimen_tributario === '14_D8';
    const esGeneral = regimen_tributario === '14_A';

    const nombreRegimen = 
        esTransparente ? '14 D N°8 (Pro Pyme Transparente)' : 
        esGeneral ? '14 A (General Semi Integrado)' : 
        '14 D N°3 (Pro Pyme General)';

    return (
        <div className="p-4 md:p-6 lg:p-8 max-w-7xl mx-auto bg-slate-50 min-h-screen animate-fade-in">
            {/* CABECERA Y FILTROS */}
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
                    
                    {/* BOTONES DE EXPORTACIÓN */}
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

            {/* TARJETAS KPI */}
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

            {/* DETALLE DE CÁLCULO */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* COLUMNA INGRESOS */}
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

                {/* COLUMNA EGRESOS */}
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

            {/* MODALES */}
            {mostrarMapeo && (
                <ModalMapeoSII onClose={() => { setMostrarMapeo(false); cargarPreRenta(); }} />
            )}

            {mostrarManual && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh] animate-fade-in-up">
                        <div className="flex justify-between items-center p-6 border-b border-slate-100 bg-indigo-600 text-white">
                            <h3 className="text-xl font-black flex items-center gap-3">
                                <i className="fas fa-book-open text-indigo-200"></i> Manual: Régimen {nombreRegimen}
                            </h3>
                            <button onClick={() => setMostrarManual(false)} className="text-indigo-200 hover:text-white transition-colors text-2xl">
                                <i className="fas fa-times"></i>
                            </button>
                        </div>
                        <div className="p-6 overflow-y-auto bg-slate-50 space-y-6 text-slate-700 text-sm">
                            <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                                <h4 className="font-bold text-lg text-indigo-700 mb-2">1. Comportamiento del ERP</h4>
                                <p className="leading-relaxed">Al tener configurado el régimen <strong>{nombreRegimen}</strong>, el sistema ha adaptado todas sus fórmulas matemáticas para cumplir estrictamente con las normativas del SII.</p>
                            </div>
                            {esFlujoCaja ? (
                                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-emerald-500">
                                    <h4 className="font-bold text-lg text-emerald-700 mb-2">2. Regla de "Flujo de Caja"</h4>
                                    <ul className="list-disc pl-5 space-y-2">
                                        <li><strong>Ingresos:</strong> Solo pagan impuesto si el cliente depositó el dinero en el año.</li>
                                        <li><strong>Egresos:</strong> Solo rebajan utilidad si los pagaste desde el banco.</li>
                                        <li><strong>Activos Fijos:</strong> Tienes beneficio de <i>Depreciación Instantánea</i> (100% al gasto).</li>
                                    </ul>
                                </div>
                            ) : (
                                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-blue-500">
                                    <h4 className="font-bold text-lg text-blue-700 mb-2">2. Regla "Devengada"</h4>
                                    <ul className="list-disc pl-5 space-y-2">
                                        <li><strong>Ingresos:</strong> Reconocidos al emitir factura, pagada o no.</li>
                                        <li><strong>Egresos:</strong> Reconocidos al registrar la factura de compra.</li>
                                        <li><strong>Activos Fijos:</strong> Depreciación mensual normal obligatoria.</li>
                                    </ul>
                                </div>
                            )}
                        </div>
                        <div className="p-5 border-t border-slate-100 bg-white flex justify-end">
                            <button onClick={() => setMostrarManual(false)} className="px-6 py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg transition-colors">
                                Cerrar guía
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default DashboardRenta;