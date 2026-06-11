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
        liquidaciones: {
            listar: vi.fn(),
            obtener: vi.fn(),
            calcular: vi.fn().mockResolvedValue({ success: true }),
            emitir: vi.fn().mockResolvedValue({ success: true }),
            anular: vi.fn().mockResolvedValue({ success: true }),
        },
        empleados: { listar: vi.fn() },
    },
}));

import LiquidacionesRrhh from './LiquidacionesRrhh';
import rrhhApi from '../Servicios/rrhhApi';

const liquidaciones = [
    {
        id: 10, empleado_id: 1, anio: 2026, mes: 6, estado: 'BORRADOR',
        empleado: { nombres: 'Ana', apellido_paterno: 'Soto', rut: '11.111.111-1' },
        total_haberes_imponibles: 1000000, total_descuentos: 200000, liquido_a_pagar: 800000,
    },
];

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.liquidaciones.listar.mockResolvedValue({ data: { data: liquidaciones } });
    rrhhApi.empleados.listar.mockResolvedValue({ data: [] });
});

afterEach(cleanup);

describe('LiquidacionesRrhh', () => {
    it('lista las liquidaciones del periodo', async () => {
        render(<LiquidacionesRrhh />);
        expect(await screen.findByText(/Ana Soto/)).toBeDefined();
        expect(screen.getByText('Junio 2026')).toBeDefined();
        expect(screen.getByText('BORRADOR')).toBeDefined();
    });

    it('muestra el boton calcular con permiso de procesar', async () => {
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        expect(screen.getByText('Calcular liquidación')).toBeDefined();
    });

    it('abre el modal de calculo', async () => {
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByText('Calcular liquidación'));
        await waitFor(() => {
            expect(screen.getByText('Horas extra')).toBeDefined();
            expect(screen.getByText('APV voluntario')).toBeDefined();
        });
    });

    it('oculta acciones de procesar sin permiso', async () => {
        permisosMock.tienePermiso = () => false;
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        expect(screen.queryByText('Calcular liquidación')).toBeNull();
    });
});
