import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

const permisosMock = vi.hoisted(() => ({ tienePermiso: () => true }));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => permisosMock,
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../Servicios/rrhhApi', () => ({
    default: {
        empleados: {
            listar: vi.fn(),
            crear: vi.fn().mockResolvedValue({ success: true }),
            actualizar: vi.fn().mockResolvedValue({ success: true }),
        },
    },
}));

import EmpleadosRrhh from './EmpleadosRrhh';
import rrhhApi from '../Servicios/rrhhApi';

const empleados = [
    { id: 1, rut: '11.111.111-1', nombres: 'Ana', apellido_paterno: 'Soto', afp: 'Modelo', tipo_salud: 'FONASA', estado: 'ACTIVO' },
    { id: 2, rut: '22.222.222-2', nombres: 'Beto', apellido_paterno: 'Lira', afp: 'Habitat', tipo_salud: 'ISAPRE', isapre_nombre: 'Colmena', estado: 'LICENCIA' },
];

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.empleados.listar.mockResolvedValue({ data: empleados });
});

afterEach(cleanup);

describe('EmpleadosRrhh', () => {
    it('lista los empleados que devuelve el servicio', async () => {
        render(<EmpleadosRrhh />);
        expect(await screen.findByText('Ana Soto')).toBeDefined();
        expect(screen.getByText(/Beto Lira/)).toBeDefined();
        // RUT se muestra enmascarado en la tabla (Ley 21.719): '11.111.111-1' → '11.***.**1-1'
        expect(screen.getByText('11.***.**1-1')).toBeDefined();
    });

    it('muestra el boton "Nuevo empleado" cuando hay permiso de crear', async () => {
        render(<EmpleadosRrhh />);
        await screen.findByText('Ana Soto');
        expect(screen.getByText('Nuevo empleado')).toBeDefined();
    });

    it('oculta el boton de crear cuando no hay permiso', async () => {
        permisosMock.tienePermiso = () => false;
        render(<EmpleadosRrhh />);
        await screen.findByText('Ana Soto');
        expect(screen.queryByText('Nuevo empleado')).toBeNull();
    });

    it('abre el modal de alta al pulsar "Nuevo empleado"', async () => {
        render(<EmpleadosRrhh />);
        await screen.findByText('Ana Soto');
        fireEvent.click(screen.getByText('Nuevo empleado'));
        await waitFor(() => {
            expect(screen.getByText('Identificación')).toBeDefined();
            expect(screen.getByText('Datos bancarios')).toBeDefined();
        });
    });

    it('muestra el boton de ayuda del modulo', async () => {
        render(<EmpleadosRrhh />);
        await screen.findByText('Ana Soto');
        expect(screen.getByTestId('ayuda-modulo-boton')).toBeDefined();
    });
});
