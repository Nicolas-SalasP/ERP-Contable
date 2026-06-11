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
        empleados: { listar: vi.fn() },
        contratos: {
            listarPorEmpleado: vi.fn(),
            crear: vi.fn().mockResolvedValue({ success: true }),
            terminar: vi.fn().mockResolvedValue({ success: true }),
        },
    },
}));

import ContratosRrhh from './ContratosRrhh';
import rrhhApi from '../Servicios/rrhhApi';

const empleados = [{ id: 1, rut: '11.111.111-1', nombres: 'Ana', apellido_paterno: 'Soto' }];
const contratos = [
    { id: 5, tipo: 'INDEFINIDO', cargo: 'Desarrolladora', fecha_inicio: '2024-01-01', sueldo_base: 900000, estado: 'VIGENTE' },
];

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.empleados.listar.mockResolvedValue({ data: empleados });
    rrhhApi.contratos.listarPorEmpleado.mockResolvedValue({ data: contratos });
});

afterEach(cleanup);

describe('ContratosRrhh', () => {
    it('pide seleccionar un empleado al inicio', async () => {
        render(<ContratosRrhh />);
        expect(await screen.findByText(/Selecciona un empleado para ver sus contratos/i)).toBeDefined();
    });

    it('carga los contratos del empleado seleccionado', async () => {
        render(<ContratosRrhh />);
        const select = await screen.findByRole('combobox');
        fireEvent.change(select, { target: { value: '1' } });

        expect(await screen.findByText('Desarrolladora')).toBeDefined();
        await waitFor(() => {
            expect(rrhhApi.contratos.listarPorEmpleado).toHaveBeenCalledWith('1');
        });
        expect(screen.getByText('VIGENTE')).toBeDefined();
    });

    it('muestra el boton de nuevo contrato tras elegir empleado (con permiso)', async () => {
        render(<ContratosRrhh />);
        const select = await screen.findByRole('combobox');
        fireEvent.change(select, { target: { value: '1' } });
        expect(await screen.findByText('Nuevo contrato')).toBeDefined();
    });

    it('no muestra acciones de creacion sin permiso', async () => {
        permisosMock.tienePermiso = () => false;
        render(<ContratosRrhh />);
        const select = await screen.findByRole('combobox');
        fireEvent.change(select, { target: { value: '1' } });
        await screen.findByText('Desarrolladora');
        expect(screen.queryByText('Nuevo contrato')).toBeNull();
    });

    it('muestra el boton de ayuda del modulo', async () => {
        render(<ContratosRrhh />);
        expect(await screen.findByTestId('ayuda-modulo-boton')).toBeDefined();
    });
});
