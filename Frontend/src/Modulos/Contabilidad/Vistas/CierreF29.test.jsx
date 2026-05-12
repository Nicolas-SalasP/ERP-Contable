import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import CierreF29 from './CierreF29';
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

// =====================================================================
// FIXTURES
// =====================================================================

const datosACancelar = {
    success: true,
    data: {
        ya_cerrado: false,
        ventas: {
            iva_debito: 1900000,
            total_neto: 10000000,
        },
        compras: {
            iva_credito: 760000,
            total_neto: 4000000,
        },
        ppm: {
            monto: 90000,
            porcentaje: 0.9,
        },
        resumen: {
            total_a_pagar: 1230000,
            remanente: 0,
        },
    },
};

const datosRemanente = {
    success: true,
    data: {
        ya_cerrado: false,
        ventas: { iva_debito: 500000, total_neto: 2600000 },
        compras: { iva_credito: 800000, total_neto: 4200000 },
        ppm: { monto: 25000, porcentaje: 0.9 },
        resumen: {
            total_a_pagar: 0,
            remanente: 275000,
        },
    },
};

const datosYaCerrado = {
    success: true,
    data: {
        ...datosACancelar.data,
        ya_cerrado: true,
    },
};

// =====================================================================
// TESTS
// =====================================================================

describe('CierreF29 - render', () => {
    it('muestra el titulo del modulo', async () => {
        setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosACancelar),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => {
            expect(screen.getByText('Cierre de IVA (F29)')).toBeDefined();
        });
    });

    it('llama al endpoint simular al montar', async () => {
        const fetchMock = setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosACancelar),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => {
            const getCalls = fetchMock.mock.calls.filter(
                ([url, init]) =>
                    (init?.method === 'GET' || !init?.method) &&
                    url.includes('/impuestos/cierre-f29/simular/')
            );
            expect(getCalls.length).toBeGreaterThanOrEqual(1);
        });
    });
});

describe('CierreF29 - mostrar resultados', () => {
    it('muestra el IVA Debito de la respuesta', async () => {
        setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosACancelar),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => {
            const elementos = screen.queryAllByText(/1\.900\.000/);
            expect(elementos.length).toBeGreaterThan(0);
        });
    });

    it('muestra el IVA Credito de la respuesta', async () => {
        setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosACancelar),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => {
            const elementos = screen.queryAllByText(/760\.000/);
            expect(elementos.length).toBeGreaterThan(0);
        });
    });

    it('muestra "Monto a Pagar" cuando debito > credito', async () => {
        setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosACancelar),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => {
            expect(screen.getByText(/Monto a Pagar/i)).toBeDefined();
        });
    });

    it('muestra "Remanente para mes sgte." cuando credito > debito', async () => {
        setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosRemanente),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => {
            expect(screen.getByText(/Remanente para mes sgte/i)).toBeDefined();
        });
    });
});

describe('CierreF29 - ejecutar cierre', () => {
    it('muestra el boton "Generar Centralización" cuando hay debito > 0 o credito > 0', async () => {
        setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosACancelar),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Generar Centralizaci/i })).toBeDefined();
        });
    });

    it('NO muestra boton de centralizar si el periodo ya esta cerrado', async () => {
        setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosYaCerrado),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => screen.getByText('Cierre de IVA (F29)'));

        expect(screen.queryByRole('button', { name: /Generar Centralizaci/i })).toBeNull();
    });

    it('al hacer click en centralizar, llama POST /impuestos/cierre-f29/ejecutar', async () => {
        const fetchMock = setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosACancelar),
            'POST /impuestos/cierre-f29/ejecutar': () =>
                mockJsonResponse(200, { success: true, message: 'Cierre ejecutado' }),
        });

        renderWithRouter(<CierreF29 />);

        await waitFor(() => screen.getByText('Cierre de IVA (F29)'));

        const boton = await screen.findByRole('button', { name: /Generar Centralizaci/i });
        fireEvent.click(boton);

        await waitFor(() => {
            const postCalls = fetchMock.mock.calls.filter(
                ([url, init]) =>
                    init?.method === 'POST' &&
                    url.includes('/impuestos/cierre-f29/ejecutar')
            );
            expect(postCalls.length).toBeGreaterThanOrEqual(1);
        });
    });
});

describe('CierreF29 - cambio de periodo', () => {
    it('cambiar el mes dispara nueva simulacion', async () => {
        const fetchMock = setupFetchRouter({
            'GET /impuestos/cierre-f29/simular': () => mockJsonResponse(200, datosACancelar),
        });
        renderWithRouter(<CierreF29 />);

        await waitFor(() => screen.getByText('Cierre de IVA (F29)'));

        const llamadasIniciales = fetchMock.mock.calls.filter(([url]) =>
            url.includes('/impuestos/cierre-f29/simular/')
        ).length;

        const selects = document.querySelectorAll('select');
        let selectMes = null;
        for (const s of selects) {
            const options = Array.from(s.options).map((o) => o.value);
            if (options.includes('01') && options.includes('12')) {
                selectMes = s;
                break;
            }
        }
        expect(selectMes).toBeTruthy();

        fireEvent.change(selectMes, { target: { value: '06' } });

        await waitFor(() => {
            const llamadasNuevas = fetchMock.mock.calls.filter(([url]) =>
                url.includes('/impuestos/cierre-f29/simular/')
            ).length;
            expect(llamadasNuevas).toBeGreaterThan(llamadasIniciales);
        });
    });
});
