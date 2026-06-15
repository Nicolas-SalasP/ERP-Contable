import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';

// usePermisos mock: por defecto el usuario tiene el permiso usuarios.gestionar
const permisosMock = vi.hoisted(() => ({ tienePermiso: () => true }));

vi.mock('../../Contextos/Permisos', () => ({
    usePermisos: () => permisosMock,
}));

vi.mock('sweetalert2', () => ({ default: { fire: vi.fn().mockResolvedValue({}) } }));

vi.mock('./cumplimientoApi', () => ({
    default: {
        auditoria: { listar: vi.fn() },
        incidentes: { listar: vi.fn(), crear: vi.fn(), actualizar: vi.fn() },
    },
}));

import PanelDpo from './PanelDpo';
import cumplimientoApi from './cumplimientoApi';

const FILA_AUDITORIA = {
    id: 1,
    created_at: '2026-06-14T10:00:00Z',
    nombre_usuario: 'Ana',
    operacion: 'ACTUALIZAR',
    auditable_type: 'App\\Domains\\Rrhh\\Models\\Empleado',
    auditable_id: 42,
    campos_afectados: 'afp',
};

const INCIDENTE = {
    id: 10,
    titulo: 'Acceso no autorizado a registros salariales',
    descripcion: 'Un usuario externo accedió a la carpeta de nóminas.',
    severidad: 'ALTA',
    estado: 'ABIERTO',
    detectado_at: '2026-06-13T08:00:00Z',
    alerta_temprana_at: null,
    reporte_csirt_at: null,
    notificacion_agencia_at: null,
    notificacion_afectados_at: null,
};

afterEach(cleanup);

describe('PanelDpo', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        permisosMock.tienePermiso = () => true;
        cumplimientoApi.auditoria.listar.mockResolvedValue({
            data: { data: [FILA_AUDITORIA], current_page: 1, last_page: 1, total: 1 },
        });
        cumplimientoApi.incidentes.listar.mockResolvedValue({ data: { data: [] } });
        cumplimientoApi.incidentes.crear.mockResolvedValue({ data: { id: 99 } });
        cumplimientoApi.incidentes.actualizar.mockResolvedValue({ data: { id: 10 } });
    });

    // ── Tab Auditoría ──────────────────────────────────────────────────────────

    it('renderiza la pestaña de auditoría por defecto con filas', async () => {
        render(<PanelDpo />);
        await waitFor(() => {
            expect(screen.getByText('Ana')).toBeDefined();
        });
        expect(cumplimientoApi.auditoria.listar).toHaveBeenCalled();
    });

    it('muestra el tipo de recurso abreviado en la tabla de auditoría', async () => {
        render(<PanelDpo />);
        await waitFor(() => expect(screen.getByText('Empleado')).toBeDefined());
    });

    it('muestra cabecera del panel DPO con referencia a Ley 21.719', async () => {
        render(<PanelDpo />);
        expect(screen.getByText(/Ley 21\.719/i)).toBeDefined();
    });

    // ── Tab Incidentes ─────────────────────────────────────────────────────────

    it('cambia a la pestaña de incidentes y muestra estado vacío', async () => {
        render(<PanelDpo />);
        fireEvent.click(screen.getByRole('tab', { name: /incidentes de seguridad/i }));
        await waitFor(() => {
            expect(cumplimientoApi.incidentes.listar).toHaveBeenCalled();
            expect(screen.getByText(/Sin incidentes registrados/i)).toBeDefined();
        });
    });

    it('en pestaña incidentes muestra incidentes cuando la API los devuelve', async () => {
        cumplimientoApi.incidentes.listar.mockResolvedValue({ data: { data: [INCIDENTE] } });
        render(<PanelDpo />);
        fireEvent.click(screen.getByRole('tab', { name: /incidentes de seguridad/i }));
        await waitFor(() => {
            expect(screen.getByText('Acceso no autorizado a registros salariales')).toBeDefined();
        });
    });

    it('registra un incidente (POST) desde el formulario', async () => {
        render(<PanelDpo />);
        fireEvent.click(screen.getByRole('tab', { name: /incidentes de seguridad/i }));
        await waitFor(() => expect(cumplimientoApi.incidentes.listar).toHaveBeenCalled());

        // Abrir formulario
        fireEvent.click(screen.getByRole('button', { name: /\+ Nuevo incidente/i }));

        // Rellenar campos requeridos
        fireEvent.change(screen.getByLabelText('Título'), { target: { value: 'Acceso no autorizado' } });
        fireEvent.change(screen.getByLabelText('Descripción'), { target: { value: 'Intento de acceso detectado' } });
        fireEvent.change(screen.getByLabelText('Detectado'), { target: { value: '2026-06-14T09:00' } });

        // Enviar
        fireEvent.click(screen.getByRole('button', { name: /guardar incidente/i }));

        await waitFor(() => {
            expect(cumplimientoApi.incidentes.crear).toHaveBeenCalled();
        });
        const payload = cumplimientoApi.incidentes.crear.mock.calls[0][0];
        expect(payload.titulo).toBe('Acceso no autorizado');
        expect(payload.severidad).toBe('MEDIA');
    });

    // ── Estado vacío de auditoría ──────────────────────────────────────────────

    it('muestra estado vacío cuando la API devuelve lista vacía en auditoría', async () => {
        cumplimientoApi.auditoria.listar.mockResolvedValue({
            data: { data: [], current_page: 1, last_page: 1, total: 0 },
        });
        render(<PanelDpo />);
        await waitFor(() => {
            expect(screen.getByText(/Sin registros/i)).toBeDefined();
        });
    });

    // ── Guard de permisos ──────────────────────────────────────────────────────

    it('muestra pantalla de acceso restringido cuando no hay permiso usuarios.gestionar', () => {
        permisosMock.tienePermiso = () => false;
        render(<PanelDpo />);
        expect(screen.getByText(/Acceso restringido/i)).toBeDefined();
        expect(cumplimientoApi.auditoria.listar).not.toHaveBeenCalled();
    });
});
