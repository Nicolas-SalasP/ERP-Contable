import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    AlertBox: ({ children }) => <div data-testid="alert-box">{children}</div>,
    EmptyState: ({ title }) => <div>{title || 'Sin datos'}</div>,
    ErrorNotice: ({ error }) => (error ? <div data-testid="error-notice">Error</div> : null),
    EstadoBadge: ({ value }) => <span>{value}</span>,
    Field: ({ label, children }) => <div><label>{label}</label>{children}</div>,
    formatDate: (d) => d || '-',
    formatNumber: (v) => String(v ?? 0),
    getBodegaNombre: (item) => item?.bodega?.nombre || `Bodega #${item?.bodega_id ?? '-'}`,
    getProductoNombre: (item) => item?.producto?.nombre || `Producto #${item?.producto_id ?? '-'}`,
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
        picking: {
            listar: vi.fn(),
            crear: vi.fn(),
            asignar: vi.fn(),
            iniciar: vi.fn(),
            confirmar: vi.fn(),
            cancelar: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: vi.fn(),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import PickingInventario from './PickingInventario';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';

const mockCargarProductos = vi.fn().mockResolvedValue([]);
const mockCargarBodegas = vi.fn().mockResolvedValue([]);
const mockInvalidarTodo = vi.fn();

const hookBase = {
    productos: [{ id: 1, nombre: 'Producto Test', sku: 'SKU-001' }],
    bodegas: [{ id: 1, nombre: 'Bodega Central', codigo: 'BOD-001' }],
    cargarProductosCache: mockCargarProductos,
    cargarBodegasCache: mockCargarBodegas,
    invalidarTodoInventario: mockInvalidarTodo,
};

afterEach(cleanup);

describe('PickingInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockCargarProductos.mockResolvedValue([]);
        mockCargarBodegas.mockResolvedValue([]);
        vi.mocked(inventarioApi.picking.listar).mockResolvedValue({ data: [] });
        vi.mocked(useInventarioData).mockReturnValue({ ...hookBase });
    });

    it('muestra el estado de carga mientras se obtienen los datos', () => {
        vi.mocked(useInventarioData).mockReturnValue({
            ...hookBase,
            cargarProductosCache: vi.fn(() => new Promise(() => {})),
            cargarBodegasCache: vi.fn(() => new Promise(() => {})),
        });
        render(<PickingInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText(/cargando operación de picking/i)).toBeTruthy();
    });

    it('renderiza el título "Picking de Bodega" tras cargar', async () => {
        render(<PickingInventario />);
        await waitFor(() => expect(screen.getByText('Picking de Bodega')).toBeTruthy());
    });

    it('llama a inventarioApi.picking.listar al montar el componente', async () => {
        render(<PickingInventario />);
        await waitFor(() =>
            expect(vi.mocked(inventarioApi.picking.listar)).toHaveBeenCalledWith({ per_page: 100 }),
        );
    });

    it('muestra el estado vacío cuando no existen órdenes de picking', async () => {
        render(<PickingInventario />);
        await waitFor(() => expect(screen.getByText('Sin órdenes de picking')).toBeTruthy());
    });

    it('muestra órdenes en tabla cuando existen registros', async () => {
        vi.mocked(inventarioApi.picking.listar).mockResolvedValue({
            data: [
                { id: 1, codigo: 'PICK-001', estado: 'PENDIENTE', prioridad: 'NORMAL', detalles: [], bodega_id: 1 },
                { id: 2, codigo: 'PICK-002', estado: 'EN_PREPARACION', prioridad: 'ALTA', detalles: [], bodega_id: 1 },
            ],
        });
        render(<PickingInventario />);
        await waitFor(() => {
            expect(screen.getByText('PICK-001')).toBeTruthy();
            expect(screen.getByText('PICK-002')).toBeTruthy();
        });
    });

    it('muestra las tarjetas de resumen tras cargar', async () => {
        render(<PickingInventario />);
        await waitFor(() => {
            const tarjetas = screen.getAllByTestId('stat-card');
            expect(tarjetas.length).toBeGreaterThanOrEqual(4);
        });
    });

    it('abre el formulario al hacer clic en "Nueva orden"', async () => {
        render(<PickingInventario />);
        await waitFor(() => screen.getByText('Nueva orden'));
        fireEvent.click(screen.getByText('Nueva orden'));
        await waitFor(() => expect(screen.getByText('Crear orden interna de picking')).toBeTruthy());
    });

    it('llama a inventarioApi.picking.crear al enviar el formulario', async () => {
        vi.mocked(inventarioApi.picking.crear).mockResolvedValue({ data: { id: 10 } });

        render(<PickingInventario />);
        await waitFor(() => screen.getByText('Nueva orden'));
        fireEvent.click(screen.getByText('Nueva orden'));
        await waitFor(() => screen.getByText('Crear orden interna de picking'));

        const selects = screen.getAllByRole('combobox');
        fireEvent.change(selects[0], { target: { value: '1' } }); // bodega
        fireEvent.change(selects[1], { target: { value: '1' } }); // producto

        const inputCantidad = screen.getByRole('spinbutton');
        fireEvent.change(inputCantidad, { target: { value: '10' } });

        const form = document.querySelector('form');
        fireEvent.submit(form);

        await waitFor(() =>
            expect(vi.mocked(inventarioApi.picking.crear)).toHaveBeenCalledWith(
                expect.objectContaining({ bodega_id: 1, detalles: expect.any(Array) }),
            ),
        );
    });
});
