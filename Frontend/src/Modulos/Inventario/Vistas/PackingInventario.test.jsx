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
        packing: {
            listar: vi.fn(),
            crear: vi.fn(),
            iniciar: vi.fn(),
            confirmar: vi.fn(),
            cancelar: vi.fn(),
        },
        picking: {
            listar: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: vi.fn(),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import PackingInventario from './PackingInventario';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';

const mockInvalidarTodo = vi.fn();
const hookBase = { invalidarTodoInventario: mockInvalidarTodo };

afterEach(cleanup);

describe('PackingInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(inventarioApi.packing.listar).mockResolvedValue({ data: [] });
        vi.mocked(inventarioApi.picking.listar).mockResolvedValue({ data: [] });
        vi.mocked(useInventarioData).mockReturnValue({ ...hookBase });
    });

    it('muestra el estado de carga mientras se obtienen los datos', () => {
        vi.mocked(inventarioApi.packing.listar).mockReturnValue(new Promise(() => {}));
        vi.mocked(inventarioApi.picking.listar).mockReturnValue(new Promise(() => {}));
        render(<PackingInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText(/cargando operación de packing/i)).toBeTruthy();
    });

    it('renderiza el título "Packing de Bodega" tras cargar', async () => {
        render(<PackingInventario />);
        await waitFor(() => expect(screen.getByText('Packing de Bodega')).toBeTruthy());
    });

    it('llama a packing.listar y picking.listar al montar el componente', async () => {
        render(<PackingInventario />);
        await waitFor(() => {
            expect(vi.mocked(inventarioApi.packing.listar)).toHaveBeenCalledWith({ per_page: 100 });
            expect(vi.mocked(inventarioApi.picking.listar)).toHaveBeenCalledWith({ per_page: 100 });
        });
    });

    it('muestra el estado vacío cuando no existen órdenes de packing', async () => {
        render(<PackingInventario />);
        await waitFor(() => expect(screen.getByText('Sin órdenes de packing')).toBeTruthy());
    });

    it('muestra órdenes de packing en tabla cuando existen registros', async () => {
        vi.mocked(inventarioApi.packing.listar).mockResolvedValue({
            data: [
                { id: 1, codigo: 'PACK-001', estado: 'PENDIENTE', detalles: [], picking_orden_id: 1 },
                { id: 2, codigo: 'PACK-002', estado: 'EN_EMPAQUE', detalles: [], picking_orden_id: 2 },
            ],
        });
        render(<PackingInventario />);
        await waitFor(() => {
            expect(screen.getByText('PACK-001')).toBeTruthy();
            expect(screen.getByText('PACK-002')).toBeTruthy();
        });
    });

    it('abre el formulario al hacer clic en "Generar packing"', async () => {
        render(<PackingInventario />);
        await waitFor(() => screen.getByText('Generar packing'));
        fireEvent.click(screen.getByText('Generar packing'));
        await waitFor(() => expect(screen.getByText('Generar packing desde picking')).toBeTruthy());
    });

    it('el botón "Crear packing" está deshabilitado cuando no hay picking disponible', async () => {
        render(<PackingInventario />);
        await waitFor(() => screen.getByText('Generar packing'));
        fireEvent.click(screen.getByText('Generar packing'));
        await waitFor(() => {
            const btn = screen.getByText('Crear packing');
            expect(btn.disabled).toBe(true);
        });
    });

    it('muestra las tarjetas de resumen tras cargar', async () => {
        render(<PackingInventario />);
        await waitFor(() => {
            const tarjetas = screen.getAllByTestId('stat-card');
            expect(tarjetas.length).toBeGreaterThanOrEqual(4);
        });
    });
});
