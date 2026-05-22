import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import RegistroFactura from './RegistroFactura';
import { api } from '../../../Configuracion/api';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
}));
vi.mock('sweetalert2', () => ({ default: swalMock }));

beforeEach(() => {
    cleanTestEnv();
    api.config({ showErrorToast: false });
    swalMock.fire.mockClear();
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

const proveedoresMock = [
    { id: 1, razon_social: 'Proveedor Uno', nombre_fantasia: 'Uno SpA', rut: '76.111.111-1' },
    { id: 2, razon_social: 'Proveedor Dos', nombre_fantasia: 'Dos SA', rut: '76.222.222-2' },
];

const setupMocks = (overrides = {}) =>
    setupFetchRouter({
        'GET /proveedores': () => mockJsonResponse(200, { success: true, data: proveedoresMock }),
        ...overrides,
    });

describe('RegistroFactura - render basico', () => {
    it('muestra el titulo "Registro de Factura"', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);

        await waitFor(() => {
            expect(screen.getByText('Registro de Factura')).toBeDefined();
        });
    });

    it('muestra el icono de ayuda contextual', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);
        await waitFor(() => screen.getByText('Registro de Factura'));

        const ayuda = screen.getAllByTestId('ayuda-modulo-boton');
        expect(ayuda.length).toBeGreaterThan(0);
    });

    it('inicia con tipo de documento FACTURA por defecto', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);

        await waitFor(() => {
            const select = screen.getByDisplayValue('Factura');
            expect(select).toBeDefined();
        });
    });
});

describe('RegistroFactura - tipo Nota de Credito (FIX: factura_referencia_id)', () => {
    it('NO muestra campo factura_referencia_id cuando es FACTURA', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);

        await waitFor(() => screen.getByText('Registro de Factura'));

        expect(screen.queryByText(/ID de Factura Original/i)).toBeNull();
        expect(screen.queryByPlaceholderText("Ej: 123")).toBeNull();
    });

    it('AL cambiar a Nota de Credito, aparece campo factura_referencia_id', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);

        await waitFor(() => screen.getByText('Registro de Factura'));

        const select = screen.getByDisplayValue('Factura');
        fireEvent.change(select, { target: { name: 'tipoDocumento', value: 'NOTA_CREDITO' } });

        await waitFor(() => {
            expect(screen.getByText(/ID de Factura Original/i)).toBeDefined();
        });
    });

    it('al cambiar a NC, muestra advertencia que el monto no puede superar al original', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);
        await waitFor(() => screen.getByText('Registro de Factura'));

        const select = screen.getByDisplayValue('Factura');
        fireEvent.change(select, { target: { name: 'tipoDocumento', value: 'NOTA_CREDITO' } });

        await waitFor(() => {
            expect(
                screen.getByText(/monto de la NC no puede ser mayor/i)
            ).toBeDefined();
        });
    });

    it('al cambiar de NC a FACTURA, el campo desaparece', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);
        await waitFor(() => screen.getByText('Registro de Factura'));

        const select = screen.getByDisplayValue('Factura');

        fireEvent.change(select, { target: { name: 'tipoDocumento', value: 'NOTA_CREDITO' } });
        await waitFor(() => screen.getByText(/ID de Factura Original/i));

        fireEvent.change(select, { target: { name: 'tipoDocumento', value: 'FACTURA' } });

        await waitFor(() => {
            expect(screen.queryByText(/ID de Factura Original/i)).toBeNull();
        });
    });

    it('NO muestra campo factura_referencia_id cuando es NOTA_DEBITO', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);
        await waitFor(() => screen.getByText('Registro de Factura'));

        const select = screen.getByDisplayValue('Factura');
        fireEvent.change(select, { target: { name: 'tipoDocumento', value: 'NOTA_DEBITO' } });

        await waitFor(() => {
            expect(screen.queryByText(/ID de Factura Original/i)).toBeNull();
        });
    });

    it('el input acepta numero como valor', async () => {
        setupMocks();
        renderWithRouter(<RegistroFactura />);
        await waitFor(() => screen.getByText('Registro de Factura'));

        const select = screen.getByDisplayValue('Factura');
        fireEvent.change(select, { target: { name: 'tipoDocumento', value: 'NOTA_CREDITO' } });

        await waitFor(() => screen.getByText(/ID de Factura Original/i));

        const inputRef = screen.getByPlaceholderText("Ej: 123");
        fireEvent.change(inputRef, { target: { name: 'factura_referencia_id', value: '456' } });

        expect(inputRef.value).toBe('456');
    });
});
