import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, waitFor, cleanup, fireEvent } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import CartolaBancaria from './CartolaBancaria';

const PLAN_CUENTAS = [
    { codigo: '111102', nombre: 'Banco Chile', imputable: true },
    { codigo: '690199', nombre: 'Cuenta Puente', imputable: true },
];

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

describe('CartolaBancaria — importar excel/csv', () => {
    it('sube el archivo sin fijar Content-Type manual y muestra el mensaje real del backend', async () => {
        let initCapturado = null;

        setupFetchRouter({
            'GET /banco/cuentas': () => mockJsonResponse(200, { success: true, data: CUENTAS }),
            'GET /banco/movimientos': () => mockJsonResponse(200, { success: true, data: MOVIMIENTOS }),
            'GET /contabilidad/plan-cuentas': () => mockJsonResponse(200, { success: true, data: PLAN_CUENTAS }),
            'POST /banco/importar': (body, url, init) => {
                initCapturado = init;
                return mockJsonResponse(200, {
                    success: true,
                    message: 'Proceso completado. Importados: 2 | Ignorados (Duplicados): 0',
                    data: { importados: 2, ignorados: 0 },
                });
            },
        });

        const { container } = renderWithRouter(<CartolaBancaria />);

        await waitFor(() => expect(screen.getByText('Banco Chile - 000-111-222')).toBeTruthy());

        const fileInput = container.querySelector('input[type="file"]');
        const archivo = new File(['col1,col2\n1,2'], 'cartola.xls', { type: 'application/vnd.ms-excel' });
        fireEvent.change(fileInput, { target: { files: [archivo] } });

        await waitFor(() => expect(screen.getByText('cartola.xls')).toBeTruthy());

        // BuscadorCuentaContable autoselecciona la primera cuenta imputable al cargar.
        await waitFor(() => expect(screen.getByRole('button', { name: /Procesar/i })).toBeTruthy());

        fireEvent.click(screen.getByRole('button', { name: /Procesar/i }));

        await waitFor(() => expect(initCapturado).not.toBeNull());

        // Regresión: fijar 'Content-Type': 'multipart/form-data' a mano (sin boundary)
        // hacía que el body llegara vacío al servidor -- el fix es NO tocar este header
        // y dejar que fetch() lo autogenere con boundary a partir del FormData.
        const headers = initCapturado.headers || {};
        expect(headers['Content-Type']).toBeUndefined();
        expect(initCapturado.body).toBeInstanceOf(FormData);

        // Regresión: destructurar "{ data }" del resultado de api.upload() tomaba el
        // campo interno data:{importados,ignorados} en vez del body completo -- una
        // importación exitosa terminaba mostrando "Error de Importación" igual.
        await waitFor(() => {
            const llamadaExito = mockSwal.fire.mock.calls.find((args) => args[0]?.icon === 'success');
            expect(llamadaExito).toBeTruthy();
            expect(llamadaExito[0].text).toBe('Proceso completado. Importados: 2 | Ignorados (Duplicados): 0');
        });
    });
});
