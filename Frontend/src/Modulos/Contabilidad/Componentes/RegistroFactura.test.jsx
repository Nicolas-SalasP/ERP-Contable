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

describe('RegistroFactura - flujo completo de 3 pasos', () => {
    const proveedores = [
        { id: 1, razon_social: 'Proveedor Uno', rut: '76.111.111-1', codigo_interno: 'P1', pais_iso: 'CL', moneda_defecto: 'CLP' },
    ];

    // El BuscadorCuentaContable autoselecciona la "cuenta puente" (690199) al montar
    // sin valor, asi que cuentaDestino se completa solo al llegar al paso 3.
    const planCuentas = [
        { codigo: '690199', nombre: 'Cuenta Puente Por Clasificar', imputable: true },
        { codigo: '410101', nombre: 'Gasto Generico', imputable: true },
        { codigo: '353350', nombre: 'IVA Credito Fiscal', imputable: true },
        { codigo: '352105', nombre: 'Proveedores Nacionales', imputable: true },
    ];

    const elegirProveedorUno = () => {
        fireEvent.change(screen.getByPlaceholderText('Buscar RUT, Razón Social...'), { target: { value: 'Uno' } });
        fireEvent.click(screen.getByText('Proveedor Uno'));
    };

    const completarPaso1 = (numero = 'F-001') => {
        elegirProveedorUno();
        fireEvent.change(screen.getByPlaceholderText('Ej: 123456'), { target: { name: 'numeroFactura', value: numero } });
        fireEvent.change(screen.getByPlaceholderText('0'), { target: { value: '119000' } });
    };

    it('mantiene "Siguiente" deshabilitado hasta seleccionar proveedor y monto', async () => {
        setupMocks({ 'GET /proveedores': () => mockJsonResponse(200, { success: true, data: proveedores }) });
        renderWithRouter(<RegistroFactura />);
        await waitFor(() => screen.getByText('Registro de Factura'));

        expect(screen.getByRole('button', { name: /Siguiente/i }).disabled).toBe(true);

        completarPaso1();

        await waitFor(() => expect(screen.getByRole('button', { name: /Siguiente/i }).disabled).toBe(false));
    });

    it('una factura duplicada bloquea el avance y muestra la advertencia', async () => {
        setupMocks({
            'GET /proveedores': () => mockJsonResponse(200, { success: true, data: proveedores }),
            'GET /facturas/check': () => mockJsonResponse(200, { exists: true }),
        });
        renderWithRouter(<RegistroFactura />);
        await waitFor(() => screen.getByText('Registro de Factura'));

        completarPaso1('F-DUP');
        fireEvent.click(screen.getByRole('button', { name: /Siguiente/i }));

        expect(await screen.findByText('Factura Duplicada')).toBeDefined();
        expect(screen.queryByText('Seleccionar Cuenta de Pago (Destino)')).toBeNull();
    });

    it('completa los 3 pasos y guarda la factura mostrando el comprobante', async () => {
        const postSpy = vi.fn(() => mockJsonResponse(200, { success: true, data: { id: 99, comprobante_contable: 'COMP-2026-001' } }));
        setupMocks({
            'GET /proveedores': () => mockJsonResponse(200, { success: true, data: proveedores }),
            'GET /facturas/check': () => mockJsonResponse(200, { exists: false }),
            'GET /cuentas-bancarias/proveedor': () => mockJsonResponse(200, { success: true, data: [] }),
            'GET /contabilidad/plan-cuentas': () => mockJsonResponse(200, { success: true, data: planCuentas }),
            'POST /facturas': postSpy,
        });
        renderWithRouter(<RegistroFactura />);
        await waitFor(() => screen.getByText('Registro de Factura'));

        completarPaso1('F-001');
        fireEvent.click(screen.getByRole('button', { name: /Siguiente/i }));

        await screen.findByText('Fecha Vencimiento Factura');
        fireEvent.change(document.querySelector('input[name="fechaVencimiento"]'), { target: { name: 'fechaVencimiento', value: '2030-12-31' } });
        fireEvent.click(screen.getByRole('button', { name: /Siguiente/i }));

        await screen.findByText('Previsualización y Clasificación del Asiento');
        await screen.findByDisplayValue(/690199/);

        fireEvent.click(screen.getByRole('button', { name: /Confirmar y Guardar/i }));

        expect(await screen.findByText('Registro Exitoso')).toBeDefined();
        expect(screen.getByText('COMP-2026-001')).toBeDefined();
        expect(postSpy).toHaveBeenCalled();
    });
});
