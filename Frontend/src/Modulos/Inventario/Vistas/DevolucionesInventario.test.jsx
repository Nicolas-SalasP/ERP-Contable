import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    AlertBox: ({ children, tone }) => <div data-tone={tone}>{children}</div>,
    EmptyState: ({ title, description }) => <div>{title || description || 'Sin datos'}</div>,
    EstadoBadge: ({ value }) => <span data-testid="estado-badge">{value}</span>,
    ErrorNotice: ({ error }) => error ? <div data-testid="error-notice">{error?.message || String(error)}</div> : null,
    Field: ({ label, children }) => <div><label>{label}</label>{children}</div>,
    formatDate: (d) => d ? String(d) : '-',
    formatNumber: (v, d) => Number(v ?? 0).toFixed(d ?? 0),
    getProductoNombre: (item) => item?.producto?.nombre || item?.producto_nombre || `Producto #${item?.producto_id ?? '-'}`,
    LoadingState: ({ text }) => <div data-testid="loading-state">{text || 'Cargando...'}</div>,
    PageHeader: ({ title, description, actions }) => (
        <div>
            <h1>{title}</h1>
            <p>{description}</p>
            <div>{actions}</div>
        </div>
    ),
    Panel: ({ children, title, subtitle, actions }) => (
        <div>
            <h2>{title}</h2>
            {subtitle && <p>{subtitle}</p>}
            {actions && <div>{actions}</div>}
            {children}
        </div>
    ),
    PrimaryButton: ({ onClick, children, disabled, type }) => (
        <button type={type || 'button'} onClick={onClick} disabled={disabled}>{children}</button>
    ),
    SecondaryButton: ({ onClick, children, disabled, type }) => (
        <button type={type || 'button'} onClick={onClick} disabled={disabled}>{children}</button>
    ),
    StatCard: ({ title, value, icon, tone }) => (
        <div data-testid="stat-card" data-tone={tone}>
            <span>{title}</span>
            <span>{value}</span>
        </div>
    ),
    TableShell: ({ children }) => <table><tbody>{children}</tbody></table>,
    Td: ({ children, align }) => <td data-align={align}>{children}</td>,
    Th: ({ children, align }) => <th data-align={align}>{children}</th>,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        devoluciones: {
            listar: vi.fn(),
            crear: vi.fn(),
            confirmar: vi.fn(),
            cancelar: vi.fn(),
            reversable: vi.fn(),
        },
        despachos: {
            listar: vi.fn(),
        },
        ubicaciones: {
            listar: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: () => ({
        invalidarTodoInventario: vi.fn(),
    }),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import inventarioApi from '../Servicios/inventarioApi';
import Swal from 'sweetalert2';
import DevolucionesInventario from './DevolucionesInventario';

const respuestaVacia = { data: [] };

const devolucionPendiente = {
    id: 1,
    codigo: 'DEV-001',
    tipo: 'DEVOLUCION',
    estado: 'PENDIENTE',
    referencia: 'REF-DEV-001',
    motivo: 'devolucion_post_despacho',
    observacion: 'Producto dañado',
    despacho_orden_id: 10,
    despacho: { codigo: 'DESP-010' },
    detalles: [
        {
            id: 1,
            producto_id: 1,
            producto: { nombre: 'Producto Test' },
            cantidad_devolver: 5,
            cantidad_aceptada: 0,
            cantidad_rechazada: 0,
            estado: 'PENDIENTE',
            ubicacion_destino: null,
        },
    ],
    fecha_creacion: '2026-06-28',
    fecha_confirmacion: null,
    fecha_cancelacion: null,
};

const devolucionConfirmada = {
    id: 2,
    codigo: 'DEV-002',
    tipo: 'REVERSA_TOTAL',
    estado: 'CONFIRMADA',
    referencia: null,
    motivo: 'reversa_total_despacho',
    observacion: null,
    despacho_orden_id: 11,
    despacho: { codigo: 'DESP-011' },
    detalles: [],
    fecha_creacion: '2026-06-27',
    fecha_confirmacion: '2026-06-28',
    fecha_cancelacion: null,
};

afterEach(cleanup);

describe('DevolucionesInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        inventarioApi.devoluciones.listar.mockResolvedValue(respuestaVacia);
        inventarioApi.despachos.listar.mockResolvedValue(respuestaVacia);
        inventarioApi.ubicaciones.listar.mockResolvedValue(respuestaVacia);
        inventarioApi.devoluciones.crear.mockResolvedValue({ data: {} });
        inventarioApi.devoluciones.confirmar.mockResolvedValue({ data: {} });
        inventarioApi.devoluciones.cancelar.mockResolvedValue({ data: {} });
        inventarioApi.devoluciones.reversable.mockResolvedValue({ data: null });
    });

    it('muestra el estado de carga al montar', () => {
        render(<DevolucionesInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText('Cargando devoluciones y reversas post-despacho...')).toBeTruthy();
    });

    it('renderiza el título principal tras la carga', async () => {
        render(<DevolucionesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Devoluciones, reversas y diferencias post-despacho')).toBeTruthy();
        });
    });

    it('muestra las 4 tarjetas de resumen (Órdenes, Pendientes, Confirmadas, Con diferencias)', async () => {
        render(<DevolucionesInventario />);
        await waitFor(() => {
            const tarjetas = screen.getAllByTestId('stat-card');
            expect(tarjetas.length).toBe(4);
            expect(screen.getByText('Órdenes')).toBeTruthy();
            expect(screen.getByText('Pendientes')).toBeTruthy();
            expect(screen.getByText('Confirmadas')).toBeTruthy();
            expect(screen.getByText('Con diferencias')).toBeTruthy();
        });
    });

    it('muestra estado vacío cuando no hay devoluciones', async () => {
        render(<DevolucionesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Sin devoluciones/reversas')).toBeTruthy();
        });
    });

    it('muestra la lista de devoluciones cuando la API devuelve datos', async () => {
        inventarioApi.devoluciones.listar.mockResolvedValue({ data: [devolucionPendiente, devolucionConfirmada] });
        render(<DevolucionesInventario />);
        await waitFor(() => {
            expect(screen.getByText('DEV-001')).toBeTruthy();
            expect(screen.getByText('DEV-002')).toBeTruthy();
        });
    });

    it('muestra el formulario de nueva devolución al hacer clic en el botón', async () => {
        render(<DevolucionesInventario />);
        await waitFor(() => expect(screen.getByText('Nueva devolución/reversa')).toBeTruthy());

        fireEvent.click(screen.getByText('Nueva devolución/reversa'));

        await waitFor(() => {
            expect(screen.getByText('Crear devolución/reversa desde despacho confirmado')).toBeTruthy();
        });
    });

    it('cierra el formulario al hacer clic nuevamente en el botón de nueva devolución', async () => {
        render(<DevolucionesInventario />);
        await waitFor(() => expect(screen.getByText('Nueva devolución/reversa')).toBeTruthy());

        fireEvent.click(screen.getByText('Nueva devolución/reversa'));
        await waitFor(() => expect(screen.getByText('Crear devolución/reversa desde despacho confirmado')).toBeTruthy());

        fireEvent.click(screen.getByText('Nueva devolución/reversa'));
        await waitFor(() => {
            expect(screen.queryByText('Crear devolución/reversa desde despacho confirmado')).toBeNull();
        });
    });

    it('llama a devoluciones.confirmar al confirmar una orden PENDIENTE', async () => {
        inventarioApi.devoluciones.listar.mockResolvedValue({ data: [devolucionPendiente] });
        Swal.fire.mockResolvedValue({ isConfirmed: true });

        render(<DevolucionesInventario />);
        await waitFor(() => expect(screen.getByText('DEV-001')).toBeTruthy());

        const botonesConfirmar = screen.getAllByText('Confirmar');
        await act(async () => {
            fireEvent.click(botonesConfirmar[0]);
        });

        await waitFor(() => {
            expect(inventarioApi.devoluciones.confirmar).toHaveBeenCalledWith(1);
        });
    });

    it('llama a devoluciones.cancelar al cancelar una orden PENDIENTE', async () => {
        inventarioApi.devoluciones.listar.mockResolvedValue({ data: [devolucionPendiente] });
        Swal.fire.mockResolvedValue({ isConfirmed: true });

        render(<DevolucionesInventario />);
        await waitFor(() => expect(screen.getByText('DEV-001')).toBeTruthy());

        const botonesCancelar = screen.getAllByText('Cancelar');
        const habilitado = botonesCancelar.find((btn) => !btn.disabled);
        await act(async () => {
            fireEvent.click(habilitado);
        });

        await waitFor(() => {
            expect(inventarioApi.devoluciones.cancelar).toHaveBeenCalledWith(1, expect.any(Object));
        });
    });

    it('los botones Confirmar y Cancelar están deshabilitados para órdenes no PENDIENTES', async () => {
        inventarioApi.devoluciones.listar.mockResolvedValue({ data: [devolucionConfirmada] });
        render(<DevolucionesInventario />);
        await waitFor(() => expect(screen.getByText('DEV-002')).toBeTruthy());

        const botonesConfirmar = screen.getAllByText('Confirmar');
        const botonesCancelar = screen.getAllByText('Cancelar');

        expect(botonesConfirmar.every((btn) => btn.disabled)).toBe(true);
        expect(botonesCancelar.every((btn) => btn.disabled)).toBe(true);
    });

    it('filtra devoluciones por estado', async () => {
        inventarioApi.devoluciones.listar.mockResolvedValue({ data: [devolucionPendiente, devolucionConfirmada] });
        render(<DevolucionesInventario />);
        await waitFor(() => expect(screen.getByText('DEV-001')).toBeTruthy());

        const selectEstado = screen.getByDisplayValue('Todos los estados');
        fireEvent.change(selectEstado, { target: { value: 'PENDIENTE' } });

        await waitFor(() => {
            expect(screen.getByText('DEV-001')).toBeTruthy();
            expect(screen.queryByText('DEV-002')).toBeNull();
        });
    });

    it('filtra devoluciones por tipo', async () => {
        inventarioApi.devoluciones.listar.mockResolvedValue({ data: [devolucionPendiente, devolucionConfirmada] });
        render(<DevolucionesInventario />);
        await waitFor(() => expect(screen.getByText('DEV-001')).toBeTruthy());

        const selectTipo = screen.getByDisplayValue('Todos los tipos');
        fireEvent.change(selectTipo, { target: { value: 'REVERSA_TOTAL' } });

        await waitFor(() => {
            expect(screen.queryByText('DEV-001')).toBeNull();
            expect(screen.getByText('DEV-002')).toBeTruthy();
        });
    });

    it('muestra alerta informativa sobre Fase 16 y restricciones DTE/SII', async () => {
        render(<DevolucionesInventario />);
        await waitFor(() => {
            expect(screen.getByText(/Fase 16/)).toBeTruthy();
        });
    });
});
