import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';
import * as jestDomMatchers from '@testing-library/jest-dom/matchers';
expect.extend(jestDomMatchers);

// vi.mock ANTES de imports del módulo bajo test
vi.mock('../../../Contextos/ToastContext', () => ({
    useToast: () => ({ toast: vi.fn() }),
}));
vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn() },
}));
vi.mock('@e965/xlsx', () => ({
    default: {
        utils: {
            json_to_sheet: vi.fn(() => ({})),
            book_new: vi.fn(() => ({})),
            book_append_sheet: vi.fn(),
        },
        writeFile: vi.fn(),
    },
}));
vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));
vi.mock('../../../Componentes/Skeleton', () => ({
    TablaSkeleton: () => <tr><td>Cargando...</td></tr>,
}));
vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacio: ({ mensaje }) => <tr><td>{mensaje}</td></tr>,
}));
vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), log: vi.fn(), warn: vi.fn() },
}));

import { api } from '../../../Configuracion/api';
import LibroMayor from './LibroMayor';

afterEach(cleanup);

describe('LibroMayor (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        localStorage.clear();
        api.get.mockImplementation((url) => {
            if (url.includes('plan-cuentas')) return Promise.resolve({ success: true, data: [] });
            return Promise.resolve({ success: true, data: [] });
        });
    });

    it('renderiza el título Libros Contables', () => {
        render(<LibroMayor />);
        expect(screen.getByText('Libros Contables')).toBeInTheDocument();
    });

    it("renderiza el botón de pestaña 'Libro Diario / Mayor'", () => {
        render(<LibroMayor />);
        expect(screen.getByText('Libro Diario / Mayor')).toBeInTheDocument();
    });

    it('muestra estado vacío cuando la api retorna lista vacía', async () => {
        render(<LibroMayor />);
        await waitFor(() => {
            expect(screen.getByText('Sin movimientos en el período')).toBeInTheDocument();
        });
    });

    it('renderiza filas cuando la api retorna asientos en formato array con detalles', async () => {
        api.get.mockImplementation((url) => {
            if (url.includes('plan-cuentas')) return Promise.resolve({ success: true, data: [] });
            return Promise.resolve({
                success: true,
                data: [{
                    id: 1,
                    fecha: '2024-01-15',
                    detalles: [{
                        cuenta_contable: '1101',
                        cuenta: { nombre: 'Caja' },
                        debe: 100000,
                        haber: 0,
                    }],
                    glosa: 'Venta contado',
                    estado: 'CONCILIADO',
                    numero_comprobante: 'C-001',
                }],
            });
        });

        render(<LibroMayor />);

        await waitFor(() => {
            expect(screen.getByText('Venta contado')).toBeInTheDocument();
        });
        expect(screen.getByText('1101')).toBeInTheDocument();
        expect(screen.getByText('Caja')).toBeInTheDocument();
    });

    it('renderiza filas cuando la api retorna datos en formato movimientos', async () => {
        api.get.mockImplementation((url) => {
            if (url.includes('plan-cuentas')) return Promise.resolve({ success: true, data: [] });
            return Promise.resolve({
                success: true,
                data: {
                    cuenta: '1101 - Caja',
                    movimientos: [{
                        comprobante: 'C-001',
                        fecha: '2024-01-15',
                        glosa: 'Pago proveedor',
                        estado: 'CONCILIADO',
                        debe: 50000,
                        haber: 0,
                    }],
                },
            });
        });

        render(<LibroMayor />);

        await waitFor(() => {
            expect(screen.getByText('Pago proveedor')).toBeInTheDocument();
        });
        expect(screen.getByText('C-001')).toBeInTheDocument();
    });

    it('el botón Consultar invoca api.get con la ruta del libro diario', async () => {
        render(<LibroMayor />);

        await act(async () => {
            fireEvent.click(screen.getByText('Consultar'));
        });

        const urlsLlamadas = api.get.mock.calls.map(([url]) => url);
        expect(urlsLlamadas.some((url) => url.includes('libro-diario'))).toBe(true);
    });

    it('muestra TablaSkeleton mientras se cargan los datos', async () => {
        let resolverLibroDiario;
        api.get.mockImplementation((url) => {
            if (url.includes('plan-cuentas')) return Promise.resolve({ success: true, data: [] });
            return new Promise((resolve) => { resolverLibroDiario = resolve; });
        });

        render(<LibroMayor />);

        await waitFor(() => {
            expect(screen.getByText('Cargando...')).toBeInTheDocument();
        });

        await act(async () => {
            resolverLibroDiario({ success: true, data: [] });
        });
    });

    it('el campo de búsqueda de cuenta responde a cambios de texto', () => {
        render(<LibroMayor />);
        const inputCuenta = screen.getByPlaceholderText("Escribe 'Caja' o '1101'...");
        fireEvent.change(inputCuenta, { target: { value: 'Caja' } });
        expect(inputCuenta.value).toBe('Caja');
    });

    it("el estado vacío muestra el mensaje 'Sin movimientos en el período' una vez finalizada la carga", async () => {
        render(<LibroMayor />);
        await waitFor(() => {
            expect(screen.queryByText('Cargando...')).not.toBeInTheDocument();
            expect(screen.getByText('Sin movimientos en el período')).toBeInTheDocument();
        });
    });
});
