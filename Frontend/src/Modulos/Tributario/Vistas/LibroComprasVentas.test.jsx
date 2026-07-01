import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({
        tienePermiso: (p) => p === 'tributario.ver',
    }),
}));

vi.mock('../Servicios/tributarioApi', () => ({
    lcv: {
        ventas: vi.fn(),
        compras: vi.fn(),
        descargarVentas: vi.fn(),
        descargarCompras: vi.fn(),
    },
}));

import { lcv } from '../Servicios/tributarioApi';
import LibroComprasVentas from './LibroComprasVentas';

const ventasMock = {
    periodo: '06/2026',
    totales: { cantidad: 1, monto_neto: 100000, iva: 19000, monto_exento: 0, monto_total: 119000 },
    lineas: [
        {
            folio: 1,
            tipo_dte: 33,
            tipo_dte_glosa: 'Factura Afecta',
            rut_receptor: '12345678-9',
            razon_social: 'Cliente SA',
            fecha_emision: '2026-06-10',
            monto_neto: 100000,
            iva: 19000,
            monto_exento: 0,
            monto_total: 119000,
            estado: 'ACEPTADO',
        },
    ],
};

const comprasMock = {
    periodo: '06/2026',
    totales: { cantidad: 1, monto_neto: 50000, iva: 9500, monto_total: 59500 },
    lineas: [
        {
            numero_factura: 100,
            tipo_dte: 33,
            tipo_dte_glosa: 'Factura Afecta',
            rut_emisor: '76111222-3',
            razon_social: 'Proveedor SA',
            fecha_emision: '2026-06-05',
            monto_neto: 50000,
            iva_recuperable: 9500,
            monto_no_recuperable: 0,
            monto_total: 59500,
            indicador_uso: 'SI',
        },
    ],
};

afterEach(cleanup);

describe('LibroComprasVentas — render inicial', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('muestra título "Libro de Compras y Ventas"', () => {
        render(<LibroComprasVentas />);
        expect(screen.getByText('Libro de Compras y Ventas')).toBeTruthy();
    });

    it('muestra estado vacío antes de consultar', () => {
        render(<LibroComprasVentas />);
        expect(screen.getByText(/Selecciona un período y presiona Consultar/i)).toBeTruthy();
    });
});

describe('LibroComprasVentas — consultar', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('llama lcv.ventas y lcv.compras al hacer click en Consultar', async () => {
        lcv.ventas.mockResolvedValue(ventasMock);
        lcv.compras.mockResolvedValue(comprasMock);

        render(<LibroComprasVentas />);

        const btn = screen.getByRole('button', { name: /Consultar/i });
        await act(async () => { fireEvent.click(btn); });

        expect(lcv.ventas).toHaveBeenCalledTimes(1);
        expect(lcv.compras).toHaveBeenCalledTimes(1);
    });

    it('muestra filas de ventas después de consultar', async () => {
        lcv.ventas.mockResolvedValue(ventasMock);
        lcv.compras.mockResolvedValue(comprasMock);

        render(<LibroComprasVentas />);

        const btn = screen.getByRole('button', { name: /Consultar/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText('Cliente SA')).toBeTruthy();
        });
    });

    it('tab Compras muestra datos de compras', async () => {
        lcv.ventas.mockResolvedValue(ventasMock);
        lcv.compras.mockResolvedValue(comprasMock);

        render(<LibroComprasVentas />);

        const btnConsultar = screen.getByRole('button', { name: /Consultar/i });
        await act(async () => { fireEvent.click(btnConsultar); });

        await waitFor(() => {
            expect(screen.getByText('Cliente SA')).toBeTruthy();
        });

        const tabCompras = screen.getByRole('button', { name: /Libro de Compras/i });
        await act(async () => { fireEvent.click(tabCompras); });

        await waitFor(() => {
            expect(screen.getByText('Proveedor SA')).toBeTruthy();
        });
    });

    it('muestra error cuando la API falla', async () => {
        lcv.ventas.mockRejectedValue({ response: { data: { mensaje: 'Error de conexión al LCV' } } });
        lcv.compras.mockResolvedValue(comprasMock);

        render(<LibroComprasVentas />);

        const btn = screen.getByRole('button', { name: /Consultar/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText(/Error de conexión al LCV/i)).toBeTruthy();
        });
    });
});

describe('LibroComprasVentas — descarga', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.URL.createObjectURL = vi.fn(() => 'blob:mock');
        global.URL.revokeObjectURL = vi.fn();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('llama lcv.descargarVentas al hacer click en descarga CSV', async () => {
        lcv.ventas.mockResolvedValue(ventasMock);
        lcv.compras.mockResolvedValue(comprasMock);
        lcv.descargarVentas.mockResolvedValue({ data: new Blob(['test']) });

        const originalCreateElement = document.createElement.bind(document);
        vi.spyOn(document, 'createElement').mockImplementation((tag) => {
            if (tag === 'a') return { href: '', download: '', click: vi.fn() };
            return originalCreateElement(tag);
        });

        render(<LibroComprasVentas />);

        const btnConsultar = screen.getByRole('button', { name: /Consultar/i });
        await act(async () => { fireEvent.click(btnConsultar); });

        await waitFor(() => {
            expect(screen.getByText('Cliente SA')).toBeTruthy();
        });

        const btnCsv = screen.getByRole('button', { name: /CSV \(formato SII\)/i });
        await act(async () => { fireEvent.click(btnCsv); });

        await waitFor(() => {
            expect(lcv.descargarVentas).toHaveBeenCalledTimes(1);
        });
    });
});
