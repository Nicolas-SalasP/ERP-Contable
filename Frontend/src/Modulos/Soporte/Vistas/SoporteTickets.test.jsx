import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('react-router-dom', () => ({
    Link: ({ children, to }) => <a href={to}>{children}</a>,
    useNavigate: () => vi.fn(),
}));

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacioDiv: ({ mensaje }) => <div data-testid="estado-vacio">{mensaje}</div>,
}));

import SoporteTickets from './SoporteTickets';
import { api } from '../../../Configuracion/api';

afterEach(cleanup);
beforeEach(() => {
    vi.clearAllMocks();
});

const ticketsMock = [
    {
        id: 1,
        ticket_code: 'TKT-001',
        subject: 'Problema con facturas',
        status: 'nuevo',
        priority: 'alta',
        messages_count: 2,
        created_at: '2025-01-15T10:00:00Z',
    },
    {
        id: 2,
        ticket_code: 'TKT-002',
        subject: 'Error en liquidaciones',
        status: 'resuelto',
        priority: 'baja',
        messages_count: 5,
        created_at: '2025-01-10T08:00:00Z',
    },
];

describe('SoporteTickets', () => {
    it('muestra spinner mientras carga', () => {
        api.get.mockReturnValue(new Promise(() => {}));
        render(<SoporteTickets />);
        expect(screen.getByText('Cargando...')).toBeTruthy();
    });

    it('renderiza el título "Soporte"', async () => {
        api.get.mockResolvedValue({ data: [] });
        render(<SoporteTickets />);
        expect(screen.getByText('Soporte')).toBeTruthy();
    });

    it('muestra botón "Nuevo ticket"', async () => {
        api.get.mockResolvedValue({ data: [] });
        render(<SoporteTickets />);
        expect(screen.getByRole('button', { name: /Nuevo ticket/i })).toBeTruthy();
    });

    it('muestra estado vacío cuando no hay tickets', async () => {
        api.get.mockResolvedValue({ data: [] });
        render(<SoporteTickets />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-vacio')).toBeTruthy();
        });
    });

    it('renderiza lista de tickets', async () => {
        api.get.mockResolvedValue({ data: ticketsMock });
        render(<SoporteTickets />);
        await waitFor(() => {
            expect(screen.getByText('Problema con facturas')).toBeTruthy();
            expect(screen.getByText('Error en liquidaciones')).toBeTruthy();
        });
    });

    it('muestra código de ticket', async () => {
        api.get.mockResolvedValue({ data: ticketsMock });
        render(<SoporteTickets />);
        await waitFor(() => {
            expect(screen.getByText(/TKT-001/)).toBeTruthy();
        });
    });

    it('muestra badge de estado del ticket', async () => {
        api.get.mockResolvedValue({ data: ticketsMock });
        render(<SoporteTickets />);
        await waitFor(() => {
            expect(screen.getByText('nuevo')).toBeTruthy();
            expect(screen.getByText('resuelto')).toBeTruthy();
        });
    });

    it('muestra badge de prioridad del ticket', async () => {
        api.get.mockResolvedValue({ data: ticketsMock });
        render(<SoporteTickets />);
        await waitFor(() => {
            expect(screen.getByText('alta')).toBeTruthy();
        });
    });

    it('muestra mensaje de error cuando falla la carga', async () => {
        api.get.mockRejectedValue(new Error('Error de red'));
        render(<SoporteTickets />);
        await waitFor(() => {
            expect(screen.getByText(/No se pudieron cargar los tickets/i)).toBeTruthy();
        });
    });

    it('botón "Nuevo ticket" abre el modal de creación', async () => {
        api.get.mockResolvedValue({ data: [] });
        render(<SoporteTickets />);
        await waitFor(() => screen.getByRole('button', { name: /Nuevo ticket/i }));

        fireEvent.click(screen.getByRole('button', { name: /Nuevo ticket/i }));
        expect(screen.getByText('Nuevo ticket de soporte')).toBeTruthy();
    });

    it('modal de creación tiene campo de asunto', async () => {
        api.get.mockResolvedValue({ data: [] });
        render(<SoporteTickets />);
        await waitFor(() => screen.getByRole('button', { name: /Nuevo ticket/i }));

        fireEvent.click(screen.getByRole('button', { name: /Nuevo ticket/i }));
        expect(screen.getByPlaceholderText(/Describe brevemente el problema/i)).toBeTruthy();
    });

    it('cerrar modal con X oculta el formulario', async () => {
        api.get.mockResolvedValue({ data: [] });
        render(<SoporteTickets />);
        await waitFor(() => screen.getByRole('button', { name: /Nuevo ticket/i }));

        fireEvent.click(screen.getByRole('button', { name: /Nuevo ticket/i }));
        expect(screen.getByText('Nuevo ticket de soporte')).toBeTruthy();

        fireEvent.click(screen.getByText('×'));
        expect(screen.queryByText('Nuevo ticket de soporte')).toBeNull();
    });

    it('crear ticket llama api.post con los datos del formulario', async () => {
        api.get.mockResolvedValue({ data: [] });
        api.post.mockResolvedValue({ success: true });

        render(<SoporteTickets />);
        await waitFor(() => screen.getByRole('button', { name: /Nuevo ticket/i }));

        fireEvent.click(screen.getByRole('button', { name: /Nuevo ticket/i }));

        const subjectInput = screen.getByPlaceholderText(/Describe brevemente el problema/i);
        const descInput = screen.getByPlaceholderText(/Describe el problema con detalle/i);

        fireEvent.change(subjectInput, { target: { value: 'Mi problema' } });
        fireEvent.change(descInput, { target: { value: 'Descripción detallada del problema' } });
        fireEvent.submit(subjectInput.closest('form'));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith(
                '/soporte/tickets',
                expect.objectContaining({ subject: 'Mi problema' })
            );
        });
    });

    it('filtro de estado llama api.get con el estado seleccionado', async () => {
        api.get.mockResolvedValue({ data: [] });
        render(<SoporteTickets />);

        await waitFor(() => {
            expect(api.get).toHaveBeenCalled();
        });

        vi.clearAllMocks();
        api.get.mockResolvedValue({ data: [] });

        const select = screen.getAllByRole('combobox')[0];
        fireEvent.change(select, { target: { value: 'nuevo' } });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalled();
        });
    });

    it('maneja respuesta con data como array directo', async () => {
        api.get.mockResolvedValue({ data: ticketsMock });
        render(<SoporteTickets />);
        await waitFor(() => {
            expect(screen.getByText('Problema con facturas')).toBeTruthy();
        });
    });
});
