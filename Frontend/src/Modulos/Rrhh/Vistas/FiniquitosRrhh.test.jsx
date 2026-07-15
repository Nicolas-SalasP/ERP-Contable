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
import Swal from 'sweetalert2';

const finiquitos = [
    {
        id: 1, empleado_id: 1, causal: 'NECESIDADES_EMPRESA', fecha_termino: '2026-06-30',
        total_neto: 1500000, estado: 'BORRADOR',
        empleado: { nombres: 'Ana', apellido_paterno: 'Soto' },
    },
];

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    Swal.fire.mockReset().mockResolvedValue({ isConfirmed: true });
    rrhhApi.finiquitos.listar.mockReset().mockResolvedValue({ data: { data: finiquitos } });
    rrhhApi.finiquitos.obtener.mockReset();
    rrhhApi.finiquitos.calcular.mockReset().mockResolvedValue({ success: true });
    rrhhApi.finiquitos.firmar.mockReset().mockResolvedValue({ success: true });
    rrhhApi.empleados.listar.mockReset().mockResolvedValue({ data: [{ id: 1, rut: '11.111.111-1', nombres: 'Ana', apellido_paterno: 'Soto' }] });
    rrhhApi.contratos.listarPorEmpleado.mockReset().mockResolvedValue({ data: [] });
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

    it('muestra estado vacio cuando no hay finiquitos', async () => {
        rrhhApi.finiquitos.listar.mockResolvedValue({ data: { data: [] } });
        render(<FiniquitosRrhh />);
        await waitFor(() => {
            expect(screen.getByText('Sin finiquitos calculados.')).toBeDefined();
        });
    });

    it('si listar falla, deja de cargar sin romper', async () => {
        rrhhApi.finiquitos.listar.mockRejectedValue(new Error('fallo de red'));
        render(<FiniquitosRrhh />);
        await waitFor(() => {
            expect(screen.getByText('Sin finiquitos calculados.')).toBeDefined();
        });
    });

    it('al elegir empleado carga sus contratos vigentes en el select', async () => {
        rrhhApi.contratos.listarPorEmpleado.mockResolvedValue({
            data: [
                { id: 5, cargo: 'Vendedor', fecha_inicio: '2024-01-01', estado: 'VIGENTE' },
                { id: 6, cargo: 'Antiguo', fecha_inicio: '2020-01-01', estado: 'TERMINADO' },
            ],
        });
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByText('Calcular finiquito'));
        await waitFor(() => screen.getByText('Causal de término *'));
        fireEvent.change(screen.getByDisplayValue('Selecciona...'), { target: { value: '1' } });
        await waitFor(() => {
            expect(rrhhApi.contratos.listarPorEmpleado).toHaveBeenCalledWith('1', expect.objectContaining({ signal: expect.anything() }));
        });
        await waitFor(() => {
            expect(screen.getByText(/Vendedor — desde/)).toBeDefined();
        });
        expect(screen.queryByText(/Antiguo — desde/)).toBeNull();
    });

    it('ver detalle abre el modal con los datos del finiquito', async () => {
        rrhhApi.finiquitos.obtener.mockResolvedValue({
            data: {
                id: 1, empleado_id: 1, estado: 'BORRADOR',
                empleado: { nombres: 'Ana', apellido_paterno: 'Soto' },
                anios_calculo: 2, anios_servicio: 2, meses_fraccion: 0,
                remuneracion_base_calculo: 1000000, monto_indemnizacion_anos: 2000000,
                monto_aviso_previo: 0, dias_vacaciones_proporcionales: 5,
                monto_vacaciones_proporcionales: 100000, haberes_pendientes: 0,
                descuentos_pendientes: 0, total_neto: 2100000,
            },
        });
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Ver detalle'));
        await waitFor(() => {
            expect(screen.getByText('Detalle de finiquito')).toBeDefined();
            expect(screen.getByText('Total neto a pagar')).toBeDefined();
        });
    });

    it('si ver detalle falla no rompe la vista', async () => {
        rrhhApi.finiquitos.obtener.mockRejectedValue(new Error('no encontrado'));
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Ver detalle'));
        await waitFor(() => {
            expect(rrhhApi.finiquitos.obtener).toHaveBeenCalledWith(1);
        });
        expect(screen.queryByText('Detalle de finiquito')).toBeNull();
    });

    it('firmar pide confirmacion y al confirmar llama a la API y recarga', async () => {
        const Swal = (await import('sweetalert2')).default;
        Swal.fire.mockResolvedValueOnce({ isConfirmed: true }).mockResolvedValueOnce({});
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Firmar'));
        await waitFor(() => {
            expect(rrhhApi.finiquitos.firmar).toHaveBeenCalledWith(1);
        });
        expect(rrhhApi.finiquitos.listar).toHaveBeenCalledTimes(2);
    });

    it('firmar no llama a la API si el usuario cancela la confirmacion', async () => {
        const Swal = (await import('sweetalert2')).default;
        Swal.fire.mockResolvedValueOnce({ isConfirmed: false });
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Firmar'));
        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalled();
        });
        expect(rrhhApi.finiquitos.firmar).not.toHaveBeenCalled();
    });

    it('si firmar falla en la API no rompe la vista', async () => {
        const Swal = (await import('sweetalert2')).default;
        Swal.fire.mockResolvedValueOnce({ isConfirmed: true });
        rrhhApi.finiquitos.firmar.mockRejectedValueOnce(new Error('no se pudo firmar'));
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByTitle('Firmar'));
        await waitFor(() => {
            expect(rrhhApi.finiquitos.firmar).toHaveBeenCalledWith(1);
        });
    });

    it('calcular finiquito envia el payload sin empleado_id y cierra el modal', async () => {
        rrhhApi.contratos.listarPorEmpleado.mockResolvedValue({
            data: [{ id: 5, cargo: 'Vendedor', fecha_inicio: '2024-01-01', estado: 'VIGENTE' }],
        });
        const { container } = render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByText('Calcular finiquito'));
        await waitFor(() => screen.getByText('Causal de término *'));
        fireEvent.change(screen.getByDisplayValue('Selecciona...'), { target: { value: '1' } });
        await waitFor(() => expect(screen.getByText(/Vendedor — desde/)).toBeDefined());
        fireEvent.change(container.querySelectorAll('select')[1], { target: { value: '5' } });
        fireEvent.change(container.querySelector('input[type="date"]'), { target: { value: '2026-06-30' } });
        fireEvent.click(screen.getByText('Calcular', { selector: 'button[type="submit"]' }));
        await waitFor(() => {
            expect(rrhhApi.finiquitos.calcular).toHaveBeenCalled();
            const payload = rrhhApi.finiquitos.calcular.mock.calls[0][0];
            expect(payload.empleado_id).toBeUndefined();
            expect(payload.fecha_termino).toBe('2026-06-30');
        });
        await waitFor(() => {
            expect(screen.queryByText('Causal de término *')).toBeNull();
        });
    });

    it('si calcular falla en la API no rompe el flujo', async () => {
        rrhhApi.finiquitos.calcular.mockRejectedValueOnce(new Error('error de calculo'));
        rrhhApi.contratos.listarPorEmpleado.mockResolvedValue({
            data: [{ id: 5, cargo: 'Vendedor', fecha_inicio: '2024-01-01', estado: 'VIGENTE' }],
        });
        const { container } = render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        fireEvent.click(screen.getByText('Calcular finiquito'));
        await waitFor(() => screen.getByText('Causal de término *'));
        fireEvent.change(screen.getByDisplayValue('Selecciona...'), { target: { value: '1' } });
        await waitFor(() => expect(screen.getByText(/Vendedor — desde/)).toBeDefined());
        fireEvent.change(container.querySelectorAll('select')[1], { target: { value: '5' } });
        fireEvent.change(container.querySelector('input[type="date"]'), { target: { value: '2026-06-30' } });
        fireEvent.click(screen.getByText('Calcular', { selector: 'button[type="submit"]' }));
        await waitFor(() => {
            expect(rrhhApi.finiquitos.calcular).toHaveBeenCalled();
        });
    });

    it('si listar empleados falla no rompe la vista', async () => {
        rrhhApi.empleados.listar.mockRejectedValue(new Error('fallo empleados'));
        render(<FiniquitosRrhh />);
        await screen.findByText(/Ana Soto/);
        expect(screen.getByText('Calcular finiquito')).toBeDefined();
    });
});
