import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';

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
    PrimaryButton: ({ onClick, children, disabled, type }) => (
        <button onClick={onClick} disabled={disabled} type={type || 'button'}>{children}</button>
    ),
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
        eventosIntegracion: {
            listar: vi.fn(),
            obtener: vi.fn(),
            resumen: vi.fn(),
            procesar: vi.fn(),
            ignorar: vi.fn(),
            error: vi.fn(),
        },
    },
}));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: vi.fn(() => ({ tienePermiso: vi.fn().mockReturnValue(true) })),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true, value: 'motivo test' }) },
}));

import EventosIntegracionInventario from './EventosIntegracionInventario';
import inventarioApi from '../Servicios/inventarioApi';
import { usePermisos } from '../../../Contextos/Permisos';

afterEach(cleanup);

describe('EventosIntegracionInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(usePermisos).mockReturnValue({ tienePermiso: vi.fn().mockReturnValue(true) });
        vi.mocked(inventarioApi.eventosIntegracion.listar).mockResolvedValue({ data: [] });
        vi.mocked(inventarioApi.eventosIntegracion.resumen).mockResolvedValue({ data: null });
    });

    it('muestra alerta de sin permisos cuando el usuario no tiene acceso', () => {
        vi.mocked(usePermisos).mockReturnValue({ tienePermiso: vi.fn().mockReturnValue(false) });
        render(<EventosIntegracionInventario />);
        expect(screen.getByTestId('alert-rose')).toBeTruthy();
        expect(screen.getByText(/no tienes permisos/i)).toBeTruthy();
    });

    it('muestra el estado de carga para usuarios con permiso', () => {
        vi.mocked(inventarioApi.eventosIntegracion.listar).mockReturnValue(new Promise(() => {}));
        vi.mocked(inventarioApi.eventosIntegracion.resumen).mockReturnValue(new Promise(() => {}));
        render(<EventosIntegracionInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText(/cargando eventos internos de integración/i)).toBeTruthy();
    });

    it('renderiza el título "Eventos de Integración Interna" tras cargar', async () => {
        render(<EventosIntegracionInventario />);
        await waitFor(() => expect(screen.getByText('Eventos de Integración Interna')).toBeTruthy());
    });

    it('llama a eventosIntegracion.listar y resumen al montar', async () => {
        render(<EventosIntegracionInventario />);
        await waitFor(() => {
            expect(vi.mocked(inventarioApi.eventosIntegracion.listar)).toHaveBeenCalled();
            expect(vi.mocked(inventarioApi.eventosIntegracion.resumen)).toHaveBeenCalled();
        });
    });

    it('muestra el estado vacío cuando no hay eventos de integración', async () => {
        render(<EventosIntegracionInventario />);
        await waitFor(() => expect(screen.getByText('Sin eventos')).toBeTruthy());
    });

    it('muestra eventos en la tabla cuando existen registros', async () => {
        vi.mocked(inventarioApi.eventosIntegracion.listar).mockResolvedValue({
            data: [
                {
                    id: 1,
                    evento: 'INVENTARIO_MOVIMIENTO_CREADO',
                    estado: 'PENDIENTE',
                    prioridad: 'NORMAL',
                    entidad_tipo: 'Movimiento',
                    entidad_id: 1,
                    usuario_id: 1,
                    created_at: '2026-06-01',
                },
                {
                    id: 2,
                    evento: 'INVENTARIO_RESERVA_CREADA',
                    estado: 'PROCESADO',
                    prioridad: 'ALTA',
                    entidad_tipo: 'Reserva',
                    entidad_id: 2,
                    usuario_id: 1,
                    created_at: '2026-06-02',
                },
            ],
        });
        render(<EventosIntegracionInventario />);
        // El texto aparece tanto en las opciones del filtro como en las celdas de la tabla
        await waitFor(() => {
            expect(screen.getAllByText('INVENTARIO MOVIMIENTO CREADO').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('INVENTARIO RESERVA CREADA').length).toBeGreaterThanOrEqual(1);
        });
        // Confirmar que existe al menos una celda de tabla con estos valores
        const tds = document.querySelectorAll('td');
        const textosTd = Array.from(tds).map((td) => td.textContent);
        expect(textosTd.some((t) => t.includes('INVENTARIO MOVIMIENTO CREADO'))).toBe(true);
    });

    it('muestra el panel de filtros con selectores de Evento y Estado', async () => {
        render(<EventosIntegracionInventario />);
        await waitFor(() => expect(screen.getByText('Filtros')).toBeTruthy());
        expect(screen.getByText('Evento')).toBeTruthy();
        expect(screen.getByText('Estado')).toBeTruthy();
    });

    it('muestra las tarjetas de resumen con contadores', async () => {
        render(<EventosIntegracionInventario />);
        await waitFor(() => {
            const tarjetas = screen.getAllByTestId('stat-card');
            expect(tarjetas.length).toBeGreaterThanOrEqual(4);
            expect(screen.getByText('Eventos')).toBeTruthy();
            expect(screen.getByText('Pendientes')).toBeTruthy();
        });
    });
});
