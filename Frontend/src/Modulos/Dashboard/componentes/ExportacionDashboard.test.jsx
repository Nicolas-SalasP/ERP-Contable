import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';

const mockSave = vi.fn();
const mockSetFontSize = vi.fn();
const mockSetTextColor = vi.fn();
const mockText = vi.fn();

vi.mock('jspdf', () => ({
    default: vi.fn().mockImplementation(function() {
        this.setFontSize = mockSetFontSize;
        this.setTextColor = mockSetTextColor;
        this.text = mockText;
        this.save = mockSave;
        this.lastAutoTable = { finalY: 100 };
    }),
}));

vi.mock('jspdf-autotable', () => ({
    default: vi.fn(),
}));

vi.mock('@e965/xlsx', () => ({
    utils: {
        json_to_sheet: vi.fn(() => ({ '!cols': [] })),
        aoa_to_sheet: vi.fn(() => ({ '!cols': [] })),
        book_new: vi.fn(() => ({})),
        book_append_sheet: vi.fn(),
    },
    writeFile: vi.fn(),
}));

import ExportacionDashboard from './ExportacionDashboard';
import * as XLSX from '@e965/xlsx';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

const resumenMock = {
    kpis: {
        ventas_mes: 5000000,
        variacion_pct: 10.5,
        facturas_emitidas_mes: 20,
        facturas_pendientes: 3,
        clientes_activos: 50,
        cotizaciones_pendientes: 7,
    },
    serie_ventas_12m: [
        { mes: 'Ene', monto: 1000000 },
        { mes: 'Feb', monto: 1200000 },
    ],
    top_clientes: [
        { nombre: 'Cliente A', monto: 3000000 },
        { nombre: 'Cliente B', monto: 2000000 },
    ],
    facturas_urgentes: [
        {
            numero_factura: '1001',
            fecha_emision: '2025-01-01',
            fecha_vencimiento: '2025-01-31',
            monto_bruto: 500000,
            estado: 'PENDIENTE',
        },
    ],
};

describe('ExportacionDashboard', () => {
    it('renderiza los botones principales de exportación', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        expect(screen.getByText(/Reporte Completo Excel/i)).toBeTruthy();
        expect(screen.getByText(/Resumen Ejecutivo PDF/i)).toBeTruthy();
        expect(screen.getByText(/Ventas 12m/i)).toBeTruthy();
        expect(screen.getByText(/Top clientes/i)).toBeTruthy();
        expect(screen.getByText(/Facturas urgentes/i)).toBeTruthy();
    });

    it('renderiza sin props sin lanzar error', () => {
        render(<ExportacionDashboard />);
        expect(screen.getByText(/Reporte Completo Excel/i)).toBeTruthy();
    });

    it('botón "Reporte Completo Excel" llama XLSX.writeFile', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/Reporte Completo Excel/i));
        expect(XLSX.writeFile).toHaveBeenCalled();
    });

    it('botón "Ventas 12m" llama XLSX.writeFile', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/^Ventas 12m$/i));
        expect(XLSX.writeFile).toHaveBeenCalled();
    });

    it('botón "Top clientes" llama XLSX.writeFile', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/^Top clientes$/i));
        expect(XLSX.writeFile).toHaveBeenCalled();
    });

    it('botón "Facturas urgentes" llama XLSX.writeFile', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/^Facturas urgentes$/i));
        expect(XLSX.writeFile).toHaveBeenCalled();
    });

    it('botón "Resumen Ejecutivo PDF" crea instancia jsPDF y guarda', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/Resumen Ejecutivo PDF/i));
        expect(jsPDF).toHaveBeenCalled();
        expect(mockSave).toHaveBeenCalled();
    });

    it('"Ventas 12m" convierte datos a hoja con json_to_sheet', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/^Ventas 12m$/i));
        expect(XLSX.utils.json_to_sheet).toHaveBeenCalledWith([
            { Mes: 'Ene', 'Monto (CLP)': 1000000 },
            { Mes: 'Feb', 'Monto (CLP)': 1200000 },
        ]);
    });

    it('"Top clientes" incluye nombres de clientes', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/^Top clientes$/i));
        expect(XLSX.utils.json_to_sheet).toHaveBeenCalledWith([
            { Cliente: 'Cliente A', 'Monto Facturado (CLP)': 3000000 },
            { Cliente: 'Cliente B', 'Monto Facturado (CLP)': 2000000 },
        ]);
    });

    it('"Facturas urgentes" incluye número de factura y estado', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/^Facturas urgentes$/i));
        expect(XLSX.utils.json_to_sheet).toHaveBeenCalledWith([
            expect.objectContaining({ 'N° Factura': '1001', Estado: 'PENDIENTE' }),
        ]);
    });

    it('PDF llama autoTable para construir el resumen de KPIs', () => {
        render(<ExportacionDashboard resumen={resumenMock} periodo="mes" />);
        fireEvent.click(screen.getByText(/Resumen Ejecutivo PDF/i));
        expect(autoTable).toHaveBeenCalled();
    });

    it('variacion_pct negativa muestra signo negativo en PDF', () => {
        const resumenNeg = { ...resumenMock, kpis: { ...resumenMock.kpis, variacion_pct: -5.3 } };
        render(<ExportacionDashboard resumen={resumenNeg} periodo="trimestre" />);
        fireEvent.click(screen.getByText(/Resumen Ejecutivo PDF/i));

        expect(autoTable).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({
                body: expect.arrayContaining([
                    expect.arrayContaining([
                        'Variación vs mes anterior',
                        expect.stringContaining('-5.3%'),
                    ]),
                ]),
            })
        );
    });
});
