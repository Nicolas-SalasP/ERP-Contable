import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    EmptyState: ({ title }) => <div>{title || 'Sin datos'}</div>,
    ErrorNotice: ({ error }) => (error ? <div data-testid="error-notice">Error</div> : null),
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
    SecondaryButton: ({ onClick, children, type }) => (
        <button onClick={onClick} type={type || 'button'}>{children}</button>
    ),
    TableShell: ({ children }) => <table>{children}</table>,
    Td: ({ children }) => <td>{children}</td>,
    Th: ({ children }) => <th>{children}</th>,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        lotes: {
            crear: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: vi.fn(),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import LotesInventario from './LotesInventario';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';

const mockCargarProductos = vi.fn().mockResolvedValue([]);
const mockCargarLotes = vi.fn().mockResolvedValue([]);
const mockInvalidarLotes = vi.fn();

const hookBase = {
    productos: [],
    lotes: [],
    cargarProductosCache: mockCargarProductos,
    cargarLotesCache: mockCargarLotes,
    invalidarLotes: mockInvalidarLotes,
};

afterEach(cleanup);

describe('LotesInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockCargarProductos.mockResolvedValue([]);
        mockCargarLotes.mockResolvedValue([]);
        vi.mocked(useInventarioData).mockReturnValue({ ...hookBase });
    });

    it('muestra el estado de carga mientras se obtienen los datos', () => {
        vi.mocked(useInventarioData).mockReturnValue({
            ...hookBase,
            cargarProductosCache: vi.fn(() => new Promise(() => {})),
            cargarLotesCache: vi.fn(() => new Promise(() => {})),
        });
        render(<LotesInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText(/cargando lotes de inventario/i)).toBeTruthy();
    });

    it('renderiza el título "Lotes y Vencimientos" tras cargar', async () => {
        render(<LotesInventario />);
        await waitFor(() => expect(screen.getByText('Lotes y Vencimientos')).toBeTruthy());
    });

    it('muestra el estado vacío cuando no existen lotes', async () => {
        render(<LotesInventario />);
        await waitFor(() => expect(screen.getByText('Sin lotes')).toBeTruthy());
    });

    it('muestra filas de lotes cuando existen registros', async () => {
        vi.mocked(useInventarioData).mockReturnValue({
            ...hookBase,
            lotes: [
                { id: 1, codigo_lote: 'LOTE-001', producto_id: 1, fecha_vencimiento: null },
                { id: 2, codigo_lote: 'LOTE-002', producto_id: 1, fecha_vencimiento: null },
            ],
        });
        render(<LotesInventario />);
        await waitFor(() => {
            expect(screen.getByText('LOTE-001')).toBeTruthy();
            expect(screen.getByText('LOTE-002')).toBeTruthy();
        });
    });

    it('muestra el formulario de nuevo lote al hacer clic en el botón', async () => {
        render(<LotesInventario />);
        await waitFor(() => screen.getByText('Nuevo lote'));
        fireEvent.click(screen.getByText('Nuevo lote'));
        await waitFor(() => expect(screen.getByText('Crear lote')).toBeTruthy());
    });

    it('el formulario contiene los campos "Código lote" y "Producto"', async () => {
        render(<LotesInventario />);
        await waitFor(() => screen.getByText('Nuevo lote'));
        fireEvent.click(screen.getByText('Nuevo lote'));
        await waitFor(() => {
            expect(screen.getByPlaceholderText('LOTE-001')).toBeTruthy();
            expect(screen.getByText('Código lote')).toBeTruthy();
            expect(screen.getByText('Producto')).toBeTruthy();
        });
    });

    it('llama a inventarioApi.lotes.crear al enviar el formulario', async () => {
        vi.mocked(inventarioApi.lotes.crear).mockResolvedValue({ data: { id: 3 } });
        vi.mocked(useInventarioData).mockReturnValue({
            ...hookBase,
            cargarProductosCache: vi.fn().mockResolvedValue([]),
            cargarLotesCache: vi.fn().mockResolvedValue([]),
            productos: [{ id: 1, nombre: 'Producto A', sku: 'SKU-001', maneja_lotes: true }],
        });

        render(<LotesInventario />);
        await waitFor(() => screen.getByText('Nuevo lote'));
        fireEvent.click(screen.getByText('Nuevo lote'));
        await waitFor(() => screen.getByPlaceholderText('LOTE-001'));

        const selectProducto = screen.getAllByRole('combobox')[0];
        fireEvent.change(selectProducto, { target: { value: '1' } });
        fireEvent.change(screen.getByPlaceholderText('LOTE-001'), { target: { value: 'LOTE-NUEVO' } });

        const form = document.querySelector('form');
        fireEvent.submit(form);

        await waitFor(() =>
            expect(vi.mocked(inventarioApi.lotes.crear)).toHaveBeenCalledWith(
                expect.objectContaining({ codigo_lote: 'LOTE-NUEVO', producto_id: 1 }),
            ),
        );
    });

    it('el botón "Actualizar" invoca la recarga de los datos', async () => {
        render(<LotesInventario />);
        await waitFor(() => screen.getByText('Actualizar'));
        fireEvent.click(screen.getByText('Actualizar'));
        await waitFor(() => expect(mockCargarProductos).toHaveBeenCalledTimes(2));
    });
});
