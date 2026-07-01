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

vi.mock('./componentes/GraficoVentas', () => ({ default: () => null }));
vi.mock('./componentes/GraficoTopClientes', () => ({ default: () => null }));
vi.mock('./componentes/GraficoComprasVsVentas', () => ({ default: () => null }));
vi.mock('./componentes/GraficoStock', () => ({ default: () => null }));
vi.mock('./componentes/AlertasPendientes', () => ({ default: () => null }));
vi.mock('./componentes/RhrrhResumen', () => ({ default: () => null }));
vi.mock('./componentes/ExportacionDashboard', () => ({ default: () => null }));
vi.mock('../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn() },
}));
vi.mock('../../Contextos/Permisos', () => ({
    usePermisos: () => ({ tieneAlgunPermiso: () => false }),
}));

// =====================================================================
// FIXTURES
// =====================================================================

const FACTURAS_URGENTES = [
    {
        id: 1,
        numero_factura: 'F-500',
        nombre_proveedor: 'Comercial Loncomilla SpA',
        monto_bruto: 119000,
        estado: 'REGISTRADA',
        fecha_emision: '2026-06-01',
        fecha_vencimiento: '2026-07-01',
    },
    {
        id: 2,
        numero_factura: 'FE-94376',
        nombre_proveedor: 'Servicios del Sur Ltda',
        monto_bruto: 101150,
        estado: 'REGISTRADA',
        fecha_emision: '2026-06-02',
        fecha_vencimiento: '2026-07-02',
    },
    {
        id: 3,
        numero_factura: 'F-8821',
        nombre_proveedor: 'Distribuidora Andes',
        monto_bruto: 297500,
        estado: 'REGISTRADA',
        fecha_emision: '2026-05-15',
        fecha_vencimiento: '2026-06-15',
    },
];

const RESUMEN_DEFAULT = {
    kpis: {
        ventas_mes: 450000,
        variacion_pct: 12.5,
        facturas_emitidas_mes: 5,
        facturas_pendientes: 3,
        clientes_activos: 2,
        cotizaciones_pendientes: 1,
    },
    facturas_urgentes: FACTURAS_URGENTES,
    serie_ventas_12m: [],
    top_clientes: [],
    alertas_pendientes: [],
    compras_12m: [],
};

const setupMocks = (resumen = RESUMEN_DEFAULT) =>
    setupFetchRouter({
        'GET /dashboard/resumen': () =>
            mockJsonResponse(200, { success: true, data: resumen }),
    });

const waitForDashboard = () =>
    waitFor(() => expect(screen.getByText(/Resumen Ejecutivo/i)).toBeDefined());

// =====================================================================
// TESTS
// =====================================================================

beforeEach(() => {
    cleanTestEnv();
    api.config({ showErrorToast: false });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('Dashboard - render basico', () => {
    it('muestra el titulo Resumen Ejecutivo', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();
        expect(screen.getByText('Resumen Ejecutivo')).toBeDefined();
    });

    it('llama a GET /dashboard/resumen al montar', async () => {
        const fetchMock = setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();
        const llamadas = fetchMock.mock.calls.map(([url]) => url);
        expect(llamadas.some((u) => u.includes('/dashboard/resumen'))).toBe(true);
    });

    it('muestra seccion de facturas urgentes', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();
        expect(screen.getByText(/Facturas que requieren atenci.n/i)).toBeDefined();
    });
});

describe('Dashboard - tabla de facturas urgentes', () => {
    it('renderiza las tres facturas urgentes en la tabla', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();

        await waitFor(() => {
            expect(screen.getByText('F-500')).toBeDefined();
            expect(screen.getByText('FE-94376')).toBeDefined();
            expect(screen.getByText('F-8821')).toBeDefined();
        });
    });

    it('renderiza el estado de cada factura', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();

        await waitFor(() => {
            const badges = screen.queryAllByText(/REGISTRADA/);
            expect(badges.length).toBeGreaterThan(0);
        });
    });

    it('renderiza el monto formateado en CLP', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();

        await waitFor(() => {
            const elementos = screen.queryAllByText(/119\.000/);
            expect(elementos.length).toBeGreaterThan(0);
        });
    });

    it('si factura no tiene nombre_proveedor no crashea', async () => {
        setupMocks({
            ...RESUMEN_DEFAULT,
            facturas_urgentes: [
                {
                    id: 99,
                    numero_factura: 'F-SIN-NOMBRE',
                    monto_bruto: 50000,
                    estado: 'REGISTRADA',
                },
            ],
        });

        renderWithRouter(<Dashboard />);
        await waitForDashboard();

        await waitFor(() => {
            expect(screen.getByText(/F-SIN-NOMBRE/)).toBeDefined();
        });
    });

    it('cuando no hay facturas urgentes muestra estado vacio', async () => {
        setupMocks({
            ...RESUMEN_DEFAULT,
            facturas_urgentes: [],
        });

        renderWithRouter(<Dashboard />);
        await waitForDashboard();

        await waitFor(() => {
            expect(screen.getByText(/¡Todo al día!/i)).toBeDefined();
        });
    });
});

describe('Dashboard - KPIs', () => {
    it('muestra ventas del periodo formateadas', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();

        await waitFor(() => {
            const elementos = screen.queryAllByText(/450\.000/);
            expect(elementos.length).toBeGreaterThan(0);
        });
    });

    it('muestra el contador de clientes activos', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();

        await waitFor(() => {
            const dosVisible = screen.queryAllByText(/^2$/);
            expect(dosVisible.length).toBeGreaterThan(0);
        });
    });

    it('muestra facturas pendientes', async () => {
        setupMocks();
        renderWithRouter(<Dashboard />);
        await waitForDashboard();

        await waitFor(() => {
            const elementos = screen.queryAllByText(/^3$/);
            expect(elementos.length).toBeGreaterThan(0);
        });
    });
});
