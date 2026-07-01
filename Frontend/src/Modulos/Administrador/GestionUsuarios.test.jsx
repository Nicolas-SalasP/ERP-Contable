import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, cleanup, waitFor } from '@testing-library/react';

vi.mock('../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('sweetalert2', () => ({
    default: {
        fire: vi.fn().mockResolvedValue({ isConfirmed: false }),
    },
}));

import GestionUsuarios from './GestionUsuarios';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

afterEach(cleanup);

const usuariosMock = [
    {
        id: 1,
        nombre: 'Ana Torres',
        email: 'ana@empresa.cl',
        rol_id: 2,
        estado_suscripcion_id: 1,
        ultimo_acceso: '2026-06-01T10:00:00Z',
    },
    {
        id: 2,
        nombre: 'Carlos Muñoz',
        email: 'carlos@empresa.cl',
        rol_id: 3,
        estado_suscripcion_id: 2,
        ultimo_acceso: null,
    },
];

const rolesMock = [
    { id: 2, nombre: 'Contador' },
    { id: 3, nombre: 'Vendedor' },
];

const respuestaVacia = (url) => {
    if (url === '/usuarios') return Promise.resolve({ success: true, data: [] });
    if (url === '/usuarios/roles') return Promise.resolve({ success: true, data: rolesMock });
    return Promise.resolve({ success: true, data: [] });
};

const respuestaConUsuarios = (url) => {
    if (url === '/usuarios') return Promise.resolve({ success: true, data: usuariosMock });
    if (url === '/usuarios/roles') return Promise.resolve({ success: true, data: rolesMock });
    return Promise.resolve({ success: true, data: [] });
};

beforeEach(() => {
    vi.clearAllMocks();
    localStorage.setItem('erp_user', JSON.stringify({ id: 99 }));
});

afterEach(() => {
    localStorage.clear();
});

describe('GestionUsuarios', () => {
    it('renderiza el título "Gestión de Equipo"', () => {
        api.get.mockImplementation(respuestaVacia);
        render(<GestionUsuarios />);
        expect(screen.getByText('Gestión de Equipo')).toBeTruthy();
    });

    it('carga la lista de usuarios al montar (llama api.get /usuarios)', async () => {
        api.get.mockImplementation(respuestaVacia);
        render(<GestionUsuarios />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/usuarios');
        });
    });

    it('muestra mensaje cuando no hay usuarios registrados', async () => {
        api.get.mockImplementation(respuestaVacia);
        render(<GestionUsuarios />);
        await waitFor(() => {
            expect(screen.getByText('No hay usuarios registrados')).toBeTruthy();
        });
    });

    it('muestra filas con datos de usuarios', async () => {
        api.get.mockImplementation(respuestaConUsuarios);
        render(<GestionUsuarios />);
        await waitFor(() => {
            expect(screen.getByText('Ana Torres')).toBeTruthy();
            expect(screen.getByText('ana@empresa.cl')).toBeTruthy();
            expect(screen.getByText('Carlos Muñoz')).toBeTruthy();
        });
    });

    it('botón "Invitar Usuario" siempre visible', () => {
        api.get.mockImplementation(respuestaVacia);
        render(<GestionUsuarios />);
        expect(screen.getByText('Invitar Usuario')).toBeTruthy();
    });

    it('muestra Swal de error cuando falla la carga de usuarios', async () => {
        api.get.mockRejectedValue(new Error('Error de red'));
        render(<GestionUsuarios />);
        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith(
                expect.objectContaining({ icon: 'error', text: 'Error al cargar los usuarios.' })
            );
        });
    });
});
