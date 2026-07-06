import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../test-utils';
import VisorCliente from './VisorCliente';
import { api } from '../../Configuracion/api';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true, value: '' }),
    showLoading: vi.fn(),
    close: vi.fn(),
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

const renderConRuta = (id = '9') =>
    render(
        <MemoryRouter initialEntries={[`/clientes/visor/${id}`]}>
            <Routes>
                <Route path="/clientes/visor/:id" element={<VisorCliente />} />
                <Route path="/clientes" element={<div>Listado Clientes</div>} />
            </Routes>
        </MemoryRouter>
    );

const fichaCliente = {
    success: true,
    data: {
        cliente: {
            id: 9,
            razon_social: 'Cliente Test SpA',
            rut: '76.999.888-1',
            email: 'cliente@test.cl',
            telefono: '+56 9 8765 4321',
            direccion: 'Av. Siempre Viva 742',
        },
        facturas: [
            { id: 501, numero_factura: '100', tipo_documento: 'FACTURA', fecha_emision: '2026-06-01', monto_bruto: 119000, estado: 'REGISTRADA', archivo_pdf: null },
            { id: 502, numero_factura: '101', tipo_documento: 'NOTA_CREDITO', fecha_emision: '2026-06-05', monto_bruto: 20000, estado: 'VIGENTE', archivo_pdf: null },
            { id: 503, numero_factura: '102', tipo_documento: 'NOTA_DEBITO', fecha_emision: '2026-06-06', monto_bruto: 5000, estado: 'VIGENTE', archivo_pdf: null },
            { id: 504, numero_factura: '99', tipo_documento: 'FACTURA', fecha_emision: '2026-05-01', monto_bruto: 50000, estado: 'PAGADA', archivo_pdf: null },
        ],
    },
};

const setupMocks = (overrides = {}) =>
    setupFetchRouter({
        'GET /clientes/ficha/9': () => mockJsonResponse(200, fichaCliente),
        'GET /clientes': () => mockJsonResponse(200, { success: true, data: [fichaCliente.data.cliente] }),
        ...overrides,
    });

const waitForLoad = async () =>
    waitFor(() => expect(screen.getAllByText(/Cliente Test SpA/i).length).toBeGreaterThan(0));

describe('VisorCliente', () => {
    it('renderiza la ficha del cliente correctamente', async () => {
        setupMocks();
        renderConRuta('9');
        await waitForLoad();
        expect(screen.getByText(/76.999.888-1/)).toBeTruthy();
    });

    it('muestra el saldo por cobrar calculado (factura + ND vigente - NC vigente)', async () => {
        setupMocks();
        renderConRuta('9');
        await waitForLoad();
        // Deuda vigente: factura 119000 + ND 5000 = 124000; a favor: NC 20000 => saldo neto 104000
        expect(screen.getByText(/104.000|104,000/)).toBeTruthy();
        expect(screen.getAllByText(/Por Cobrar/i).length).toBeGreaterThan(0);
    });

    it('muestra las 4 filas del historial con sus tipos FAC/NC/ND', async () => {
        setupMocks();
        renderConRuta('9');
        await waitForLoad();
        expect(screen.getAllByText('FAC').length).toBe(2);
        expect(screen.getByText('NC')).toBeTruthy();
        expect(screen.getByText('ND')).toBeTruthy();
    });

    it('la factura pagada se muestra como Cerrado', async () => {
        setupMocks();
        renderConRuta('9');
        await waitForLoad();
        expect(screen.getByText('Cerrado')).toBeTruthy();
    });

    it('sin id en la ruta, muestra el buscador de clientes', async () => {
        render(
            <MemoryRouter initialEntries={['/clientes/visor']}>
                <Routes>
                    <Route path="/clientes/visor" element={<VisorCliente />} />
                </Routes>
            </MemoryRouter>
        );
        await waitFor(() => expect(screen.getByText(/Visor del Cliente/i)).toBeTruthy());
        expect(screen.getByRole('button', { name: /Seleccionar Cliente/i })).toBeTruthy();
    });
});
