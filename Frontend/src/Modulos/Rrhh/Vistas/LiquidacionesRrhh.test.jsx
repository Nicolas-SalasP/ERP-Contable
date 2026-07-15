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
import Swal from 'sweetalert2';

const liquidaciones = [
    {
        id: 10, empleado_id: 1, anio: 2026, mes: 6, estado: 'BORRADOR',
        empleado: { nombres: 'Ana', apellido_paterno: 'Soto', rut: '11.111.111-1' },
        total_haberes_imponibles: 1000000, total_descuentos: 200000, liquido_a_pagar: 800000,
    },
];

const liquidacionEmitida = [
    {
        id: 11, empleado_id: 2, anio: 2026, mes: 6, estado: 'EMITIDA',
        empleado: { nombres: 'Luis', apellido_paterno: 'Pardo', rut: '22.222.222-2' },
        total_haberes_imponibles: 1000000, total_descuentos: 200000, liquido_a_pagar: 800000,
    },
];

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    Swal.fire.mockReset().mockResolvedValue({ isConfirmed: true });
    rrhhApi.liquidaciones.listar.mockReset().mockResolvedValue({ data: { data: liquidaciones } });
    rrhhApi.liquidaciones.obtener.mockReset();
    rrhhApi.liquidaciones.calcular.mockReset().mockResolvedValue({ success: true });
    rrhhApi.liquidaciones.emitir.mockReset().mockResolvedValue({ success: true });
    rrhhApi.liquidaciones.anular.mockReset().mockResolvedValue({ success: true });
    rrhhApi.empleados.listar.mockReset().mockResolvedValue({ data: [] });
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

    it('muestra estado vacio cuando no hay liquidaciones', async () => {
        rrhhApi.liquidaciones.listar.mockResolvedValue({ data: { data: [] } });
        render(<LiquidacionesRrhh />);
        await waitFor(() => {
            expect(screen.getByText('Sin liquidaciones para mostrar.')).toBeDefined();
        });
    });

    it('si listar falla, deja de cargar sin romper', async () => {
        rrhhApi.liquidaciones.listar.mockRejectedValue(new Error('fallo de red'));
        render(<LiquidacionesRrhh />);
        await waitFor(() => {
            expect(screen.getByText('Sin liquidaciones para mostrar.')).toBeDefined();
        });
    });

    it('cambiar el filtro de mes recarga las liquidaciones con el nuevo periodo', async () => {
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        rrhhApi.liquidaciones.listar.mockClear();
        fireEvent.change(screen.getByLabelText('Mes'), { target: { value: '3' } });
        await waitFor(() => {
            expect(rrhhApi.liquidaciones.listar).toHaveBeenCalledWith(
                expect.objectContaining({ mes: 3 }),
                expect.objectContaining({ signal: expect.anything() }),
            );
        });
    });

    it('ver detalle abre el modal con los conceptos de la liquidacion', async () => {
        rrhhApi.liquidaciones.obtener.mockResolvedValue({
            data: {
                id: 10, empleado_id: 1, anio: 2026, mes: 6, estado: 'BORRADOR',
                empleado: { nombres: 'Ana', apellido_paterno: 'Soto' },
                detalles: [{ id: 1, nombre_concepto: 'Sueldo base', tipo: 'HABER_IMPONIBLE', monto: 1000000 }],
                total_haberes: 1000000, total_descuentos: 200000, liquido_a_pagar: 800000,
            },
        });
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Ver detalle'));
        await waitFor(() => {
            expect(screen.getByText('Detalle de liquidación')).toBeDefined();
            expect(screen.getByText('Sueldo base')).toBeDefined();
            expect(screen.getByText('Líquido a pagar')).toBeDefined();
        });
    });

    it('si ver detalle falla no rompe la vista', async () => {
        rrhhApi.liquidaciones.obtener.mockRejectedValue(new Error('no encontrada'));
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Ver detalle'));
        await waitFor(() => {
            expect(rrhhApi.liquidaciones.obtener).toHaveBeenCalledWith(10);
        });
        expect(screen.queryByText('Detalle de liquidación')).toBeNull();
    });

    it('emitir pide confirmacion y al confirmar llama a la API y recarga', async () => {
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Emitir'));
        await waitFor(() => {
            expect(rrhhApi.liquidaciones.emitir).toHaveBeenCalledWith(10);
        });
        expect(rrhhApi.liquidaciones.listar).toHaveBeenCalledTimes(2);
    });

    it('emitir no llama a la API si el usuario cancela la confirmacion', async () => {
        Swal.fire.mockResolvedValueOnce({ isConfirmed: false });
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Emitir'));
        await waitFor(() => expect(Swal.fire).toHaveBeenCalled());
        expect(rrhhApi.liquidaciones.emitir).not.toHaveBeenCalled();
    });

    it('si emitir falla en la API no rompe la vista', async () => {
        rrhhApi.liquidaciones.emitir.mockRejectedValueOnce(new Error('no se pudo emitir'));
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Emitir'));
        await waitFor(() => {
            expect(rrhhApi.liquidaciones.emitir).toHaveBeenCalledWith(10);
        });
    });

    it('anular pide confirmacion y al confirmar llama a la API y recarga', async () => {
        rrhhApi.liquidaciones.listar.mockResolvedValue({ data: { data: liquidacionEmitida } });
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Luis Pardo/);
        fireEvent.click(screen.getByTitle('Anular'));
        await waitFor(() => {
            expect(rrhhApi.liquidaciones.anular).toHaveBeenCalledWith(11);
        });
        expect(rrhhApi.liquidaciones.listar).toHaveBeenCalledTimes(2);
    });

    it('anular no llama a la API si el usuario cancela la confirmacion', async () => {
        rrhhApi.liquidaciones.listar.mockResolvedValue({ data: { data: liquidacionEmitida } });
        Swal.fire.mockResolvedValueOnce({ isConfirmed: false });
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Luis Pardo/);
        fireEvent.click(screen.getByTitle('Anular'));
        await waitFor(() => expect(Swal.fire).toHaveBeenCalled());
        expect(rrhhApi.liquidaciones.anular).not.toHaveBeenCalled();
    });

    it('si anular falla en la API no rompe la vista', async () => {
        rrhhApi.liquidaciones.listar.mockResolvedValue({ data: { data: liquidacionEmitida } });
        rrhhApi.liquidaciones.anular.mockRejectedValueOnce(new Error('no se pudo anular'));
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Luis Pardo/);
        fireEvent.click(screen.getByTitle('Anular'));
        await waitFor(() => {
            expect(rrhhApi.liquidaciones.anular).toHaveBeenCalledWith(11);
        });
    });

    it('calcular liquidacion envia el payload sin campos vacios y cierra el modal', async () => {
        rrhhApi.empleados.listar.mockResolvedValue({ data: [{ id: 1, rut: '11.111.111-1', nombres: 'Ana', apellido_paterno: 'Soto' }] });
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByText('Calcular liquidación'));
        await waitFor(() => screen.getByText('Horas extra'));
        fireEvent.change(screen.getByLabelText(/Empleado/), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText('Horas extra'), { target: { value: '5' } });
        fireEvent.click(screen.getByText('Calcular', { selector: 'button[type="submit"]' }));
        await waitFor(() => {
            expect(rrhhApi.liquidaciones.calcular).toHaveBeenCalled();
            const payload = rrhhApi.liquidaciones.calcular.mock.calls[0][0];
            expect(payload.empleado_id).toBe('1');
            expect(payload.horas_extra).toBe('5');
            expect(payload).not.toHaveProperty('remuneraciones_variables');
        });
        await waitFor(() => {
            expect(screen.queryByText('Horas extra')).toBeNull();
        });
    });

    it('si calcular falla en la API no rompe el flujo', async () => {
        rrhhApi.liquidaciones.calcular.mockRejectedValueOnce(new Error('error de calculo'));
        rrhhApi.empleados.listar.mockResolvedValue({ data: [{ id: 1, rut: '11.111.111-1', nombres: 'Ana', apellido_paterno: 'Soto' }] });
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByText('Calcular liquidación'));
        await waitFor(() => screen.getByText('Horas extra'));
        fireEvent.change(screen.getByLabelText(/Empleado/), { target: { value: '1' } });
        fireEvent.click(screen.getByText('Calcular', { selector: 'button[type="submit"]' }));
        await waitFor(() => {
            expect(rrhhApi.liquidaciones.calcular).toHaveBeenCalled();
        });
    });

    it('si listar empleados falla no rompe la vista', async () => {
        rrhhApi.empleados.listar.mockRejectedValue(new Error('fallo empleados'));
        render(<LiquidacionesRrhh />);
        await screen.findByText(/Ana Soto/);
        expect(screen.getByText('Calcular liquidación')).toBeDefined();
    });
});
