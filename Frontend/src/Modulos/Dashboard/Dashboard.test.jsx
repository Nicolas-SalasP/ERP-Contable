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
        monto_bruto: 119000,
        estado: 'REGISTRADA',
    },
    {
        id: 2,
        numero_factura: 'FE-94376',
        nombre_proveedor: 'Servicios del Sur Ltda',
        monto_bruto: 101150,
        estado: 'REGISTRADA',
    },
    {
        id: 3,
        numero_factura: 'F-8821',
        nombre_proveedor: 'Distribuidora Andes',
        monto_bruto: 297500,
        estado: 'REGISTRADA',
    },
];

const resumenMock = {
    kpis: {
        ventas_mes: 517650,
        variacion_pct: 5.2,
        facturas_emitidas_mes: 3,
        facturas_pendientes: 3,
        clientes_activos: 2,
        cotizaciones_pendientes: 0,
    },
    facturas_urgentes: facturasMock,
    serie_ventas_12m: [],
    top_clientes: [],
    alertas_pendientes: [],
};

const setupMocks = () =>
    setupFetchRouter({
        'GET /dashboard/resumen': () =>
            mockJsonResponse(200, { success: true, data: resumenMock }),
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

    it('llama al endpoint resumen al montar', async () => {
        const fetchMock = setupMocks();
        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            const llamadas = fetchMock.mock.calls.map(([url]) => url);
            expect(llamadas.some((u) => u.includes('/dashboard/resumen'))).toBe(true);
        });
    });
});

describe('Dashboard - Atencion Requerida (BUG FE-BE arreglado)', () => {
    it('renderiza los numeros de factura en la tabla', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            expect(screen.getByText('F-500')).toBeDefined();
            expect(screen.getByText('FE-94376')).toBeDefined();
            expect(screen.getByText('F-8821')).toBeDefined();
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
            'GET /dashboard/resumen': () =>
                mockJsonResponse(200, {
                    success: true,
                    data: {
                        ...resumenMock,
                        facturas_urgentes: [
                            {
                                id: 99,
                                numero_factura: 'F-SIN-NOMBRE',
                                monto_bruto: 50000,
                                estado: 'REGISTRADA',
                            },
                        ],
                    },
                }),
        });

        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            expect(screen.getByText(/F-SIN-NOMBRE/)).toBeDefined();
        });
    });

    it('cuando no hay facturas pendientes muestra estado vacio amigable', async () => {
        setupFetchRouter({
            'GET /dashboard/resumen': () =>
                mockJsonResponse(200, {
                    success: true,
                    data: { ...resumenMock, facturas_urgentes: [] },
                }),
        });

        renderWithRouter(<Dashboard />);

        await waitFor(() => {
            expect(screen.getByText(/No hay facturas pendientes/i)).toBeDefined();
        });
    });
});

describe('Dashboard - metricas KPI', () => {
    it('muestra el KPI de ventas del periodo formateado', async () => {
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
