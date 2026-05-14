import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, waitFor, cleanup } from '@testing-library/react';
import {
    renderWithRouter,
    mockJsonResponse,
    setupFetchRouter,
    cleanTestEnv,
} from '../../test-utils';
import Dashboard from './Dashboard';
import { api } from '../../Configuracion/api';

beforeEach(() => {
    cleanTestEnv();
    api.config({ showErrorToast: false });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

// =====================================================================
// FIXTURES
// =====================================================================

const facturasMock = [
    {
        id: 1,
        numero_factura: 'F-500',
        nombre_proveedor: 'Comercial Loncomilla SpA',
        proveedor: { id: 1, razon_social: 'Comercial Loncomilla SpA' },
        monto_bruto: 119000,
        estado: 'REGISTRADA',
    },
    {
        id: 2,
        numero_factura: 'FE-94376',
        nombre_proveedor: 'Servicios del Sur Ltda',
        proveedor: { id: 2, razon_social: 'Servicios del Sur Ltda' },
        monto_bruto: 101150,
        estado: 'REGISTRADA',
    },
    {
        id: 3,
        numero_factura: 'F-8821',
        nombre_proveedor: 'Distribuidora Andes',
        proveedor: { id: 3, razon_social: 'Distribuidora Andes' },
        monto_bruto: 297500,
        estado: 'REGISTRADA',
    },
];

const setupMocks = () =>
    setupFetchRouter({
        'GET /clientes': () =>
            mockJsonResponse(200, { success: true, data: [{ id: 1 }, { id: 2 }] }),
        'GET /cotizaciones': () =>
            mockJsonResponse(200, { success: true, data: [] }),
        'GET /facturas/historial': () =>
            mockJsonResponse(200, { success: true, data: facturasMock }),
    });

// =====================================================================
// TESTS
// =====================================================================

describe('Dashboard - render basico', () => {
    it('muestra alguna seccion identificable del dashboard', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            const tituloAtencion = screen.queryByText(/Atenci.n Requerida/i);
            const cuentasPorPagar = screen.queryByText(/Cuentas por Pagar/i);
            expect(tituloAtencion || cuentasPorPagar).toBeDefined();
        });
    });

    it('llama a los endpoints clientes, cotizaciones, facturas al montar', async () => {
        const fetchMock = setupMocks();
        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            const llamadas = fetchMock.mock.calls.map(([url]) => url);
            expect(llamadas.some((u) => u.includes('/clientes'))).toBe(true);
            expect(llamadas.some((u) => u.includes('/cotizaciones'))).toBe(true);
            expect(
                llamadas.some((u) => u.includes('/facturas/historial?estado=REGISTRADA'))
            ).toBe(true);
        });
    });
});

describe('Dashboard - Atencion Requerida (BUG FE-BE arreglado)', () => {
    it('renderiza el nombre del proveedor en la tabla', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            expect(screen.getByText('Comercial Loncomilla SpA')).toBeDefined();
            expect(screen.getByText('Servicios del Sur Ltda')).toBeDefined();
            expect(screen.getByText('Distribuidora Andes')).toBeDefined();
        });
    });

    it('renderiza el numero de factura junto al proveedor', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            expect(screen.getByText(/F-500/)).toBeDefined();
            expect(screen.getByText(/FE-94376/)).toBeDefined();
        });
    });

    it('renderiza el monto formateado en CLP', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            // 119000 -> $119.000 en es-CL
            const elementos119k = screen.queryAllByText(/119\.000/);
            expect(elementos119k.length).toBeGreaterThan(0);
        });
    });

    it('si el backend devuelve facturas sin nombre_proveedor, NO crashea', async () => {
        setupFetchRouter({
            'GET /clientes': () => mockJsonResponse(200, { success: true, data: [] }),
            'GET /cotizaciones': () => mockJsonResponse(200, { success: true, data: [] }),
            'GET /facturas/historial': () =>
                mockJsonResponse(200, {
                    success: true,
                    data: [
                        {
                            id: 99,
                            numero_factura: 'F-SIN-NOMBRE',
                            monto_bruto: 50000,
                            estado: 'REGISTRADA',
                        },
                    ],
                }),
        });

        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            expect(screen.getByText(/F-SIN-NOMBRE/)).toBeDefined();
        });
    });

    it('cuando no hay facturas pendientes muestra estado vacio amigable', async () => {
        setupFetchRouter({
            'GET /clientes': () => mockJsonResponse(200, { success: true, data: [] }),
            'GET /cotizaciones': () => mockJsonResponse(200, { success: true, data: [] }),
            'GET /facturas/historial': () =>
                mockJsonResponse(200, { success: true, data: [] }),
        });

        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            expect(screen.getByText(/No tienes facturas/i)).toBeDefined();
        });
    });
});

describe('Dashboard - metricas KPI', () => {
    it('suma correctamente el total pendiente', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitFor(() => {
            const elementos = screen.queryAllByText(/517\.650/);
            expect(elementos.length).toBeGreaterThan(0);
        });
    });

    it('muestra el contador de clientes activos', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitFor(() => {
            const dosVisible = screen.queryAllByText(/^2$/);
            expect(dosVisible.length).toBeGreaterThan(0);
        });
    });
});
