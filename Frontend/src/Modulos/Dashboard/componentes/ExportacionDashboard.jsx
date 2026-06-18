import React from 'react';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from '@e965/xlsx';

const formatCLP = (monto) =>
    new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(monto ?? 0);

const fechaHoy = () => new Date().toISOString().slice(0, 10);

const nombreArchivo = (tipo, periodo, ext) =>
    `Dashboard_${tipo}_${periodo}_${fechaHoy()}.${ext}`;

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

    const ws = XLSX.utils.json_to_sheet(filas);
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

    const ws = XLSX.utils.json_to_sheet(filas);
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
 * Exporta un resumen ejecutivo de KPIs como PDF.
 */
const exportarResumenPDF = (kpis, periodo) => {
    const doc = new jsPDF();
    const hoy = new Date().toLocaleDateString('es-CL');
    const variacion = Number(kpis?.variacion_pct ?? 0);
    const signo = variacion >= 0 ? '+' : '';

    doc.setFontSize(16);
    doc.setTextColor(30, 58, 138);
    doc.text('Resumen Ejecutivo Dashboard ERP Tenri SpA', 14, 20);

    doc.setFontSize(10);
    doc.setTextColor(71, 85, 105);
    doc.text(`Período: ${periodo}`, 14, 30);
    doc.text(`Fecha de generación: ${hoy}`, 14, 36);

    autoTable(doc, {
        startY: 44,
        head: [['Indicador', 'Valor']],
        body: [
            ['Ventas del período', formatCLP(kpis?.ventas_mes)],
            ['Variación vs período anterior', `${signo}${variacion.toFixed(1)}%`],
            ['Facturas emitidas', String(kpis?.facturas_emitidas_mes ?? 0)],
            ['Facturas pendientes', String(kpis?.facturas_pendientes ?? 0)],
            ['Clientes activos', String(kpis?.clientes_activos ?? 0)],
            ['Cotizaciones pendientes', String(kpis?.cotizaciones_pendientes ?? 0)],
        ],
        theme: 'grid',
        headStyles: { fillColor: [30, 58, 138] },
        columnStyles: {
            0: { fontStyle: 'bold', cellWidth: 100 },
            1: { halign: 'right' },
        },
    });

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
    const kpis = resumen?.kpis ?? {};
    const serieVentas = resumen?.serie_ventas_12m ?? [];
    const topClientes = resumen?.top_clientes ?? [];
    const facturasUrgentes = resumen?.facturas_urgentes ?? [];

    return (
        <div className="flex flex-wrap gap-2 mt-2">
            <button
                type="button"
                onClick={() => exportarVentas12mExcel(serieVentas, periodo)}
                className="flex items-center gap-1 px-3 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-200 transition-colors"
                title="Descargar serie de ventas de los últimos 12 meses en Excel"
            >
                <i className="fas fa-file-excel text-green-600"></i>
                Ventas 12m → Excel
            </button>

            <button
                type="button"
                onClick={() => exportarTopClientesExcel(topClientes, periodo)}
                className="flex items-center gap-1 px-3 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-200 transition-colors"
                title="Descargar ranking de mejores clientes en Excel"
            >
                <i className="fas fa-file-excel text-green-600"></i>
                Top clientes → Excel
            </button>

            <button
                type="button"
                onClick={() => exportarFacturasUrgentesExcel(facturasUrgentes, periodo)}
                className="flex items-center gap-1 px-3 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-200 transition-colors"
                title="Descargar listado de facturas urgentes en Excel"
            >
                <i className="fas fa-file-excel text-green-600"></i>
                Facturas urgentes → Excel
            </button>

            <button
                type="button"
                onClick={() => exportarResumenPDF(kpis, periodo)}
                className="flex items-center gap-1 px-3 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-200 transition-colors"
                title="Descargar resumen ejecutivo de KPIs en PDF"
            >
                <i className="fas fa-file-pdf text-rose-600"></i>
                Resumen ejecutivo → PDF
            </button>
        </div>
    );
};

export default ExportacionDashboard;
