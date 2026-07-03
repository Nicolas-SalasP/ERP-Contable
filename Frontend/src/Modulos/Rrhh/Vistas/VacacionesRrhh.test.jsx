import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

const permisosMock = vi.hoisted(() => ({ tienePermiso: () => true }));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => permisosMock,
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true, value: 'Motivo de prueba con largo suficiente' }) },
}));

vi.mock('../Servicios/rrhhApi', () => ({
    default: {
        vacaciones: {
            saldo: vi.fn(),
            listar: vi.fn(),
            solicitar: vi.fn().mockResolvedValue({ success: true }),
            aprobar: vi.fn().mockResolvedValue({ success: true }),
            rechazar: vi.fn().mockResolvedValue({ success: true }),
            anular: vi.fn().mockResolvedValue({ success: true }),
        },
        empleados: { listar: vi.fn() },
    },
}));

import VacacionesRrhh from './VacacionesRrhh';
import rrhhApi from '../Servicios/rrhhApi';

const solicitudes = [
    {
        id: 1, empleado_id: 1, fecha_desde: '2026-06-08', fecha_hasta: '2026-06-12',
        dias_habiles: 5, estado: 'PENDIENTE',
        empleado: { nombres: 'Ana', apellido_paterno: 'Soto' },
    },
];

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.vacaciones.listar.mockResolvedValue({ data: { data: solicitudes } });
    rrhhApi.vacaciones.saldo.mockResolvedValue({ data: { dias_devengados: 10, dias_tomados: 2, dias_disponibles: 8 } });
    rrhhApi.empleados.listar.mockResolvedValue({ data: [{ id: 1, rut: '11.111.111-1', nombres: 'Ana', apellido_paterno: 'Soto' }] });
});

afterEach(cleanup);

describe('VacacionesRrhh', () => {
    it('lista las solicitudes de vacaciones', async () => {
        render(<VacacionesRrhh />);
        expect(await screen.findByText(/Ana Soto/)).toBeDefined();
        expect(screen.getByText('PENDIENTE')).toBeDefined();
    });

    it('muestra el boton solicitar con permiso de procesar', async () => {
        render(<VacacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        expect(screen.getByText('Solicitar vacaciones')).toBeDefined();
    });

    it('oculta solicitar sin permiso de procesar', async () => {
        permisosMock.tienePermiso = () => false;
        render(<VacacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        expect(screen.queryByText('Solicitar vacaciones')).toBeNull();
    });

    it('abre el modal y muestra el saldo disponible al elegir empleado', async () => {
        render(<VacacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByText('Solicitar vacaciones'));

        const select = await screen.findByDisplayValue('Selecciona...');
        fireEvent.change(select, { target: { value: '1' } });

        await waitFor(() => {
            expect(rrhhApi.vacaciones.saldo).toHaveBeenCalledWith('1');
        });
        expect(await screen.findByText(/Saldo disponible/)).toBeDefined();
    });

    it('aprueba una solicitud pendiente', async () => {
        render(<VacacionesRrhh />);
        await screen.findByText(/Ana Soto/);

        fireEvent.click(screen.getByTitle('Aprobar'));

        await waitFor(() => {
            expect(rrhhApi.vacaciones.aprobar).toHaveBeenCalledWith(1);
        });
    });

    it('rechaza una solicitud pendiente con motivo', async () => {
        render(<VacacionesRrhh />);
        await screen.findByText(/Ana Soto/);

        fireEvent.click(screen.getByTitle('Rechazar'));

        await waitFor(() => {
            expect(rrhhApi.vacaciones.rechazar).toHaveBeenCalledWith(1, 'Motivo de prueba con largo suficiente');
        });
    });
});
