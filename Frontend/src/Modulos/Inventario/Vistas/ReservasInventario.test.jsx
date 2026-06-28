import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

// ── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

const contextMock = vi.hoisted(() => ({
    productos: [{ id: 1, nombre: 'Producto Test', sku: 'SKU-001' }],
    bodegas: [{ id: 1, nombre: 'Bodega Central' }],
    lotes: [],
    cargarProductosCache: vi.fn().mockResolvedValue([]),
    cargarBodegasCache: vi.fn().mockResolvedValue([]),
    cargarLotesCache: vi.fn().mockResolvedValue([]),
    invalidarProductos: vi.fn(),
    invalidarLotes: vi.fn(),
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: () => contextMock,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        reservas: {
            listar: vi.fn().mockResolvedValue({ data: [] }),
            crear: vi.fn().mockResolvedValue({ success: true, data: { id: 99 } }),
            cancelar: vi.fn().mockResolvedValue({ success: true }),
            liberar: vi.fn().mockResolvedValue({ success: true }),
            consumir: vi.fn().mockResolvedValue({ success: true }),
        },
        disponibilidad: {
            listar: vi.fn().mockResolvedValue({ data: [] }),
        },
    },
}));

vi.mock('../Componentes/InventarioUI', () => ({
    AlertBox: ({ children, tone }) => (
        <div data-testid="alert-box" data-tone={tone}>{children}</div>
    ),
    EmptyState: ({ title, description }) => (
        <div data-testid="empty-state">
            <p>{title}</p>
            {description && <p>{description}</p>}
        </div>
    ),
    ErrorNotice: ({ error }) =>
        error ? <div data-testid="error-notice">{String(error?.message ?? error)}</div> : null,
    EstadoBadge: ({ value }) => (
        <span data-testid="estado-badge">{String(value || '-').replace(/_/g, ' ')}</span>
    ),
    Field: ({ label, children }) => (
        <div>
            <label>{label}</label>
            {children}
        </div>
    ),
    formatDate: (d) => d || '-',
    formatNumber: (v) => String(v ?? 0),
    LoadingState: ({ text }) => <div data-testid="loading-state">{text || 'Cargando...'}</div>,
    PageHeader: ({ title, description, actions }) => (
        <div>
            <h1>{title}</h1>
            {description && <p>{description}</p>}
            {actions && <div data-testid="page-actions">{actions}</div>}
        </div>
    ),
    Panel: ({ children, title, subtitle, actions }) => (
        <div data-testid="panel">
            {title && <h2>{title}</h2>}
            {subtitle && <p>{subtitle}</p>}
            {actions && <div data-testid="panel-actions">{actions}</div>}
            {children}
        </div>
    ),
    PrimaryButton: ({ onClick, children, disabled, type }) => (
        <button type={type || 'button'} onClick={onClick} disabled={disabled}>
            {children}
        </button>
    ),
    SecondaryButton: ({ onClick, children, type }) => (
        <button type={type || 'button'} onClick={onClick}>
            {children}
        </button>
    ),
    TableShell: ({ children }) => <table>{children}</table>,
    Td: ({ children, align }) => <td style={{ textAlign: align }}>{children}</td>,
    Th: ({ children, align }) => <th style={{ textAlign: align }}>{children}</th>,
}));

// ── Imports bajo test ──────────────────────────────────────────────────────

import inventarioApi from '../Servicios/inventarioApi';
import Swal from 'sweetalert2';
import ReservasInventario from './ReservasInventario';

// ── Fixtures ───────────────────────────────────────────────────────────────

const RESERVA_ACTIVA = {
    id: 1,
    codigo_reserva: 'RES-2026-001',
    estado: 'ACTIVA',
    referencia: 'PED-001',
    motivo: 'reserva_comercial',
    observacion: 'Para pedido cliente',
    fecha_reserva: '2026-06-01T00:00:00Z',
    fecha_expiracion: null,
};

const RESERVA_CONSUMIDA = {
    id: 2,
    codigo_reserva: 'RES-2026-002',
    estado: 'CONSUMIDA',
    referencia: 'PED-002',
    motivo: 'reserva_comercial',
    observacion: null,
    fecha_reserva: '2026-05-15T00:00:00Z',
    fecha_expiracion: null,
};

// ── Helpers ────────────────────────────────────────────────────────────────

const esperarCarga = () =>
    waitFor(() => expect(screen.queryByTestId('loading-state')).toBeNull());

afterEach(cleanup);

// ── Tests ──────────────────────────────────────────────────────────────────

describe('ReservasInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        inventarioApi.reservas.listar.mockResolvedValue({ data: [] });
        inventarioApi.disponibilidad.listar.mockResolvedValue({ data: [] });
        inventarioApi.reservas.crear.mockResolvedValue({ success: true, data: { id: 99 } });
        inventarioApi.reservas.cancelar.mockResolvedValue({ success: true });
        inventarioApi.reservas.liberar.mockResolvedValue({ success: true });
        inventarioApi.reservas.consumir.mockResolvedValue({ success: true });
    });

    it('muestra el estado de carga inicial antes de que las APIs respondan', () => {
        inventarioApi.reservas.listar.mockImplementation(() => new Promise(() => {}));
        render(<ReservasInventario />);
        expect(screen.getByTestId('loading-state')).toBeDefined();
        expect(screen.getByText(/Cargando reservas/i)).toBeDefined();
    });

    it('renderiza el título "Reservas y Disponibilidad" tras cargar', async () => {
        render(<ReservasInventario />);
        await esperarCarga();
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Reservas y Disponibilidad',
        );
    });

    it('muestra estado vacío cuando no hay reservas registradas', async () => {
        inventarioApi.reservas.listar.mockResolvedValue({ data: [] });
        render(<ReservasInventario />);
        await esperarCarga();
        expect(screen.getByTestId('empty-state')).toBeDefined();
        expect(screen.getByText('Sin reservas')).toBeDefined();
    });

    it('renderiza filas de reservas cuando la API retorna datos', async () => {
        inventarioApi.reservas.listar.mockResolvedValue({
            data: [RESERVA_ACTIVA, RESERVA_CONSUMIDA],
        });
        render(<ReservasInventario />);
        await esperarCarga();

        expect(screen.getByText('RES-2026-001')).toBeDefined();
        expect(screen.getByText('RES-2026-002')).toBeDefined();
        expect(screen.queryByTestId('empty-state')).toBeNull();
    });

    it('alterna la visibilidad del formulario con el botón "Nueva reserva"', async () => {
        render(<ReservasInventario />);
        await esperarCarga();

        expect(screen.getByText('Nueva reserva')).toBeDefined();
        fireEvent.click(screen.getByText('Nueva reserva'));
        // Verificar que el formulario está presente por un campo exclusivo del form
        expect(screen.getByText('Lote opcional')).toBeDefined();

        fireEvent.click(screen.getByText('Cerrar formulario'));
        expect(screen.queryByText('Lote opcional')).toBeNull();
    });

    it('el formulario contiene los campos obligatorios: Producto, Bodega, Cantidad', async () => {
        const { container } = render(<ReservasInventario />);
        await esperarCarga();

        fireEvent.click(screen.getByText('Nueva reserva'));

        expect(screen.getByText('Producto')).toBeDefined();
        expect(screen.getByText('Bodega')).toBeDefined();
        expect(screen.getByText('Cantidad')).toBeDefined();
        expect(container.querySelector('select[name="producto_id"]')).toBeTruthy();
        expect(container.querySelector('select[name="bodega_id"]')).toBeTruthy();
        expect(container.querySelector('input[name="cantidad"]')).toBeTruthy();
    });

    it('llama a inventarioApi.reservas.crear al enviar el formulario con datos válidos', async () => {
        const { container } = render(<ReservasInventario />);
        await esperarCarga();

        fireEvent.click(screen.getByText('Nueva reserva'));

        fireEvent.change(container.querySelector('select[name="producto_id"]'), {
            target: { name: 'producto_id', value: '1' },
        });
        fireEvent.change(container.querySelector('select[name="bodega_id"]'), {
            target: { name: 'bodega_id', value: '1' },
        });
        fireEvent.change(container.querySelector('input[name="cantidad"]'), {
            target: { name: 'cantidad', value: '5' },
        });

        fireEvent.submit(container.querySelector('form'));

        await waitFor(() => {
            expect(inventarioApi.reservas.crear).toHaveBeenCalledWith(
                expect.objectContaining({
                    detalles: expect.arrayContaining([
                        expect.objectContaining({ producto_id: 1, bodega_id: 1, cantidad: 5 }),
                    ]),
                }),
            );
        });
    });

    it('el filtro de estados incluye las opciones: Activa, Consumida, Liberada, Cancelada', async () => {
        render(<ReservasInventario />);
        await esperarCarga();

        expect(screen.getByRole('option', { name: 'Activa' })).toBeDefined();
        expect(screen.getByRole('option', { name: 'Consumida' })).toBeDefined();
        expect(screen.getByRole('option', { name: 'Liberada' })).toBeDefined();
        expect(screen.getByRole('option', { name: 'Cancelada' })).toBeDefined();
    });

    it('los botones de acción de una reserva ACTIVA están habilitados', async () => {
        inventarioApi.reservas.listar.mockResolvedValue({ data: [RESERVA_ACTIVA] });
        render(<ReservasInventario />);
        await esperarCarga();

        expect(screen.getByText('RES-2026-001')).toBeDefined();

        const btnLiberar = screen.getByRole('button', { name: 'Liberar' });
        const btnConsumir = screen.getByRole('button', { name: 'Consumir' });
        const btnCancelar = screen.getByRole('button', { name: 'Cancelar' });

        expect(btnLiberar.disabled).toBe(false);
        expect(btnConsumir.disabled).toBe(false);
        expect(btnCancelar.disabled).toBe(false);
    });

    it('llama a reservas.cancelar cuando el usuario confirma la cancelación', async () => {
        inventarioApi.reservas.listar.mockResolvedValue({ data: [RESERVA_ACTIVA] });
        render(<ReservasInventario />);
        await esperarCarga();

        fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith(
                expect.objectContaining({ title: 'Cancelar reserva' }),
            );
            expect(inventarioApi.reservas.cancelar).toHaveBeenCalledWith(
                RESERVA_ACTIVA.id,
                expect.any(Object),
            );
        });
    });
});
