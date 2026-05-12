import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../test-utils';
import GestionCotizaciones from './GestionCotizaciones';
import { api } from '../../Configuracion/api';

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

const cotizacionesMock = [
    {
        id: 1,
        numero: 'COT-0001',
        cliente: { id: 1, razon_social: 'Cliente Uno SpA', nombre_fantasia: 'Uno' },
        nombre_cliente: 'Cliente Uno SpA',
        estado: { id: 1, nombre: 'Borrador' },
        fecha_emision: '2026-05-01',
        fecha_validez: '2026-06-01',
        total: 1500000,
        monto_total: 1500000,
    },
    {
        id: 2,
        numero: 'COT-0002',
        cliente: { id: 2, razon_social: 'Cliente Dos SA', nombre_fantasia: 'Dos' },
        nombre_cliente: 'Cliente Dos SA',
        estado: { id: 2, nombre: 'Aprobada' },
        fecha_emision: '2026-04-15',
        fecha_validez: '2026-05-15',
        total: 800000,
        monto_total: 800000,
    },
    {
        id: 3,
        numero: 'COT-0003',
        cliente: { id: 3, razon_social: 'Cliente Tres', nombre_fantasia: 'Tres' },
        nombre_cliente: 'Cliente Tres',
        estado: { id: 3, nombre: 'Rechazada' },
        fecha_emision: '2026-04-10',
        fecha_validez: '2026-05-10',
        total: 500000,
        monto_total: 500000,
    },
];

const setupMocks = (overrides = {}) =>
    setupFetchRouter({
        'GET /cotizaciones': () => mockJsonResponse(200, { success: true, data: cotizacionesMock }),
        ...overrides,
    });

const waitForList = async () =>
    waitFor(() => expect(screen.getAllByText(/Cliente Uno/i).length).toBeGreaterThan(0));

describe('GestionCotizaciones - render', () => {
    it('muestra el titulo', async () => {
        setupMocks();
        renderWithRouter(<GestionCotizaciones />);
        await waitForList();
        expect(screen.getByText('Historial de Cotizaciones')).toBeDefined();
    });

    it('lista todas las cotizaciones del backend', async () => {
        setupMocks();
        renderWithRouter(<GestionCotizaciones />);
        await waitForList();

        expect(screen.getAllByText(/Cliente Uno/i).length).toBeGreaterThan(0);
        expect(screen.getAllByText(/Cliente Dos/i).length).toBeGreaterThan(0);
        expect(screen.getAllByText(/Cliente Tres/i).length).toBeGreaterThan(0);
    });

    it('muestra el icono de ayuda', async () => {
        setupMocks();
        renderWithRouter(<GestionCotizaciones />);
        await waitForList();

        expect(screen.getAllByTestId('ayuda-modulo-boton').length).toBeGreaterThan(0);
    });
});

describe('GestionCotizaciones - estados', () => {
    it('muestra el estado BORRADOR de las cotizaciones', async () => {
        setupMocks();
        renderWithRouter(<GestionCotizaciones />);
        await waitForList();

        const borradores = screen.queryAllByText(/borrador/i);
        expect(borradores.length).toBeGreaterThan(0);
    });

    it('muestra el estado APROBADA', async () => {
        setupMocks();
        renderWithRouter(<GestionCotizaciones />);
        await waitForList();

        const aprobadas = screen.queryAllByText(/aprobada/i);
        expect(aprobadas.length).toBeGreaterThan(0);
    });

    it('muestra el estado RECHAZADA', async () => {
        setupMocks();
        renderWithRouter(<GestionCotizaciones />);
        await waitForList();

        const rechazadas = screen.queryAllByText(/rechazada/i);
        expect(rechazadas.length).toBeGreaterThan(0);
    });
});

describe('GestionCotizaciones - cambio de estado', () => {
    it('muestra los botones Aceptar/Rechazar solo en cotizaciones Borrador o Enviada', async () => {
        setupMocks();
        renderWithRouter(<GestionCotizaciones />);
        await waitForList();
        const botonesAceptar = screen.getAllByRole('button', { name: /^Aceptar$/i });
        const botonesRechazar = screen.getAllByRole('button', { name: /^Rechazar$/i });
        expect(botonesAceptar.length).toBeGreaterThanOrEqual(1);
        expect(botonesRechazar.length).toBeGreaterThanOrEqual(1);
    });

    it('al hacer click en "Aceptar" y confirmar el modal, llama PUT /cotizaciones/{id}/estado', async () => {
        const fetchMock = setupMocks({
            'PUT /cotizaciones/1/estado': () =>
                mockJsonResponse(200, { success: true, message: 'OK' }),
        });

        renderWithRouter(<GestionCotizaciones />);
        await waitForList();
        const botonAceptar = screen.getAllByRole('button', { name: /^Aceptar$/i })[0];
        fireEvent.click(botonAceptar);
        const botonConfirmar = await screen.findByRole('button', { name: /Sí, Confirmar/i });
        fireEvent.click(botonConfirmar);
        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                ([url, init]) =>
                    init?.method === 'PUT' && url.includes('/cotizaciones/1/estado')
            );
            expect(putCalls.length).toBe(1);
            const body = JSON.parse(putCalls[0][1].body);
            expect(body.estado).toBe('Aceptada');
        });
    });

    it('al hacer click en "Rechazar" y confirmar, llama PUT con estado=Rechazada', async () => {
        const fetchMock = setupMocks({
            'PUT /cotizaciones/1/estado': () =>
                mockJsonResponse(200, { success: true, message: 'OK' }),
        });

        renderWithRouter(<GestionCotizaciones />);
        await waitForList();

        const botonRechazar = screen.getAllByRole('button', { name: /^Rechazar$/i })[0];
        fireEvent.click(botonRechazar);

        const botonConfirmar = await screen.findByRole('button', { name: /Sí, Confirmar/i });
        fireEvent.click(botonConfirmar);

        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                ([url, init]) =>
                    init?.method === 'PUT' && url.includes('/cotizaciones/1/estado')
            );
            expect(putCalls.length).toBe(1);

            const body = JSON.parse(putCalls[0][1].body);
            expect(body.estado).toBe('Rechazada');
        });
    });
});
