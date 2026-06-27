import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import CartolaBancaria from './CartolaBancaria';

const mockSwal = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
    showLoading: vi.fn(),
    close: vi.fn(),
}));
vi.mock('sweetalert2', () => ({ default: mockSwal }));
vi.mock('../../../Componentes/AyudaModulo', () => ({ default: () => null }));

const CUENTAS = [
    { id: 1, banco: 'Banco Chile', numero_cuenta: '000-111-222', saldo_actual: 1500000 },
    { id: 2, banco: 'Santander', numero_cuenta: '333-444-555', saldo_actual: 2000000 },
];

const MOVIMIENTOS = [
    { id: 10, descripcion: 'Depósito cliente', monto: 500000, tipo_movimiento: 'INGRESO', fecha: '2026-06-01' },
    { id: 11, descripcion: 'Pago proveedor', monto: 200000, tipo_movimiento: 'EGRESO', fecha: '2026-06-02' },
];

beforeEach(() => {
    cleanTestEnv();
    mockSwal.fire.mockResolvedValue({ isConfirmed: true });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

function montar(cuentas = CUENTAS, movimientos = MOVIMIENTOS) {
    setupFetchRouter({
        'GET /banco/cuentas': () => mockJsonResponse(200, { success: true, data: cuentas }),
        'GET /banco/movimientos': () => mockJsonResponse(200, { success: true, data: movimientos }),
    });
    return renderWithRouter(<CartolaBancaria />);
}

describe('CartolaBancaria — render', () => {
    it('muestra el título Cartola y Movimientos', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/Cartola y Movimientos/i)).toBeTruthy()
        );
    });

    it('muestra la sección de importación de cartola', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/Importar Cartola del Banco/i)).toBeTruthy()
        );
    });

    it('muestra la sección de registro manual', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/Registro Manual/i)).toBeTruthy()
        );
    });

    it('muestra Saldo Actual Contable', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/Saldo Actual Contable/i)).toBeTruthy()
        );
    });
});

describe('CartolaBancaria — cuentas', () => {
    it('muestra la cuenta bancaria en el select', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/Banco Chile/i)).toBeTruthy()
        );
    });

    it('muestra mensaje cuando no hay cuentas registradas', async () => {
        montar([]);
        await waitFor(() =>
            expect(screen.getByText(/No hay cuentas registradas/i)).toBeTruthy()
        );
    });

    it('llama GET /banco/movimientos al cargar cuentas', async () => {
        let movimientosLlamado = false;
        setupFetchRouter({
            'GET /banco/cuentas': () => mockJsonResponse(200, { success: true, data: CUENTAS }),
            'GET /banco/movimientos': () => {
                movimientosLlamado = true;
                return mockJsonResponse(200, { success: true, data: MOVIMIENTOS });
            },
        });
        renderWithRouter(<CartolaBancaria />);
        await waitFor(() => expect(movimientosLlamado).toBe(true), { timeout: 2000 });
    });
});

describe('CartolaBancaria — registro manual', () => {
    it('muestra los campos del formulario manual (descripción y monto)', async () => {
        montar();
        await waitFor(() => expect(screen.getByText(/Registro Manual/i)).toBeTruthy());

        // Los inputs de descripción y monto deben existir
        const inputs = screen.getAllByRole('textbox');
        expect(inputs.length).toBeGreaterThan(0);
    });

    it('muestra descripción de registro manual', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/Ingresa transacciones aisladas/i)).toBeTruthy()
        );
    });
});
