import React from 'react';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from '@e965/xlsx';
import { sanitizarCeldaExcel, sanitizarFilasExcel } from '../../../Utilidades/exportarExcelSeguro';

const formatCLP = (monto) =>
    new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(monto ?? 0);

const fechaHoy = () => new Date().toISOString().slice(0, 10);

const nombreArchivo = (tipo, periodo, ext) =>
    `Dashboard_${tipo}_${periodo}_${fechaHoy()}.${ext}`;

/**
 * Exporta un workbook Excel multi-hoja con todos los datos del dashboard.
 */
const exportarExcelCompleto = (datos, _periodo) => {
    const wb = XLSX.utils.book_new();

    const kpisData = [
        ['Indicador', 'Valor'],
        ['Ventas del mes', datos?.kpis?.ventas_mes ?? 0],
        ['Variación vs mes anterior (%)', datos?.kpis?.variacion_pct ?? 0],
        ['Facturas emitidas mes', datos?.kpis?.facturas_emitidas_mes ?? 0],
        ['Facturas pendientes', datos?.kpis?.facturas_pendientes ?? 0],
        ['Clientes activos', datos?.kpis?.clientes_activos ?? 0],
        ['Cotizaciones pendientes', datos?.kpis?.cotizaciones_pendientes ?? 0],
        ['DSO (días cobro promedio)', datos?.dso ?? 'N/D'],
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(kpisData), 'KPIs');

    const ventasData = [
        ['Mes', 'Monto (CLP)'],
        ...(datos?.serie_ventas_12m ?? []).map((r) => [r.mes, r.monto]),
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(ventasData), 'Ventas 12m');

    const comprasData = [
        ['Mes', 'Monto (CLP)'],
        ...(datos?.compras_12m ?? []).map((r) => [r.mes, r.monto]),
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(comprasData), 'Compras 12m');

    const agingARData = [
        ['Tramo', 'Monto (CLP)'],
        ...(datos?.aging_ar ?? []).map((r) => [r.tramo, r.monto]),
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(agingARData), 'Aging Cobrar');

    const agingAPData = [
        ['Tramo', 'Monto (CLP)'],
        ...(datos?.aging_ap ?? []).map((r) => [r.tramo, r.monto]),
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(agingAPData), 'Aging Pagar');

    const topData = [
        ['Cliente', 'Monto (CLP)'],
        ...(datos?.top_clientes ?? []).map((r) => [sanitizarCeldaExcel(r.nombre), r.monto]),
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(topData), 'Top Clientes');

    const urgData = [
        ['N° Factura', 'Fecha Emisión', 'Vencimiento', 'Monto (CLP)', 'Estado'],
        ...(datos?.facturas_urgentes ?? []).map((r) => [
            r.numero_factura,
            r.fecha_emision,
            r.fecha_vencimiento ?? '',
            r.monto_bruto,
            r.estado,
        ]),
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(urgData), 'Facturas Urgentes');

    XLSX.writeFile(wb, `dashboard-${fechaHoy()}.xlsx`);
};

/**
 * Exporta la serie de ventas de 12 meses a Excel.
 */
const exportarVentas12mExcel = (serieVentas, periodo) => {
    const filas = (serieVentas ?? []).map((item) => ({
        Mes: item.mes,
        'Monto (CLP)': Number(item.monto) || 0,
    }));

    const ws = XLSX.utils.json_to_sheet(filas);
    ws['!cols'] = [{ wch: 12 }, { wch: 18 }];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Ventas 12m');

    XLSX.writeFile(wb, nombreArchivo('Ventas12m', periodo, 'xlsx'));
};

/**
 * Exporta el top de clientes a Excel.
 */
const exportarTopClientesExcel = (topClientes, periodo) => {
    const filas = (topClientes ?? []).map((item) => ({
        Cliente: item.nombre,
        'Monto Facturado (CLP)': Number(item.monto) || 0,
    }));

    const ws = XLSX.utils.json_to_sheet(sanitizarFilasExcel(filas));
    ws['!cols'] = [{ wch: 35 }, { wch: 20 }];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Top Clientes');

    XLSX.writeFile(wb, nombreArchivo('TopClientes', periodo, 'xlsx'));
};

/**
 * Exporta las facturas urgentes a Excel.
 */
const exportarFacturasUrgentesExcel = (facturas, periodo) => {
    const filas = (facturas ?? []).map((f) => ({
        'N° Factura': f.numero_factura,
        'Fecha Emisión': f.fecha_emision,
        'Fecha Vencimiento': f.fecha_vencimiento,
        'Monto (CLP)': Number(f.monto_bruto) || 0,
        Estado: f.estado,
    }));

    const ws = XLSX.utils.json_to_sheet(sanitizarFilasExcel(filas));
    ws['!cols'] = [
        { wch: 16 },
        { wch: 15 },
        { wch: 18 },
        { wch: 16 },
        { wch: 14 },
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Facturas Urgentes');

    XLSX.writeFile(wb, nombreArchivo('FacturasUrgentes', periodo, 'xlsx'));
};

/**
 * Exporta un resumen ejecutivo de KPIs como PDF (con aging y flujo de caja).
 */
const exportarResumenPDF = (datos, periodo) => {
    const kpis = datos?.kpis ?? {};
    const doc = new jsPDF();
    const hoy = new Date().toLocaleDateString('es-CL');
    const variacion = Number(kpis?.variacion_pct ?? 0);
    const signo = variacion >= 0 ? '+' : '';

    doc.setFontSize(16);
    doc.text('Resumen Ejecutivo — Dashboard', 14, 20);
    doc.setFontSize(10);
    doc.text(`Generado: ${hoy}  |  Período: ${periodo}`, 14, 28);

    autoTable(doc, {
        startY: 35,
        head: [['Indicador', 'Valor']],
        body: [
            ['Ventas del mes', formatCLP(kpis?.ventas_mes)],
            ['Variación vs mes anterior', `${signo}${variacion.toFixed(1)}%`],
            ['Facturas emitidas', String(kpis?.facturas_emitidas_mes ?? 0)],
            ['Facturas pendientes', String(kpis?.facturas_pendientes ?? 0)],
            ['Clientes activos', String(kpis?.clientes_activos ?? 0)],
            ['Cotizaciones pendientes', String(kpis?.cotizaciones_pendientes ?? 0)],
            ['DSO (días cobro)', datos?.dso != null ? `${datos.dso}d` : 'N/D'],
        ],
        theme: 'striped',
        headStyles: { fillColor: [16, 185, 129] },
        columnStyles: { 0: { fontStyle: 'bold', cellWidth: 100 }, 1: { halign: 'right' } },
    });

    let y = doc.lastAutoTable.finalY + 10;
    doc.setFontSize(12);
    doc.text('Aging Cuentas por Cobrar', 14, y);
    autoTable(doc, {
        startY: y + 5,
        head: [['Tramo', 'Monto (CLP)']],
        body: (datos?.aging_ar ?? []).map((r) => [r.tramo, `$${r.monto.toLocaleString('es-CL')}`]),
        theme: 'striped',
        headStyles: { fillColor: [59, 130, 246] },
    });

    y = doc.lastAutoTable.finalY + 10;
    doc.setFontSize(12);
    doc.text('Aging Cuentas por Pagar', 14, y);
    autoTable(doc, {
        startY: y + 5,
        head: [['Tramo', 'Monto (CLP)']],
        body: (datos?.aging_ap ?? []).map((r) => [r.tramo, `$${r.monto.toLocaleString('es-CL')}`]),
        theme: 'striped',
        headStyles: { fillColor: [245, 158, 11] },
    });

    if (datos?.flujo_caja_30d) {
        y = doc.lastAutoTable.finalY + 10;
        doc.setFontSize(12);
        doc.text('Proyección Flujo de Caja 30 días', 14, y);
        autoTable(doc, {
            startY: y + 5,
            head: [['Concepto', 'Monto (CLP)']],
            body: [
                ['Entradas proyectadas', `$${datos.flujo_caja_30d.entradas_30d.toLocaleString('es-CL')}`],
                ['Salidas proyectadas', `$${datos.flujo_caja_30d.salidas_30d.toLocaleString('es-CL')}`],
                ['Neto proyectado', `$${datos.flujo_caja_30d.neto_30d.toLocaleString('es-CL')}`],
            ],
            theme: 'striped',
            headStyles: { fillColor: [99, 102, 241] },
        });
    }

    doc.save(nombreArchivo('ResumenEjecutivo', periodo, 'pdf'));
};

/**
 * Fila de botones de exportación para el Dashboard.
 *
 * Props:
 *   resumen  — objeto completo del endpoint /api/dashboard/resumen
 *   periodo  — string: 'mes' | 'trimestre' | 'año'
 */
const ExportacionDashboard = ({ resumen, periodo = 'mes' }) => {
    const serieVentas = resumen?.serie_ventas_12m ?? [];
    const topClientes = resumen?.top_clientes ?? [];
    const facturasUrgentes = resumen?.facturas_urgentes ?? [];

    return (
        <div className="flex flex-wrap items-center gap-2 mt-2">
            {/* Botón principal: reporte completo multi-hoja */}
            <button
                type="button"
                onClick={() => exportarExcelCompleto(resumen, periodo)}
                className="flex items-center gap-1.5 px-4 py-2 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-sm transition-colors"
                title="Descarga un Excel con 7 hojas: KPIs, ventas, compras, aging, top clientes y facturas urgentes"
            >
                <i className="fas fa-file-excel"></i>
                Reporte Completo Excel
            </button>

            {/* Botón PDF mejorado */}
            <button
                type="button"
                onClick={() => exportarResumenPDF(resumen, periodo)}
                className="flex items-center gap-1.5 px-4 py-2 text-sm font-bold bg-rose-600 hover:bg-rose-700 text-white rounded-lg shadow-sm transition-colors"
                title="Descargar resumen ejecutivo con KPIs, aging y flujo de caja en PDF"
            >
                <i className="fas fa-file-pdf"></i>
                Resumen Ejecutivo PDF
            </button>

            {/* Separador visual */}
            <span className="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-700 mx-1" />

            {/* Botones individuales secundarios */}
            <button
                type="button"
                onClick={() => exportarVentas12mExcel(serieVentas, periodo)}
                className="flex items-center gap-1 px-3 py-1.5 text-xs bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg border border-slate-200 dark:border-slate-700 transition-colors"
                title="Solo la serie de ventas de los últimos 12 meses"
            >
                <i className="fas fa-file-excel text-green-600"></i>
                Ventas 12m
            </button>

            <button
                type="button"
                onClick={() => exportarTopClientesExcel(topClientes, periodo)}
                className="flex items-center gap-1 px-3 py-1.5 text-xs bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg border border-slate-200 dark:border-slate-700 transition-colors"
                title="Solo el ranking de mejores clientes"
            >
                <i className="fas fa-file-excel text-green-600"></i>
                Top clientes
            </button>

            <button
                type="button"
                onClick={() => exportarFacturasUrgentesExcel(facturasUrgentes, periodo)}
                className="flex items-center gap-1 px-3 py-1.5 text-xs bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg border border-slate-200 dark:border-slate-700 transition-colors"
                title="Solo las facturas que requieren atención urgente"
            >
                <i className="fas fa-file-excel text-green-600"></i>
                Facturas urgentes
            </button>
        </div>
    );
};

export default ExportacionDashboard;
