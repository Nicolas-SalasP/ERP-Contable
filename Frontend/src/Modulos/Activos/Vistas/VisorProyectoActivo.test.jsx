import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import VisorProyectoActivo from './VisorProyectoActivo';
import { api } from '../../../Configuracion/api';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
}));
vi.mock('sweetalert2', () => ({ default: swalMock }));

beforeEach(() => {
    cleanTestEnv();
    api.config({ showErrorToast: false });
    swalMock.fire.mockClear();
    swalMock.fire.mockResolvedValue({ isConfirmed: true });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

// =====================================================================
// FIXTURES
// =====================================================================

const proyectoEnConstruccion = {
    success: true,
    data: {
        id: 5,
        nombre: 'Bodega Sur 2026',
        estado: 'EN_CONSTRUCCION',
        valor_total_original: 1500000,
        depreciacion_acumulada: 0,
        vida_util_meses: 60,
        anio_fabricacion: 2026,
        tipo_activo_id: 12101,
        cuenta_depreciacion_id: null,
        cuenta_gasto_id: null,
        facturas: [
            { id: 101, numero: 'FAC-001', proveedor: 'Cemento SA', monto: 800000 },
            { id: 102, numero: 'FAC-002', proveedor: 'Maderas Ltda', monto: 700000 },
        ],
    },
};

const proyectoActivado = {
    success: true,
    data: {
        ...proyectoEnConstruccion.data,
        estado: 'ACTIVADO',
    },
};

const parametrosMock = {
    success: true,
    data: {
        cuentas_activo: [],
        cuentas_depreciacion: [],
        cuentas_gasto: [],
    },
};

const setupMocks = (proyectoData, overrides = {}) => {
    return setupFetchRouter({
        'GET /activos/proyectos/5/analisis': () =>
            mockJsonResponse(200, proyectoData),
        'GET /activos/parametros': () =>
            mockJsonResponse(200, parametrosMock),
        ...overrides,
    });
};

const waitForProject = async () =>
    waitFor(() => expect(screen.getByText('Bodega Sur 2026')).toBeDefined());

// =====================================================================
// TESTS
// =====================================================================

describe('VisorProyectoActivo - render', () => {
    it('muestra el nombre y estado del proyecto', async () => {
        setupMocks(proyectoEnConstruccion);
        renderWithRouter(
            <VisorProyectoActivo
                proyectoId={5}
                onVolver={vi.fn()}
                onNotificar={vi.fn()}
            />
        );
        await waitForProject();
        expect(screen.getByText(/EN CONSTRUCCION/i)).toBeDefined();
    });

    it('lista las facturas vinculadas con su monto', async () => {
        setupMocks(proyectoEnConstruccion);
        renderWithRouter(
            <VisorProyectoActivo proyectoId={5} onVolver={vi.fn()} onNotificar={vi.fn()} />
        );
        await waitForProject();

        expect(screen.getByText('FAC-001')).toBeDefined();
        expect(screen.getByText('FAC-002')).toBeDefined();
        expect(screen.getByText('Cemento SA')).toBeDefined();
        expect(screen.getByText('Maderas Ltda')).toBeDefined();
    });
});

describe('VisorProyectoActivo - desvincular factura', () => {
    it('muestra columna Acciones SOLO si el proyecto esta en construccion', async () => {
        setupMocks(proyectoEnConstruccion);
        renderWithRouter(
            <VisorProyectoActivo proyectoId={5} onVolver={vi.fn()} onNotificar={vi.fn()} />
        );
        await waitForProject();
        expect(screen.getByText('Acciones')).toBeDefined();
        expect(screen.getAllByTitle(/desvincular/i).length).toBe(2);
    });

    it('NO muestra columna Acciones en proyectos ACTIVADOS', async () => {
        setupMocks(proyectoActivado);
        renderWithRouter(
            <VisorProyectoActivo proyectoId={5} onVolver={vi.fn()} onNotificar={vi.fn()} />
        );
        await waitForProject();

        expect(screen.queryByText('Acciones')).toBeNull();
        expect(screen.queryAllByTitle(/desvincular/i).length).toBe(0);
    });

    it('al confirmar, llama DELETE /activos/proyectos/{p}/facturas/{f}', async () => {
        const fetchMock = setupMocks(proyectoEnConstruccion, {
            'DELETE /activos/proyectos/5/facturas/101': () =>
                mockJsonResponse(200, { success: true }),
        });

        const onNotificar = vi.fn();
        renderWithRouter(
            <VisorProyectoActivo proyectoId={5} onVolver={vi.fn()} onNotificar={onNotificar} />
        );
        await waitForProject();

        const botonesDesv = screen.getAllByTitle(/desvincular/i);
        fireEvent.click(botonesDesv[0]);

        await waitFor(() => expect(swalMock.fire).toHaveBeenCalled());

        await waitFor(() => {
            const deleteCalls = fetchMock.mock.calls.filter(
                ([url, init]) =>
                    init?.method === 'DELETE' && url.includes('/activos/proyectos/5/facturas/101')
            );
            expect(deleteCalls.length).toBe(1);
        });

        await waitFor(() => {
            const successCalls = onNotificar.mock.calls.filter(
                (c) => c[0] === 'success'
            );
            expect(successCalls.length).toBeGreaterThan(0);
        });
    });

    it('si el usuario cancela el Swal, NO se hace DELETE', async () => {
        swalMock.fire.mockResolvedValueOnce({ isConfirmed: false });

        const fetchMock = setupMocks(proyectoEnConstruccion);
        renderWithRouter(
            <VisorProyectoActivo proyectoId={5} onVolver={vi.fn()} onNotificar={vi.fn()} />
        );
        await waitForProject();

        fireEvent.click(screen.getAllByTitle(/desvincular/i)[0]);
        await waitFor(() => expect(swalMock.fire).toHaveBeenCalled());

        await new Promise((r) => setTimeout(r, 50));

        const deleteCalls = fetchMock.mock.calls.filter(
            ([, init]) => init?.method === 'DELETE'
        );
        expect(deleteCalls.length).toBe(0);
    });

    it('confirmacion menciona el monto que se restara y el numero de factura', async () => {
        swalMock.fire.mockResolvedValueOnce({ isConfirmed: false });
        const fetchMock = setupMocks(proyectoEnConstruccion);
        renderWithRouter(
            <VisorProyectoActivo proyectoId={5} onVolver={vi.fn()} onNotificar={vi.fn()} />
        );
        await waitForProject();

        fireEvent.click(screen.getAllByTitle(/desvincular/i)[0]);
        await waitFor(() => expect(swalMock.fire).toHaveBeenCalled());

        const swalArg = swalMock.fire.mock.calls[0][0];
        expect(swalArg.html).toContain('FAC-001');
        expect(swalArg.html).toContain('Cemento SA');
        const deleteCalls = fetchMock.mock.calls.filter(
            ([, init]) => init?.method === 'DELETE'
        );
        expect(deleteCalls.length).toBe(0);
    });
});
