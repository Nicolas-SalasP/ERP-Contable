import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), log: vi.fn() },
}));

vi.mock('../../../Contextos/ToastContext', () => ({
    useToast: () => ({ toast: vi.fn() }),
}));

vi.mock('../../../Componentes/Skeleton', () => ({
    TablaSkeleton: () => (
        <tr data-testid="skeleton-carga">
            <td>Cargando...</td>
        </tr>
    ),
}));

vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacio: ({ mensaje }) => (
        <tr data-testid="estado-vacio">
            <td>{mensaje}</td>
        </tr>
    ),
}));

vi.mock('@e965/xlsx', () => ({
    utils: {
        json_to_sheet: vi.fn(() => ({})),
        book_new: vi.fn(() => ({})),
        book_append_sheet: vi.fn(),
    },
    writeFile: vi.fn(),
}));

import BalanceComprobacion from './BalanceComprobacion';
import { api } from '../../../Configuracion/api';
import * as XLSX from '@e965/xlsx';

afterEach(cleanup);

const cuentasMock = [
    {
        codigo: '1101',
        nombre: 'Caja',
        tipo: 'ACTIVO',
        anterior: { debe: 0, haber: 0, saldo_deudor: 50000, saldo_acreedor: 0 },
        mensual: { debe: 200000, haber: 100000, saldo_deudor: 100000, saldo_acreedor: 0 },
        acumulado: { debe: 250000, haber: 100000, saldo_deudor: 150000, saldo_acreedor: 0 },
    },
    {
        codigo: '4101',
        nombre: 'Ventas',
        tipo: 'INGRESO',
        anterior: { debe: 0, haber: 0, saldo_deudor: 0, saldo_acreedor: 50000 },
        mensual: { debe: 0, haber: 100000, saldo_deudor: 0, saldo_acreedor: 100000 },
        acumulado: { debe: 0, haber: 150000, saldo_deudor: 0, saldo_acreedor: 150000 },
    },
];

const totalesCuadrado = {
    anterior: { saldo_deudor: 50000, saldo_acreedor: 50000 },
    mensual: { debe: 200000, haber: 200000, saldo_deudor: 100000, saldo_acreedor: 100000 },
    acumulado: { saldo_deudor: 150000, saldo_acreedor: 150000 },
};

const totalesDescuadrado = {
    anterior: { saldo_deudor: 50000, saldo_acreedor: 50000 },
    mensual: { debe: 200000, haber: 100000, saldo_deudor: 100000, saldo_acreedor: 0 },
    acumulado: { saldo_deudor: 150000, saldo_acreedor: 150000 },
};

describe('BalanceComprobacion (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza el título Balance de Comprobacion y Saldos', () => {
        render(<BalanceComprobacion />);
        expect(screen.getByText('Balance de Comprobacion y Saldos')).toBeDefined();
    });

    it('muestra los filtros de Fecha Inicio y Fecha Fin', () => {
        render(<BalanceComprobacion />);
        expect(screen.getByText('Fecha Inicio')).toBeDefined();
        expect(screen.getByText('Fecha Fin')).toBeDefined();
    });

    it('muestra los botones Consultar y Excel', () => {
        render(<BalanceComprobacion />);
        expect(screen.getByRole('button', { name: /Consultar/i })).toBeDefined();
        expect(screen.getByRole('button', { name: /Excel/i })).toBeDefined();
    });

    it('muestra estado vacío con mensaje antes de consultar', () => {
        render(<BalanceComprobacion />);
        expect(screen.getByText('Seleccione un periodo y presione Consultar.')).toBeDefined();
    });

    it('carga y muestra cuentas en la tabla al consultar exitosamente', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { cuentas: cuentasMock, totales: totalesCuadrado },
        });

        render(<BalanceComprobacion />);

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Consultar/i }));
        });

        await waitFor(() => {
            expect(screen.getByText('Caja')).toBeDefined();
            expect(screen.getByText('Ventas')).toBeDefined();
        });

        expect(api.get).toHaveBeenCalledWith(
            expect.stringContaining('/contabilidad/reportes/balance-comprobacion')
        );
    });

    it('muestra los códigos de cuenta en la tabla', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { cuentas: cuentasMock, totales: totalesCuadrado },
        });

        render(<BalanceComprobacion />);

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Consultar/i }));
        });

        await waitFor(() => {
            expect(screen.getByText('1101')).toBeDefined();
            expect(screen.getByText('4101')).toBeDefined();
        });
    });

    it('muestra la fila de TOTALES en el tfoot tras consultar', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { cuentas: cuentasMock, totales: totalesCuadrado },
        });

        render(<BalanceComprobacion />);

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Consultar/i }));
        });

        await waitFor(() => {
            expect(screen.getByText('TOTALES')).toBeDefined();
        });
    });

    it('muestra advertencia de balance descuadrado cuando Debe y Haber difieren', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { cuentas: cuentasMock, totales: totalesDescuadrado },
        });

        render(<BalanceComprobacion />);

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Consultar/i }));
        });

        await waitFor(() => {
            expect(screen.getByText(/Balance descuadrado/)).toBeDefined();
        });
    });

    it('muestra estado vacío con mensaje apropiado cuando no hay cuentas en el periodo', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { cuentas: [], totales: null },
        });

        render(<BalanceComprobacion />);

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Consultar/i }));
        });

        await waitFor(() => {
            expect(screen.getByText(/No hay cuentas con movimientos/)).toBeDefined();
        });
    });

    it('llama a XLSX.writeFile al exportar Excel con datos cargados', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { cuentas: cuentasMock, totales: totalesCuadrado },
        });

        render(<BalanceComprobacion />);

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Consultar/i }));
        });

        await waitFor(() => {
            expect(screen.getByText('Caja')).toBeDefined();
        });

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Excel/i }));
        });

        expect(XLSX.utils.json_to_sheet).toHaveBeenCalled();
        expect(XLSX.writeFile).toHaveBeenCalled();
    });
});
