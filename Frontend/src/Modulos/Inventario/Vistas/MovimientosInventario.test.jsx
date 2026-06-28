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
        movimientos: {
            listar: vi.fn().mockResolvedValue({ data: [] }),
            registrar: vi.fn().mockResolvedValue({ success: true, data: { id: 1 } }),
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
    Field: ({ label, children }) => (
        <div>
            <label>{label}</label>
            {children}
        </div>
    ),
    formatCurrency: (v) => `$${Number(v ?? 0)}`,
    formatDate: (d) => d || '-',
    formatNumber: (v) => String(v ?? 0),
    getProductoNombre: (item) =>
        item?.producto_nombre || item?.nombre_producto || `Producto #${item?.producto_id ?? '-'}`,
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
import MovimientosInventario from './MovimientosInventario';

// ── Helpers ────────────────────────────────────────────────────────────────

const esperarCarga = () =>
    waitFor(() => expect(screen.queryByTestId('loading-state')).toBeNull());

afterEach(cleanup);

// ── Tests ──────────────────────────────────────────────────────────────────

describe('MovimientosInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        inventarioApi.movimientos.listar.mockResolvedValue({ data: [] });
        inventarioApi.movimientos.registrar.mockResolvedValue({ success: true, data: { id: 1 } });
    });

    it('muestra el estado de carga inicial antes de que la API responda', () => {
        inventarioApi.movimientos.listar.mockImplementation(() => new Promise(() => {}));
        render(<MovimientosInventario />);
        expect(screen.getByTestId('loading-state')).toBeDefined();
        expect(screen.getByText(/Cargando movimientos/i)).toBeDefined();
    });

    it('renderiza el título "Movimientos de Inventario" tras cargar', async () => {
        render(<MovimientosInventario />);
        await esperarCarga();
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Movimientos de Inventario',
        );
    });

    it('muestra el estado vacío cuando la API retorna una lista sin movimientos', async () => {
        inventarioApi.movimientos.listar.mockResolvedValue({ data: [] });
        render(<MovimientosInventario />);
        await esperarCarga();
        expect(screen.getByTestId('empty-state')).toBeDefined();
        expect(screen.getByText('Sin movimientos')).toBeDefined();
    });

    it('renderiza filas de movimientos cuando la API retorna datos', async () => {
        inventarioApi.movimientos.listar.mockResolvedValue({
            data: [
                {
                    id: 10,
                    tipo: 'entrada',
                    producto_nombre: 'Tornillo Acero',
                    bodega_destino_id: 1,
                    cantidad: 50,
                    costo_unitario: 1200,
                    referencia: 'ENT-2026-001',
                    fecha_movimiento: '2026-06-01T00:00:00Z',
                },
            ],
        });

        render(<MovimientosInventario />);
        await esperarCarga();

        expect(screen.getByText('ENT-2026-001')).toBeDefined();
        expect(screen.queryByTestId('empty-state')).toBeNull();
    });

    it('alterna la visibilidad del formulario con el botón "Nuevo movimiento"', async () => {
        render(<MovimientosInventario />);
        await esperarCarga();

        expect(screen.getByText('Nuevo movimiento')).toBeDefined();
        fireEvent.click(screen.getByText('Nuevo movimiento'));
        // Verificar que el formulario está presente por un campo exclusivo del form
        expect(screen.getByText('Tipo de movimiento')).toBeDefined();

        fireEvent.click(screen.getByText('Cerrar formulario'));
        expect(screen.queryByText('Tipo de movimiento')).toBeNull();
    });

    it('el select de tipo contiene todas las opciones de movimiento requeridas', async () => {
        render(<MovimientosInventario />);
        await esperarCarga();

        fireEvent.click(screen.getByText('Nuevo movimiento'));

        // Ambos selects (formulario y filtro) tienen las mismas opciones; se verifica que existan
        expect(screen.getAllByRole('option', { name: 'Entrada' }).length).toBeGreaterThan(0);
        expect(screen.getAllByRole('option', { name: 'Salida' }).length).toBeGreaterThan(0);
        expect(screen.getAllByRole('option', { name: 'Traspaso' }).length).toBeGreaterThan(0);
        expect(screen.getAllByRole('option', { name: 'Ajuste positivo' }).length).toBeGreaterThan(0);
        expect(screen.getAllByRole('option', { name: 'Ajuste negativo' }).length).toBeGreaterThan(0);
    });

    it('muestra "Bodega origen" y oculta "Bodega destino" al cambiar tipo a "salida"', async () => {
        const { container } = render(<MovimientosInventario />);
        await esperarCarga();

        fireEvent.click(screen.getByText('Nuevo movimiento'));

        const selectTipo = container.querySelector('select[name="tipo"]');
        fireEvent.change(selectTipo, { target: { name: 'tipo', value: 'salida' } });

        expect(screen.getByText('Bodega origen')).toBeDefined();
        expect(screen.queryByText('Bodega destino')).toBeNull();
    });

    it('llama a inventarioApi.movimientos.registrar al enviar el formulario con datos', async () => {
        const { container } = render(<MovimientosInventario />);
        await esperarCarga();

        fireEvent.click(screen.getByText('Nuevo movimiento'));

        fireEvent.change(container.querySelector('select[name="producto_id"]'), {
            target: { name: 'producto_id', value: '1' },
        });
        fireEvent.change(container.querySelector('select[name="bodega_destino_id"]'), {
            target: { name: 'bodega_destino_id', value: '1' },
        });
        fireEvent.change(container.querySelector('input[name="cantidad"]'), {
            target: { name: 'cantidad', value: '10' },
        });
        fireEvent.change(container.querySelector('input[name="costo_unitario"]'), {
            target: { name: 'costo_unitario', value: '5000' },
        });

        fireEvent.submit(container.querySelector('form'));

        await waitFor(() => {
            expect(inventarioApi.movimientos.registrar).toHaveBeenCalledWith(
                expect.objectContaining({ tipo: 'entrada', producto_id: 1, cantidad: 10 }),
            );
        });
    });

    it('el botón "Limpiar" resetea el campo referencia del formulario', async () => {
        const { container } = render(<MovimientosInventario />);
        await esperarCarga();

        fireEvent.click(screen.getByText('Nuevo movimiento'));

        const inputReferencia = container.querySelector('input[name="referencia"]');
        fireEvent.change(inputReferencia, {
            target: { name: 'referencia', value: 'REF-TEST-99' },
        });
        expect(inputReferencia.value).toBe('REF-TEST-99');

        fireEvent.click(screen.getByRole('button', { name: 'Limpiar' }));
        expect(inputReferencia.value).toBe('');
    });
});
