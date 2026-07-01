import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('react-router-dom', () => ({
    Link: ({ children, to }) => <a href={to}>{children}</a>,
    useParams: () => ({ id: '1' }),
}));

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

import SoporteTicketDetalle from './SoporteTicketDetalle';
import { api } from '../../../Configuracion/api';

afterEach(cleanup);
beforeEach(() => {
    vi.clearAllMocks();
});

const ticketMock = {
    id: 1,
    ticket_code: 'TKT-001',
    subject: 'Problema con facturas',
    status: 'abierto',
    priority: 'alta',
    category: 'ERP',
    assignee: { name: 'Soporte Tenri' },
    messages: [
        {
            id: 1,
            user_id: null,
            autor_nombre: 'Cliente',
            message: 'Tengo un problema con las facturas',
            created_at: '2025-01-15T10:00:00Z',
        },
        {
            id: 2,
            user_id: 5,
            user: { name: 'Agente Soporte' },
            message: 'Estamos revisando el problema',
            created_at: '2025-01-15T11:00:00Z',
        },
    ],
};

describe('SoporteTicketDetalle', () => {
    it('muestra "Cargando..." mientras se obtiene el ticket', () => {
        api.get.mockReturnValue(new Promise(() => {}));
        render(<SoporteTicketDetalle />);
        expect(screen.getByText('Cargando...')).toBeTruthy();
    });

    it('muestra error cuando falla la carga', async () => {
        api.get.mockRejectedValue(new Error('No encontrado'));
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByText(/No se pudo cargar el ticket/i)).toBeTruthy();
        });
    });

    it('muestra el asunto del ticket', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByText('Problema con facturas')).toBeTruthy();
        });
    });

    it('muestra el código del ticket', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByText(/TKT-001/)).toBeTruthy();
        });
    });

    it('muestra badge de estado', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByText('abierto')).toBeTruthy();
        });
    });

    it('muestra el nombre del agente asignado', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByText(/Soporte Tenri/)).toBeTruthy();
        });
    });

    it('muestra los mensajes del hilo', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByText('Tengo un problema con las facturas')).toBeTruthy();
            expect(screen.getByText('Estamos revisando el problema')).toBeTruthy();
        });
    });

    it('muestra badge "Soporte" en mensajes del agente', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByText('Soporte')).toBeTruthy();
        });
    });

    it('muestra formulario de respuesta cuando el ticket está abierto', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByPlaceholderText(/Escribe tu respuesta/i)).toBeTruthy();
            expect(screen.getByRole('button', { name: /Responder/i })).toBeTruthy();
        });
    });

    it('no muestra formulario de respuesta cuando el ticket está resuelto', async () => {
        api.get.mockResolvedValue({ data: { ...ticketMock, status: 'resuelto', messages: [] } });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.queryByPlaceholderText(/Escribe tu respuesta/i)).toBeNull();
            expect(screen.getByText(/Este ticket esta resuelto/i)).toBeTruthy();
        });
    });

    it('no muestra formulario cuando el ticket está cerrado', async () => {
        api.get.mockResolvedValue({ data: { ...ticketMock, status: 'cerrado', messages: [] } });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.queryByPlaceholderText(/Escribe tu respuesta/i)).toBeNull();
        });
    });

    it('enviar respuesta llama api.post con el mensaje', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        api.post.mockResolvedValue({ success: true });
        api.get.mockResolvedValueOnce({ data: ticketMock }).mockResolvedValueOnce({ data: ticketMock });

        render(<SoporteTicketDetalle />);
        await waitFor(() => screen.getByPlaceholderText(/Escribe tu respuesta/i));

        const textarea = screen.getByPlaceholderText(/Escribe tu respuesta/i);
        fireEvent.change(textarea, { target: { value: 'Mi respuesta al ticket' } });
        fireEvent.submit(textarea.closest('form'));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith(
                '/soporte/tickets/1/reply',
                { message: 'Mi respuesta al ticket' }
            );
        });
    });

    it('enlace "Volver a tickets" lleva a la lista', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => {
            expect(screen.getByText(/Volver a tickets/i)).toBeTruthy();
        });
    });

    it('botón Responder está deshabilitado cuando el textarea está vacío', async () => {
        api.get.mockResolvedValue({ data: ticketMock });
        render(<SoporteTicketDetalle />);
        await waitFor(() => screen.getByRole('button', { name: /Responder/i }));

        const btn = screen.getByRole('button', { name: /Responder/i });
        expect(btn.disabled).toBe(true);
    });
});
