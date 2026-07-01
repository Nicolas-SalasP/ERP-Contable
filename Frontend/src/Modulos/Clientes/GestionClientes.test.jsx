import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../test-utils';
import GestionClientes from './GestionClientes';

const mockSwal = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
    showLoading: vi.fn(),
    close: vi.fn(),
}));
vi.mock('sweetalert2', () => ({ default: mockSwal }));

vi.mock('./Componentes/FormularioCliente', () => ({
    default: ({ clienteId, onGuardado }) => (
        <div data-testid="formulario-cliente" data-cliente-id={clienteId}>
            <button onClick={onGuardado}>Guardar mock</button>
        </div>
    ),
}));

vi.mock('./Componentes/HistorialCotizaciones', () => ({
    default: () => <div data-testid="historial-cotizaciones" />,
}));

const CLIENTES = [
    {
        id: 1,
        codigo_cliente: 'C001',
        razon_social: 'Empresa ABC SpA',
        rut: '76.111.111-1',
        contacto_nombre: 'Juan Pérez',
        contacto_email: 'juan@abc.cl',
        direccion: 'Av. Principal 100',
        estado: 'ACTIVO',
    },
    {
        id: 2,
        codigo_cliente: 'C002',
        razon_social: 'Empresa XYZ Ltda',
        rut: '76.222.222-2',
        contacto_nombre: 'María Soto',
        contacto_email: 'maria@xyz.cl',
        direccion: 'Calle Secundaria 200',
        estado: 'BLOQUEADO',
    },
];

beforeEach(() => {
    cleanTestEnv();
    mockSwal.fire.mockResolvedValue({ isConfirmed: true });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

function montar(clientes = CLIENTES) {
    setupFetchRouter({
        'GET /clientes': () => mockJsonResponse(200, { success: true, data: clientes }),
    });
    return renderWithRouter(<GestionClientes />);
}

describe('GestionClientes — render', () => {
    it('muestra el título del módulo', async () => {
        montar();
        await waitFor(() => expect(screen.getByText('Clientes')).toBeTruthy());
    });

    it('muestra el botón Nuevo Cliente', async () => {
        montar();
        await waitFor(() => expect(screen.getByText(/Nuevo Cliente/i)).toBeTruthy());
    });

    it('muestra el input de búsqueda', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByPlaceholderText(/Filtrar por RUT/i)).toBeTruthy()
        );
    });
});

describe('GestionClientes — lista', () => {
    it('muestra clientes cargados desde GET /clientes', async () => {
        montar();
        await waitFor(() => {
            expect(screen.getAllByText('Empresa ABC SpA').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Empresa XYZ Ltda').length).toBeGreaterThan(0);
        });
    });

    it('muestra badge ACTIVO para cliente activo', async () => {
        montar();
        await waitFor(() => {
            const badges = screen.getAllByText('ACTIVO');
            expect(badges.length).toBeGreaterThan(0);
        });
    });

    it('muestra badge BLOQUEADO para cliente bloqueado', async () => {
        montar();
        await waitFor(() => {
            const badges = screen.getAllByText('BLOQUEADO');
            expect(badges.length).toBeGreaterThan(0);
        });
    });

    it('muestra mensaje de vacío cuando no hay clientes', async () => {
        montar([]);
        await waitFor(() =>
            expect(screen.getByText(/No hay registros coincidentes/i)).toBeTruthy()
        );
    });
});

describe('GestionClientes — modal', () => {
    it('abre el formulario al hacer click en Gestionar', async () => {
        montar();
        await waitFor(() => screen.getAllByText('Gestionar')[0]);
        fireEvent.click(screen.getAllByText('Gestionar')[0]);
        await waitFor(() =>
            expect(screen.getAllByTestId('formulario-cliente').length).toBeGreaterThan(0)
        );
    });
});

describe('GestionClientes — acciones de estado', () => {
    it('llama DELETE al confirmar bloqueo de cliente ACTIVO', async () => {
        let deleteUrl = null;
        setupFetchRouter({
            'GET /clientes': () => mockJsonResponse(200, { success: true, data: CLIENTES }),
            'DELETE /clientes/1': (_, url) => {
                deleteUrl = url;
                return mockJsonResponse(200, { success: true });
            },
        });
        renderWithRouter(<GestionClientes />);

        await waitFor(() => screen.getAllByText('Empresa ABC SpA')[0]);

        const btnBloquear = screen.getAllByRole('button', { name: /bloquear cliente/i })[0];
        fireEvent.click(btnBloquear);

        await waitFor(() => expect(deleteUrl).not.toBeNull(), { timeout: 2000 });
    });

    it('llama PUT /activar al confirmar activación de cliente BLOQUEADO', async () => {
        let activarLlamado = false;
        setupFetchRouter({
            'GET /clientes': () => mockJsonResponse(200, { success: true, data: CLIENTES }),
            'PUT /clientes/2/activar': () => {
                activarLlamado = true;
                return mockJsonResponse(200, { success: true });
            },
        });
        renderWithRouter(<GestionClientes />);

        await waitFor(() => screen.getAllByText('Empresa XYZ Ltda')[0]);

        const btnActivar = screen.getAllByRole('button', { name: /activar cliente/i })[0];
        fireEvent.click(btnActivar);

        await waitFor(() => expect(activarLlamado).toBe(true), { timeout: 2000 });
    });

    it('no llama DELETE si el usuario cancela el Swal', async () => {
        mockSwal.fire.mockResolvedValue({ isConfirmed: false });
        let deleteLlamado = false;
        setupFetchRouter({
            'GET /clientes': () => mockJsonResponse(200, { success: true, data: CLIENTES }),
            'DELETE /clientes/1': () => {
                deleteLlamado = true;
                return mockJsonResponse(200, { success: true });
            },
        });
        renderWithRouter(<GestionClientes />);

        await waitFor(() => screen.getAllByText('Empresa ABC SpA')[0]);
        const btnBloquear = screen.getAllByRole('button', { name: /bloquear cliente/i })[0];
        fireEvent.click(btnBloquear);

        await waitFor(() => expect(mockSwal.fire).toHaveBeenCalled());
        expect(deleteLlamado).toBe(false);
    });
});
