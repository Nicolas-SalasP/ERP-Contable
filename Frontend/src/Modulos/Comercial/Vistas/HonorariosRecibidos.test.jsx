import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../Servicios/comercialApi', () => ({
    honorarios: {
        listar: vi.fn(),
        registrar: vi.fn(),
        eliminar: vi.fn(),
    },
}));
vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));
vi.mock('../../../Componentes/Skeleton', () => ({
    TablaSkeleton: () => <tr><td>Cargando...</td></tr>,
}));
// EstadoVacio dentro de <tbody> debe renderizar en <tr><td>
vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacio: ({ mensaje }) => (
        <tr><td colSpan="9">{mensaje}</td></tr>
    ),
}));
vi.mock('../../../Componentes/ConfirmacionInline', () => ({
    BotonEliminar: ({ onConfirmar }) => (
        <button onClick={onConfirmar}>Eliminar</button>
    ),
}));

import { honorarios } from '../Servicios/comercialApi';
import HonorariosRecibidos from './HonorariosRecibidos';

afterEach(cleanup);

describe('HonorariosRecibidos (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        honorarios.listar.mockResolvedValue([]);
        honorarios.registrar.mockResolvedValue({ id: 99 });
        honorarios.eliminar.mockResolvedValue({});
    });

    it('renderiza el título Honorarios Recibidos', async () => {
        await act(async () => {
            render(<HonorariosRecibidos />);
        });
        expect(screen.getByText('Honorarios Recibidos')).toBeDefined();
    });

    it('muestra estado vacío cuando lista está vacía', async () => {
        honorarios.listar.mockResolvedValue([]);
        await act(async () => {
            render(<HonorariosRecibidos />);
        });
        await waitFor(() => {
            expect(screen.getByText('Sin boletas de honorarios.')).toBeDefined();
        });
    });

    it('muestra registros cuando listar retorna datos', async () => {
        honorarios.listar.mockResolvedValue([
            {
                id: 1,
                rut_prestador: '12.345.678-9',
                nombre_prestador: 'Juan Pérez',
                numero_boleta: 'B-001',
                monto_bruto: 100000,
                fecha: '2024-01-15',
                mes: 1,
                anio: 2024,
                monto_retencion: 13750,
                monto_liquido: 86250,
            },
        ]);
        await act(async () => {
            render(<HonorariosRecibidos />);
        });
        await waitFor(() => {
            expect(screen.getByText('Juan Pérez')).toBeDefined();
            expect(screen.getByText('12.345.678-9')).toBeDefined();
        });
    });

    it('el formulario tiene campos RUT, Nombre y N° Boleta', async () => {
        await act(async () => {
            render(<HonorariosRecibidos />);
        });
        expect(screen.getByPlaceholderText('12345678-5')).toBeDefined();
        expect(screen.getByPlaceholderText('Juan Pérez Silva')).toBeDefined();
        expect(screen.getByPlaceholderText('000123')).toBeDefined();
    });

    it('cálculo de retención en vivo al ingresar monto y fecha', async () => {
        const { container } = render(<HonorariosRecibidos />);
        await waitFor(() => expect(honorarios.listar).toHaveBeenCalled());

        // Único input[type=date] en el formulario
        const fechaInput = container.querySelector('input[type="date"]');
        const montoInput = screen.getByPlaceholderText('500000');

        await act(async () => {
            fireEvent.change(montoInput, { target: { value: '100000' } });
            fireEvent.change(fechaInput, { target: { value: '2024-01-15' } });
        });

        // Para 2024 la tasa es 13.75 %; 100000 * 13.75 / 100 = 13750
        await waitFor(() => {
            expect(screen.getByText(/Retención \(13\.75%\)/i)).toBeDefined();
        });
    });

    it('el submit registra un nuevo honorario', async () => {
        honorarios.registrar.mockResolvedValue({ id: 5 });
        const { container } = render(<HonorariosRecibidos />);
        await waitFor(() => expect(honorarios.listar).toHaveBeenCalled());

        const fechaInput = container.querySelector('input[type="date"]');

        await act(async () => {
            fireEvent.change(screen.getByPlaceholderText('12345678-5'), {
                target: { value: '12345678-5' },
            });
            fireEvent.change(screen.getByPlaceholderText('Juan Pérez Silva'), {
                target: { value: 'María González' },
            });
            fireEvent.change(screen.getByPlaceholderText('500000'), {
                target: { value: '200000' },
            });
            fireEvent.change(fechaInput, { target: { value: '2024-03-10' } });
        });

        await act(async () => {
            fireEvent.click(screen.getByText('Registrar'));
        });

        await waitFor(() => {
            expect(honorarios.registrar).toHaveBeenCalledWith(
                expect.objectContaining({
                    rut_prestador: '12345678-5',
                    nombre_prestador: 'María González',
                    monto_bruto: 200000,
                    fecha: '2024-03-10',
                }),
            );
        });
    });

    it('selección de mes en filtro', async () => {
        await act(async () => {
            render(<HonorariosRecibidos />);
        });
        // Primer combobox es el filtro de mes
        const selectores = screen.getAllByRole('combobox');
        expect(selectores.length).toBeGreaterThanOrEqual(2);
        expect(screen.getByText('Enero')).toBeDefined();
        expect(screen.getByText('Diciembre')).toBeDefined();
    });

    it('muestra el filtro de año', async () => {
        await act(async () => {
            render(<HonorariosRecibidos />);
        });
        const anioActual = new Date().getFullYear();
        expect(screen.getByDisplayValue(String(anioActual))).toBeDefined();
    });

    it('eliminar honorario llama a honorarios.eliminar', async () => {
        honorarios.listar.mockResolvedValue([
            {
                id: 7,
                rut_prestador: '98765432-1',
                nombre_prestador: 'Carlos Silva',
                numero_boleta: 'B-010',
                monto_bruto: 50000,
                fecha: '2024-02-20',
            },
        ]);
        await act(async () => {
            render(<HonorariosRecibidos />);
        });
        await waitFor(() => {
            expect(screen.getByText('Carlos Silva')).toBeDefined();
        });

        // "Eliminar" aparece también como encabezado de columna; se busca por rol de botón
        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: 'Eliminar' }));
        });

        await waitFor(() => {
            expect(honorarios.eliminar).toHaveBeenCalledWith(7);
        });
    });
});
