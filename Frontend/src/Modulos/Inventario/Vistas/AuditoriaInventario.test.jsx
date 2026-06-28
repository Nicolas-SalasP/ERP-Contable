import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    AlertBox: ({ children, tone }) => <div data-testid={`alert-${tone || 'default'}`}>{children}</div>,
    EmptyState: ({ title }) => <div>{title || 'Sin datos'}</div>,
    ErrorNotice: ({ error }) => (error ? <div data-testid="error-notice">Error</div> : null),
    EstadoBadge: ({ value }) => <span>{value}</span>,
    Field: ({ label, children }) => <div><label>{label}</label>{children}</div>,
    formatDate: (d) => d || '-',
    LoadingState: ({ text }) => <div data-testid="loading-state">{text || 'Cargando...'}</div>,
    PageHeader: ({ title, actions }) => <div><h1>{title}</h1>{actions}</div>,
    Panel: ({ children, title }) => <div><h2>{title}</h2>{children}</div>,
    SecondaryButton: ({ onClick, children, disabled, type }) => (
        <button onClick={onClick} disabled={disabled} type={type || 'button'}>{children}</button>
    ),
    StatCard: ({ title, value }) => <div data-testid="stat-card"><span>{title}</span><span>{value}</span></div>,
    TableShell: ({ children }) => <table>{children}</table>,
    Td: ({ children }) => <td>{children}</td>,
    Th: ({ children }) => <th>{children}</th>,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        auditoria: {
            listar: vi.fn(),
            obtener: vi.fn(),
            resumen: vi.fn(),
        },
    },
}));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: vi.fn(() => ({ tienePermiso: vi.fn().mockReturnValue(true) })),
}));

import AuditoriaInventario from './AuditoriaInventario';
import inventarioApi from '../Servicios/inventarioApi';
import { usePermisos } from '../../../Contextos/Permisos';

afterEach(cleanup);

describe('AuditoriaInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(usePermisos).mockReturnValue({ tienePermiso: vi.fn().mockReturnValue(true) });
        vi.mocked(inventarioApi.auditoria.listar).mockResolvedValue({ data: [] });
        vi.mocked(inventarioApi.auditoria.resumen).mockResolvedValue({ data: null });
    });

    it('muestra alerta de sin permisos cuando el usuario no tiene acceso', () => {
        vi.mocked(usePermisos).mockReturnValue({ tienePermiso: vi.fn().mockReturnValue(false) });
        render(<AuditoriaInventario />);
        expect(screen.getByTestId('alert-rose')).toBeTruthy();
        expect(screen.getByText(/no tienes permisos/i)).toBeTruthy();
    });

    it('muestra el estado de carga para usuarios con permiso', () => {
        vi.mocked(inventarioApi.auditoria.listar).mockReturnValue(new Promise(() => {}));
        vi.mocked(inventarioApi.auditoria.resumen).mockReturnValue(new Promise(() => {}));
        render(<AuditoriaInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText(/cargando bitácora operativa/i)).toBeTruthy();
    });

    it('renderiza el título "Auditoría de Inventario" tras cargar', async () => {
        render(<AuditoriaInventario />);
        await waitFor(() => expect(screen.getByText('Auditoría de Inventario')).toBeTruthy());
    });

    it('llama a auditoria.listar y auditoria.resumen al montar', async () => {
        render(<AuditoriaInventario />);
        await waitFor(() => {
            expect(vi.mocked(inventarioApi.auditoria.listar)).toHaveBeenCalled();
            expect(vi.mocked(inventarioApi.auditoria.resumen)).toHaveBeenCalled();
        });
    });

    it('muestra el estado vacío cuando no hay eventos de auditoría', async () => {
        render(<AuditoriaInventario />);
        await waitFor(() => expect(screen.getByText('Sin eventos')).toBeTruthy());
    });

    it('muestra eventos en la tabla cuando existen registros', async () => {
        vi.mocked(inventarioApi.auditoria.listar).mockResolvedValue({
            data: [
                {
                    id: 1,
                    accion: 'MOVIMIENTO_CREADO',
                    severidad: 'INFO',
                    entidad_tipo: 'Movimiento',
                    entidad_id: 1,
                    usuario_id: 1,
                    created_at: '2026-06-01',
                },
                {
                    id: 2,
                    accion: 'AJUSTE_CRITICO_CREADO',
                    severidad: 'CRITICAL',
                    entidad_tipo: 'Ajuste',
                    entidad_id: 2,
                    usuario_id: 1,
                    created_at: '2026-06-02',
                },
            ],
        });
        render(<AuditoriaInventario />);
        // El texto aparece tanto en las opciones del filtro como en las celdas de la tabla
        await waitFor(() => {
            expect(screen.getAllByText('MOVIMIENTO CREADO').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('AJUSTE CRITICO CREADO').length).toBeGreaterThanOrEqual(1);
        });
        // Confirmar que existe al menos una celda de tabla con estos valores
        const tds = document.querySelectorAll('td');
        const textosTd = Array.from(tds).map((td) => td.textContent);
        expect(textosTd.some((t) => t.includes('MOVIMIENTO CREADO'))).toBe(true);
    });

    it('muestra el panel de filtros con campos Acción y Severidad', async () => {
        render(<AuditoriaInventario />);
        await waitFor(() => expect(screen.getByText('Filtros de auditoría')).toBeTruthy());
        expect(screen.getByText('Acción')).toBeTruthy();
        expect(screen.getByText('Severidad')).toBeTruthy();
    });

    it('hace clic en "Ver" de un evento y llama a auditoria.obtener', async () => {
        vi.mocked(inventarioApi.auditoria.listar).mockResolvedValue({
            data: [
                {
                    id: 5,
                    accion: 'RESERVA_CREADA',
                    severidad: 'INFO',
                    entidad_tipo: 'Reserva',
                    entidad_id: 5,
                    usuario_id: 1,
                    created_at: '2026-06-01',
                },
            ],
        });
        vi.mocked(inventarioApi.auditoria.obtener).mockResolvedValue({
            data: {
                id: 5,
                accion: 'RESERVA_CREADA',
                severidad: 'INFO',
                descripcion: 'Reserva creada',
                metadata_json: null,
                antes_json: null,
                despues_json: null,
            },
        });

        render(<AuditoriaInventario />);
        await waitFor(() => screen.getByText('Ver'));
        fireEvent.click(screen.getByText('Ver'));
        await waitFor(() =>
            expect(vi.mocked(inventarioApi.auditoria.obtener)).toHaveBeenCalledWith(5),
        );
    });
});
