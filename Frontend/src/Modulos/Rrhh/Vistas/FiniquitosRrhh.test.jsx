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
        finiquitos: {
            listar: vi.fn(),
            obtener: vi.fn(),
            calcular: vi.fn().mockResolvedValue({ success: true }),
            firmar: vi.fn().mockResolvedValue({ success: true }),
        },
        empleados: { listar: vi.fn() },
        contratos: { listarPorEmpleado: vi.fn() },
    },
}));

import FiniquitosRrhh from './FiniquitosRrhh';
import rrhhApi from '../Servicios/rrhhApi';

const finiquitos = [
    {
        id: 1, empleado_id: 1, causal: 'NECESIDADES_EMPRESA', fecha_termino: '2026-06-30',
        total_neto: 1500000, estado: 'BORRADOR',
        empleado: { nombres: 'Ana', apellido_paterno: 'Soto' },
    },
];

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.finiquitos.listar.mockResolvedValue({ data: { data: finiquitos } });
    rrhhApi.empleados.listar.mockResolvedValue({ data: [{ id: 1, rut: '11.111.111-1', nombres: 'Ana', apellido_paterno: 'Soto' }] });
    rrhhApi.contratos.listarPorEmpleado.mockResolvedValue({ data: [] });
});

afterEach(cleanup);

describe('FiniquitosRrhh', () => {
    it('lista los finiquitos calculados', async () => {
        render(<FiniquitosRrhh />);
        expect(await screen.findByText(/Ana Soto/)).toBeDefined();
        expect(screen.getByText(/NECESIDADES EMPRESA/i)).toBeDefined();
        expect(screen.getByText('BORRADOR')).toBeDefined();
    });

    it('muestra el boton calcular con permiso de procesar', async () => {
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        expect(screen.getByText('Calcular finiquito')).toBeDefined();
    });

    it('abre el modal de calculo con la causal legal', async () => {
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByText('Calcular finiquito'));
        await waitFor(() => {
            expect(screen.getByText('Causal de término *')).toBeDefined();
            expect(screen.getByText('Contrato vigente *')).toBeDefined();
        });
    });

    it('oculta calcular sin permiso de procesar', async () => {
        permisosMock.tienePermiso = () => false;
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        expect(screen.queryByText('Calcular finiquito')).toBeNull();
    });
});
