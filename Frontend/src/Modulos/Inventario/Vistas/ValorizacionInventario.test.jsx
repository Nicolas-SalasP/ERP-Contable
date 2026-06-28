import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    EmptyState: ({ title }) => <div>{title || 'Sin datos'}</div>,
    formatCurrency: (v) => `$${Number(v ?? 0)}`,
    formatNumber: (v) => String(v ?? 0),
    getBodegaNombre: (item) => item?.bodega?.nombre || `Bodega #${item?.bodega_id ?? '-'}`,
    getProductoNombre: (item) => item?.producto?.nombre || `Producto #${item?.producto_id ?? '-'}`,
    LoadingState: ({ text }) => <div data-testid="loading-state">{text || 'Cargando...'}</div>,
    PageHeader: ({ title, actions }) => <div><h1>{title}</h1>{actions}</div>,
    Panel: ({ children, title }) => <div><h2>{title}</h2>{children}</div>,
    SecondaryButton: ({ onClick, children }) => <button onClick={onClick}>{children}</button>,
    StatCard: ({ title, value }) => <div data-testid="stat-card"><span>{title}</span><span>{value}</span></div>,
    TableShell: ({ children }) => <table>{children}</table>,
    Td: ({ children }) => <td>{children}</td>,
    Th: ({ children }) => <th>{children}</th>,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        valorizacion: {
            listar: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: vi.fn(),
}));

import ValorizacionInventario from './ValorizacionInventario';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';

const mockCargarProductos = vi.fn().mockResolvedValue([]);
const mockCargarBodegas = vi.fn().mockResolvedValue([]);

const hookBase = {
    productos: [{ id: 1, nombre: 'Producto Test', sku: 'SKU-001' }],
    bodegas: [{ id: 1, nombre: 'Bodega Central', codigo: 'BOD-001' }],
    cargarProductosCache: mockCargarProductos,
    cargarBodegasCache: mockCargarBodegas,
};

afterEach(cleanup);

describe('ValorizacionInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockCargarProductos.mockResolvedValue([]);
        mockCargarBodegas.mockResolvedValue([]);
        vi.mocked(inventarioApi.valorizacion.listar).mockResolvedValue({ data: [] });
        vi.mocked(useInventarioData).mockReturnValue({ ...hookBase });
    });

    it('muestra el estado de carga mientras se obtienen los datos', () => {
        vi.mocked(inventarioApi.valorizacion.listar).mockReturnValue(new Promise(() => {}));
        render(<ValorizacionInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText(/cargando valorización de inventario/i)).toBeTruthy();
    });

    it('renderiza el título "Valorización de Inventario" tras cargar', async () => {
        render(<ValorizacionInventario />);
        await waitFor(() => expect(screen.getByText('Valorización de Inventario')).toBeTruthy());
    });

    it('llama a inventarioApi.valorizacion.listar al montar el componente', async () => {
        render(<ValorizacionInventario />);
        await waitFor(() =>
            expect(vi.mocked(inventarioApi.valorizacion.listar)).toHaveBeenCalled(),
        );
    });

    it('muestra el estado vacío cuando no hay datos de valorización', async () => {
        render(<ValorizacionInventario />);
        await waitFor(() => expect(screen.getByText('Sin valorización')).toBeTruthy());
    });

    it('muestra filas con datos de valorización cuando existen registros', async () => {
        vi.mocked(inventarioApi.valorizacion.listar).mockResolvedValue({
            data: [
                { id: 1, producto_id: 1, bodega_id: 1, stock_actual: 50, costo_promedio: 10000, valor_total: 500000 },
                { id: 2, producto_id: 2, bodega_id: 1, stock_actual: 30, costo_promedio: 5000, valor_total: 150000 },
            ],
        });
        render(<ValorizacionInventario />);
        await waitFor(() => {
            expect(screen.getByText('Producto #1')).toBeTruthy();
            expect(screen.getByText('Producto #2')).toBeTruthy();
        });
    });

    it('muestra el panel de filtros con selectores de producto y bodega', async () => {
        render(<ValorizacionInventario />);
        await waitFor(() => expect(screen.getByText('Filtros')).toBeTruthy());
        expect(screen.getByText('Todos los productos')).toBeTruthy();
        expect(screen.getByText('Todas las bodegas')).toBeTruthy();
    });

    it('muestra las tarjetas de resumen con los totales calculados', async () => {
        render(<ValorizacionInventario />);
        // Esperar que el componente termine de cargar verificando el título
        await waitFor(() => expect(screen.getByText('Valorización de Inventario')).toBeTruthy());
        // Las tarjetas se renderizan siempre (muestran 0 cuando no hay datos)
        const tarjetas = screen.getAllByTestId('stat-card');
        expect(tarjetas.length).toBeGreaterThanOrEqual(4);
        expect(screen.getByText('Registros')).toBeTruthy();
        expect(screen.getByText('Valor total')).toBeTruthy();
    });

    it('el botón "Actualizar" recarga los datos de valorización', async () => {
        render(<ValorizacionInventario />);
        await waitFor(() => screen.getByText('Actualizar'));
        fireEvent.click(screen.getByText('Actualizar'));
        await waitFor(() =>
            expect(vi.mocked(inventarioApi.valorizacion.listar)).toHaveBeenCalledTimes(2),
        );
    });
});
