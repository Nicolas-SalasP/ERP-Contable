import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import GestionProyectosActivos from './GestionProyectosActivos';
import { api } from '../../../Configuracion/api';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
}));
vi.mock('sweetalert2', () => ({
    default: swalMock,
}));

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

vi.mock('./VisorProyectoActivo', () => ({
    default: () => <div data-testid="visor-proyecto-stub">Visor Stub</div>,
}));

// =====================================================================
// FIXTURES
// =====================================================================

const proyectosMock = [
    {
        id_proyecto: 1,
        nombre: 'Bodega Sur 2026',
        estado: 'EN_CONSTRUCCION',
        valor_total_original: 0,
        vida_util_meses: 60,
    },
    {
        id_proyecto: 2,
        nombre: 'Galpon Centro',
        estado: 'EN_CONSTRUCCION',
        valor_total_original: 1500000,
        vida_util_meses: 120,
    },
    {
        id_proyecto: 3,
        nombre: 'Oficina Norte',
        estado: 'ACTIVADO',
        valor_total_original: 8500000,
        vida_util_meses: 60,
    },
];

const parametrosMock = {
    success: true,
    data: { cuentas_activo: [{ id: 1, codigo: '12101', nombre: 'Equipos' }] },
};

const setupMocks = (overrides = {}) => {
    return setupFetchRouter({
        'GET /activos/proyectos': () =>
            mockJsonResponse(200, { success: true, data: proyectosMock }),
        'GET /activos/parametros': () =>
            mockJsonResponse(200, parametrosMock),
        ...overrides,
    });
};

const waitForCards = async () =>
    waitFor(() => expect(screen.getByText('Bodega Sur 2026')).toBeDefined());

// =====================================================================
// TESTS
// =====================================================================

describe('GestionProyectosActivos - listado', () => {
    it('muestra los proyectos retornados por el backend', async () => {
        setupMocks();
        renderWithRouter(<GestionProyectosActivos onNotificar={vi.fn()} />);

        await waitForCards();
        expect(screen.getByText('Galpon Centro')).toBeDefined();
        expect(screen.getByText('Oficina Norte')).toBeDefined();
    });

    it('muestra el badge "En Construcción" en proyectos EN_CONSTRUCCION', async () => {
        setupMocks();
        renderWithRouter(<GestionProyectosActivos onNotificar={vi.fn()} />);

        await waitForCards();
        const badges = screen.getAllByText(/en construcción/i);
        expect(badges.length).toBeGreaterThanOrEqual(2);
    });
});

describe('GestionProyectosActivos - eliminar proyecto', () => {
    it('NO muestra boton eliminar en proyectos ACTIVADOS', async () => {
        setupMocks();
        renderWithRouter(<GestionProyectosActivos onNotificar={vi.fn()} />);

        await waitForCards();

        const botonesEliminar = screen.getAllByTitle(/eliminar proyecto/i);
        expect(botonesEliminar.length).toBe(2);
    });

    it('al confirmar, llama DELETE /activos/proyectos/{id}', async () => {
        const fetchMock = setupMocks({
            'DELETE /activos/proyectos/1': () =>
                mockJsonResponse(200, { success: true }),
        });

        const onNotificar = vi.fn();
        renderWithRouter(<GestionProyectosActivos onNotificar={onNotificar} />);

        await waitForCards();

        const botonesEliminar = screen.getAllByTitle(/eliminar proyecto/i);
        fireEvent.click(botonesEliminar[0]);

        await waitFor(() => expect(swalMock.fire).toHaveBeenCalled());

        await waitFor(() => {
            const deleteCalls = fetchMock.mock.calls.filter(
                ([url, init]) => init?.method === 'DELETE' && url.includes('/activos/proyectos/1')
            );
            expect(deleteCalls.length).toBe(1);
        });
    });

    it('si el usuario cancela el Swal, NO se hace DELETE', async () => {
        swalMock.fire.mockResolvedValueOnce({ isConfirmed: false });

        const fetchMock = setupMocks({
            'DELETE /activos/proyectos/1': () =>
                mockJsonResponse(200, { success: true }),
        });

        renderWithRouter(<GestionProyectosActivos onNotificar={vi.fn()} />);

        await waitForCards();

        fireEvent.click(screen.getAllByTitle(/eliminar proyecto/i)[0]);
        await waitFor(() => expect(swalMock.fire).toHaveBeenCalled());

        await new Promise((r) => setTimeout(r, 50));

        const deleteCalls = fetchMock.mock.calls.filter(
            ([, init]) => init?.method === 'DELETE'
        );
        expect(deleteCalls.length).toBe(0);
    });

    it('el Swal advierte cuando el proyecto tiene facturas vinculadas', async () => {
        swalMock.fire.mockResolvedValueOnce({ isConfirmed: false });
        const fetchMock = setupMocks();
        renderWithRouter(<GestionProyectosActivos onNotificar={vi.fn()} />);

        await waitForCards();

        const botonesEliminar = screen.getAllByTitle(/eliminar proyecto/i);
        fireEvent.click(botonesEliminar[1]);

        await waitFor(() => expect(swalMock.fire).toHaveBeenCalled());

        const swalArg = swalMock.fire.mock.calls[0][0];
        expect(swalArg.html).toContain('facturas vinculadas');
        const deleteCalls = fetchMock.mock.calls.filter(
            ([, init]) => init?.method === 'DELETE'
        );
        expect(deleteCalls.length).toBe(0);
    });

    it('si el backend rechaza con 400, muestra el mensaje en Swal de error', async () => {
        const fetchMock = setupMocks({
            'DELETE /activos/proyectos/2': () =>
                mockJsonResponse(400, {
                    success: false,
                    message: 'No se puede eliminar: el proyecto tiene 3 factura(s) vinculada(s).',
                }),
        });

        renderWithRouter(<GestionProyectosActivos onNotificar={vi.fn()} />);
        await waitForCards();

        fireEvent.click(screen.getAllByTitle(/eliminar proyecto/i)[1]);

        await waitFor(() => expect(swalMock.fire).toHaveBeenCalledTimes(1));

        await waitFor(() => {
            const deleteCalls = fetchMock.mock.calls.filter(
                ([url, init]) => init?.method === 'DELETE' && url.includes('/activos/proyectos/2')
            );
            expect(deleteCalls.length).toBe(1);
        });

        await waitFor(() => expect(swalMock.fire).toHaveBeenCalledTimes(2));

        const segundaLlamada = swalMock.fire.mock.calls[1];
        const arg = segundaLlamada[0];
        if (typeof arg === 'string') {
            expect(segundaLlamada[1]).toContain('factura(s) vinculada(s)');
        } else {
            expect(JSON.stringify(arg)).toContain('factura');
        }
    });
});

describe('GestionProyectosActivos - crear proyecto (smoke)', () => {
    it('abre el modal de creacion al click en "Nuevo Proyecto"', async () => {
        setupMocks();
        renderWithRouter(<GestionProyectosActivos onNotificar={vi.fn()} />);

        await waitForCards();

        fireEvent.click(screen.getByRole('button', { name: /nuevo proyecto/i }));

        await waitFor(() => {
            expect(screen.getByText('Crear Proyecto')).toBeDefined();
        });
    });
});
