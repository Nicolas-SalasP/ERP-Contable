import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
    },
}));

vi.mock('sweetalert2', () => ({
    default: {
        fire: vi.fn().mockResolvedValue({ isConfirmed: true, value: 'Nuevo Rol' }),
    },
}));

import GestionRoles from './GestionRoles';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

afterEach(cleanup);
beforeEach(() => {
    vi.clearAllMocks();
});

const rolesMock = [
    {
        id: 1,
        nombre: 'Administrador',
        empresa_id: null, // rol de sistema
        permisos: ['ventas.ver', 'clientes.ver'],
    },
    {
        id: 2,
        nombre: 'Vendedor',
        empresa_id: 5, // rol de empresa
        permisos: ['ventas.ver'],
    },
];

describe('GestionRoles', () => {
    it('renderiza el título "Roles y Permisos"', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<GestionRoles />);
        expect(screen.getByText('Roles y Permisos')).toBeTruthy();
    });

    it('muestra botón "Crear Nuevo Rol"', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<GestionRoles />);
        await waitFor(() => {
            expect(screen.getByText(/Crear Nuevo Rol/i)).toBeTruthy();
        });
    });

    it('carga y muestra los roles de la API', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        render(<GestionRoles />);
        await waitFor(() => {
            expect(screen.getByText('Administrador')).toBeTruthy();
            expect(screen.getByText('Vendedor')).toBeTruthy();
        });
    });

    it('marca los roles de sistema con badge "Sistema"', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        render(<GestionRoles />);
        await waitFor(() => {
            expect(screen.getByText('Sistema')).toBeTruthy();
        });
    });

    it('seleccionar un rol muestra el panel de configuración', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        render(<GestionRoles />);
        await waitFor(() => screen.getByText('Vendedor'));

        fireEvent.click(screen.getByText('Vendedor'));
        expect(screen.getByText(/Configurando: Vendedor/i)).toBeTruthy();
    });

    it('rol de sistema muestra "solo lectura" y botón Duplicar', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        render(<GestionRoles />);
        await waitFor(() => screen.getByText('Administrador'));

        fireEvent.click(screen.getByText('Administrador'));
        await waitFor(() => {
            expect(screen.getByText(/solo lectura/i)).toBeTruthy();
            expect(screen.getByText(/Duplicar/i)).toBeTruthy();
        });
    });

    it('rol de empresa muestra botón "Guardar Cambios"', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        render(<GestionRoles />);
        await waitFor(() => screen.getByText('Vendedor'));

        fireEvent.click(screen.getByText('Vendedor'));
        await waitFor(() => {
            expect(screen.getByText(/Guardar Cambios/i)).toBeTruthy();
        });
    });

    it('toggle permiso funciona para rol de empresa', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        render(<GestionRoles />);
        await waitFor(() => screen.getByText('Vendedor'));

        fireEvent.click(screen.getByText('Vendedor'));
        await waitFor(() => screen.getByText(/Guardar Cambios/i));

        // El permiso ventas.crear está disponible, lo activamos
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        expect(checkboxes.length).toBeGreaterThan(0);
        // Hacemos toggle en el primer checkbox
        fireEvent.change(checkboxes[0], { target: { checked: true } });
        // No debería lanzar error
    });

    it('guardar cambios llama api.put con los permisos actualizados', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        api.put.mockResolvedValue({ success: true });
        render(<GestionRoles />);
        await waitFor(() => screen.getByText('Vendedor'));

        fireEvent.click(screen.getByText('Vendedor'));
        await waitFor(() => screen.getByText(/Guardar Cambios/i));

        fireEvent.click(screen.getByText(/Guardar Cambios/i));

        await waitFor(() => {
            expect(api.put).toHaveBeenCalledWith(
                '/usuarios/roles/2',
                expect.objectContaining({ nombre: 'Vendedor' })
            );
        });
    });

    it('guardar cambios muestra Swal de éxito', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        api.put.mockResolvedValue({ success: true });
        render(<GestionRoles />);
        await waitFor(() => screen.getByText('Vendedor'));

        fireEvent.click(screen.getByText('Vendedor'));
        await waitFor(() => screen.getByText(/Guardar Cambios/i));
        fireEvent.click(screen.getByText(/Guardar Cambios/i));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith(
                expect.objectContaining({ icon: 'success', title: 'Permisos actualizados' })
            );
        });
    });

    it('muestra mensaje de selección cuando no hay rol seleccionado y no hay roles', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<GestionRoles />);
        await waitFor(() => {
            expect(screen.getByText(/Selecciona un rol para editar sus permisos/i)).toBeTruthy();
        });
    });

    it('duplicar rol llama api.post con nombre copia', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        api.post.mockResolvedValue({ success: true, data: { id: 3 } });
        render(<GestionRoles />);
        await waitFor(() => screen.getByText('Administrador'));

        fireEvent.click(screen.getByText('Administrador'));
        await waitFor(() => screen.getByText(/Duplicar/i));
        fireEvent.click(screen.getByText(/Duplicar/i));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith(
                '/usuarios/roles',
                expect.objectContaining({ nombre: 'Administrador (copia)' })
            );
        });
    });

    it('primer rol se selecciona automáticamente al cargar', async () => {
        api.get.mockResolvedValue({ success: true, data: rolesMock });
        render(<GestionRoles />);
        await waitFor(() => {
            expect(screen.getByText(/Configurando: Administrador/i)).toBeTruthy();
        });
    });
});
