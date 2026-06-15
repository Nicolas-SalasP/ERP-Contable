import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import PanelDpo from './PanelDpo';

vi.mock('sweetalert2', () => ({ default: { fire: vi.fn().mockResolvedValue({}) } }));

vi.mock('./cumplimientoApi', () => ({
    default: {
        auditoria: { listar: vi.fn() },
        incidentes: { listar: vi.fn(), crear: vi.fn(), actualizar: vi.fn() },
    },
}));

import cumplimientoApi from './cumplimientoApi';

afterEach(cleanup);

describe('PanelDpo', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        cumplimientoApi.auditoria.listar.mockResolvedValue({
            data: { data: [
                { id: 1, created_at: '2026-06-14T10:00:00Z', nombre_usuario: 'Ana', operacion: 'ACTUALIZAR', auditable_type: 'App\\Domains\\Rrhh\\Models\\Empleado', detalle: 'Campos modificados: afp' },
            ] },
        });
        cumplimientoApi.incidentes.listar.mockResolvedValue({ data: { data: [] } });
        cumplimientoApi.incidentes.crear.mockResolvedValue({ data: { id: 99 } });
    });

    it('renderiza la pestaña de auditoría con filas', async () => {
        render(<PanelDpo />);
        await waitFor(() => {
            expect(screen.getByText('Ana')).toBeDefined();
            expect(screen.getByText(/Campos modificados: afp/)).toBeDefined();
        });
        expect(cumplimientoApi.auditoria.listar).toHaveBeenCalled();
    });

    it('cambia a la pestaña de incidentes y muestra estado vacío', async () => {
        render(<PanelDpo />);
        fireEvent.click(screen.getByRole('button', { name: /incidentes/i }));
        await waitFor(() => {
            expect(cumplimientoApi.incidentes.listar).toHaveBeenCalled();
            expect(screen.getByText(/Sin incidentes registrados/i)).toBeDefined();
        });
    });

    it('registra un incidente (POST) desde el formulario', async () => {
        render(<PanelDpo />);
        fireEvent.click(screen.getByRole('button', { name: /incidentes/i }));
        await waitFor(() => expect(cumplimientoApi.incidentes.listar).toHaveBeenCalled());

        fireEvent.click(screen.getByRole('button', { name: /registrar incidente/i }));

        fireEvent.change(screen.getByLabelText('Título'), { target: { value: 'Acceso no autorizado' } });
        fireEvent.change(screen.getByLabelText('Descripción'), { target: { value: 'Intento de acceso' } });
        fireEvent.change(screen.getByLabelText('Detectado'), { target: { value: '2026-06-14T09:00' } });

        fireEvent.click(screen.getByRole('button', { name: /guardar incidente/i }));

        await waitFor(() => {
            expect(cumplimientoApi.incidentes.crear).toHaveBeenCalled();
        });
        const payload = cumplimientoApi.incidentes.crear.mock.calls[0][0];
        expect(payload.titulo).toBe('Acceso no autorizado');
        expect(payload.severidad).toBe('MEDIA');
    });
});
