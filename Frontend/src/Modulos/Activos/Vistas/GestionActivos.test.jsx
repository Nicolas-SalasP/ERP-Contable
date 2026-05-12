import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import GestionActivos from './GestionActivos';
import { api } from '../../../Configuracion/api';

vi.mock('sweetalert2', () => ({
    default: {
        fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
    },
}));

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

const activosRegistradosMock = [
    {
        id: 1,
        codigo: 'AF-001',
        nombre: 'Notebook Lenovo T14',
        descripcion: 'Equipo para desarrollo',
        valor_adquisicion: 850000,
        valor_residual: 1,
        vida_util_meses: 60,
        depreciacion_acumulada: 0,
        estado: 'ACTIVO',
        cuenta: { nombre: 'Equipos Computacionales', categoria_sii: 'TI' },
    },
    {
        id: 2,
        codigo: 'AF-002',
        nombre: 'Impresora Epson',
        descripcion: 'Oficina principal',
        valor_adquisicion: 280000,
        valor_residual: 1,
        vida_util_meses: 36,
        depreciacion_acumulada: 50000,
        estado: 'ACTIVO',
        cuenta: { nombre: 'Equipos Oficina', categoria_sii: 'OF' },
    },
    {
        id: 3,
        codigo: 'AF-003',
        nombre: 'Notebook viejo',
        descripcion: 'Dado de baja en marzo',
        valor_adquisicion: 500000,
        valor_residual: 1,
        vida_util_meses: 60,
        depreciacion_acumulada: 500000,
        estado: 'DADO_DE_BAJA',
        cuenta: { nombre: 'Equipos Computacionales', categoria_sii: 'TI' },
    },
];

const activosPendientesMock = [];

// =====================================================================
// HELPERS
// =====================================================================

const setupMocks = (overrides = {}) => {
    return setupFetchRouter({
        'GET /activos/pendientes': () =>
            mockJsonResponse(200, { success: true, data: activosPendientesMock }),
        'GET /activos': () =>
            mockJsonResponse(200, { success: true, data: activosRegistradosMock }),
        ...overrides,
    });
};

const waitForLoad = async () => {
    await waitFor(() => {
        expect(screen.queryByText(/cargando/i)).toBeNull();
    });
};

// =====================================================================
// TESTS
// =====================================================================

describe('GestionActivos - render inicial', () => {
    it('muestra el titulo del modulo', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();
        expect(screen.getByText('Activos Fijos')).toBeDefined();
    });

    it('muestra el icono de ayuda (AyudaModulo)', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();
        expect(screen.getAllByTestId('ayuda-modulo-boton').length).toBeGreaterThan(0);
    });

    it('llama a los endpoints correctos al montar', async () => {
        const fetchMock = setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        const calls = fetchMock.mock.calls.map((c) => c[0]);
        expect(calls.some((u) => u.includes('/activos/pendientes'))).toBe(true);
        expect(calls.some((u) => u.endsWith('/activos'))).toBe(true);
    });
});

describe('GestionActivos - lista de registrados', () => {
    it('muestra los activos registrados al cambiar de tab', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        const tabRegistrados = screen.getByRole('button', { name: /registrad/i });
        fireEvent.click(tabRegistrados);

        await waitFor(() => {
            expect(screen.getByText('Notebook Lenovo T14')).toBeDefined();
            expect(screen.getByText('Impresora Epson')).toBeDefined();
        });
    });

    it('muestra el estado RETIRADO para activos dados de baja', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));

        await waitFor(() => {
            expect(screen.getByText('Notebook viejo')).toBeDefined();
            expect(screen.getByText('RETIRADO')).toBeDefined();
        });
    });

    it('NO muestra botones de editar/baja para activos retirados', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));

        await waitFor(() => {
            expect(screen.getByText('Notebook viejo')).toBeDefined();
        });

        const botonesBaja = screen.getAllByTitle('Dar de Baja el Activo');
        expect(botonesBaja.length).toBe(2);
    });
});

describe('GestionActivos - modal de edicion', () => {
    it('abre el modal de edicion al click en el lapiz', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));
        await waitFor(() => screen.getByText('Notebook Lenovo T14'));

        const botonesEditar = screen.getAllByTitle('Editar nombre/descripcion');
        fireEvent.click(botonesEditar[0]);

        await waitFor(() => {
            expect(screen.getByText('Editar Activo')).toBeDefined();
        });
    });

    it('precarga el nombre y descripcion actuales en el formulario', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));
        await waitFor(() => screen.getByText('Notebook Lenovo T14'));

        const botonesEditar = screen.getAllByTitle('Editar nombre/descripcion');
        fireEvent.click(botonesEditar[0]);

        await waitFor(() => screen.getByText('Editar Activo'));

        // Inputs precargados con valores del activo
        const inputNombre = screen.getByDisplayValue('Notebook Lenovo T14');
        const inputDesc = screen.getByDisplayValue('Equipo para desarrollo');
        expect(inputNombre).toBeDefined();
        expect(inputDesc).toBeDefined();
    });

    it('muestra advertencia sobre campos contables no editables', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));
        await waitFor(() => screen.getByText('Notebook Lenovo T14'));

        fireEvent.click(screen.getAllByTitle('Editar nombre/descripcion')[0]);

        await waitFor(() => screen.getByText(/solo podes editar nombre y descripcion/i));
        expect(screen.getByText(/movimientos calculados/i)).toBeDefined();
    });

    it('NO permite guardar con nombre vacio', async () => {
        const fetchMock = setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));
        await waitFor(() => screen.getByText('Notebook Lenovo T14'));

        fireEvent.click(screen.getAllByTitle('Editar nombre/descripcion')[0]);
        await waitFor(() => screen.getByText('Editar Activo'));

        const inputNombre = screen.getByDisplayValue('Notebook Lenovo T14');
        fireEvent.change(inputNombre, { target: { value: '   ' } });

        const botonGuardar = screen.getByRole('button', { name: /guardar cambios/i });
        fireEvent.click(botonGuardar);

        await waitFor(() => {
            const llamadasPut = fetchMock.mock.calls.filter(
                ([, init]) => init?.method === 'PUT'
            );
            expect(llamadasPut.length).toBe(0);
        });
    });

    it('guarda los cambios cuando el formulario es valido', async () => {
        const fetchMock = setupMocks({
            'PUT /activos/1': () =>
                mockJsonResponse(200, { success: true, data: { id: 1, nombre: 'Renombrado' } }),
        });
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));
        await waitFor(() => screen.getByText('Notebook Lenovo T14'));

        fireEvent.click(screen.getAllByTitle('Editar nombre/descripcion')[0]);
        await waitFor(() => screen.getByText('Editar Activo'));

        const inputNombre = screen.getByDisplayValue('Notebook Lenovo T14');
        fireEvent.change(inputNombre, { target: { value: 'Renombrado' } });

        const botonGuardar = screen.getByRole('button', { name: /guardar cambios/i });
        fireEvent.click(botonGuardar);

        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                ([url, init]) => init?.method === 'PUT' && url.includes('/activos/1')
            );
            expect(putCalls.length).toBe(1);
            const body = JSON.parse(putCalls[0][1].body);
            expect(body.nombre).toBe('Renombrado');
        });
    });

    it('el body del PUT NO incluye campos contables aunque esten en el state', async () => {
        const fetchMock = setupMocks({
            'PUT /activos/1': () => mockJsonResponse(200, { success: true }),
        });
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));
        await waitFor(() => screen.getByText('Notebook Lenovo T14'));

        fireEvent.click(screen.getAllByTitle('Editar nombre/descripcion')[0]);
        await waitFor(() => screen.getByText('Editar Activo'));

        fireEvent.click(screen.getByRole('button', { name: /guardar cambios/i }));

        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                ([url, init]) => init?.method === 'PUT' && url.includes('/activos/1')
            );
            expect(putCalls.length).toBe(1);
            const body = JSON.parse(putCalls[0][1].body);

            const keysEnviadas = Object.keys(body).sort();
            expect(keysEnviadas).toEqual(['descripcion', 'nombre']);

            expect(body.valor_adquisicion).toBeUndefined();
            expect(body.vida_util_meses).toBeUndefined();
            expect(body.valor_residual).toBeUndefined();
        });
    });
});

describe('GestionActivos - modal de baja', () => {
    it('abre el modal de baja al click en boton baja', async () => {
        setupMocks();
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));
        await waitFor(() => screen.getByText('Notebook Lenovo T14'));

        const botonesBaja = screen.getAllByTitle('Dar de Baja el Activo');
        fireEvent.click(botonesBaja[0]);

        await waitFor(() => {
            expect(screen.getByText(/motivo de la baja/i)).toBeDefined();
        });
    });

    it('envia el motivo de baja al backend', async () => {
        const fetchMock = setupMocks({
            'PUT /activos/1/baja': () =>
                mockJsonResponse(200, { success: true, message: 'Activo dado de baja' }),
        });
        renderWithRouter(<GestionActivos />);
        await waitForLoad();

        fireEvent.click(screen.getByRole('button', { name: /registrad/i }));
        await waitFor(() => screen.getByText('Notebook Lenovo T14'));

        fireEvent.click(screen.getAllByTitle('Dar de Baja el Activo')[0]);

        await waitFor(() => screen.getByText(/motivo de la baja/i));

        const inputMotivo = screen.getByPlaceholderText(/obsolescencia/i);
        fireEvent.change(inputMotivo, { target: { value: 'Equipo robado' } });

        const botonConfirmar = screen.getByRole('button', { name: /confirmar baja/i });
        fireEvent.click(botonConfirmar);

        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                ([url, init]) => init?.method === 'PUT' && url.includes('/activos/1/baja')
            );
            expect(putCalls.length).toBe(1);
            const body = JSON.parse(putCalls[0][1].body);
            expect(body.motivo).toBe('Equipo robado');
        });
    });
});

describe('GestionActivos - manejo de errores', () => {
    it('si el GET /activos falla, no rompe el render', async () => {
        setupFetchRouter({
            'GET /activos/pendientes': () =>
                mockJsonResponse(500, { success: false, message: 'Error server' }),
            'GET /activos': () =>
                mockJsonResponse(500, { success: false, message: 'Error server' }),
        });

        renderWithRouter(<GestionActivos />);

        await waitForLoad();
        expect(screen.getByText('Activos Fijos')).toBeDefined();
    });
});
