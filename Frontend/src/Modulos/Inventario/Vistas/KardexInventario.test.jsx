import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    EmptyState: ({ title, description }) => <div>{title || description || 'Sin datos'}</div>,
    Field: ({ label, children }) => <div><label>{label}</label>{children}</div>,
    formatDate: (d) => d ? String(d) : '-',
    formatNumber: (v, d) => Number(v ?? 0).toFixed(d ?? 0),
    getBodegaNombre: (item) => item?.bodega?.nombre || `Bodega #${item?.bodega_id ?? '-'}`,
    getProductoNombre: (item) => item?.producto?.nombre || item?.producto_nombre || `Producto #${item?.producto_id ?? '-'}`,
    LoadingState: ({ text }) => <div data-testid="loading-state">{text || 'Cargando...'}</div>,
    PageHeader: ({ title, description, actions }) => (
        <div>
            <h1>{title}</h1>
            <p>{description}</p>
            <div>{actions}</div>
        </div>
    ),
    Panel: ({ children, title, subtitle }) => (
        <div>
            <h2>{title}</h2>
            {subtitle && <p>{subtitle}</p>}
            {children}
        </div>
    ),
    PrimaryButton: ({ onClick, children, disabled, type }) => (
        <button type={type || 'button'} onClick={onClick} disabled={disabled}>{children}</button>
    ),
    SecondaryButton: ({ onClick, children, disabled }) => (
        <button onClick={onClick} disabled={disabled}>{children}</button>
    ),
    TableShell: ({ children }) => <table><tbody>{children}</tbody></table>,
    Td: ({ children, align }) => <td data-align={align}>{children}</td>,
    Th: ({ children, align }) => <th data-align={align}>{children}</th>,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        kardex: {
            listar: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: () => ({
        productos: [
            { id: 1, nombre: 'Producto Test', sku: 'SKU-001' },
            { id: 2, nombre: 'Producto Beta', sku: 'SKU-002' },
        ],
        bodegas: [
            { id: 1, nombre: 'Bodega Central', codigo: 'BC' },
        ],
        lotes: [
            { id: 1, codigo_lote: 'LOTE-001', producto_id: 1 },
        ],
        cargarProductosCache: vi.fn().mockResolvedValue([]),
        cargarBodegasCache: vi.fn().mockResolvedValue([]),
        cargarLotesCache: vi.fn().mockResolvedValue([]),
        invalidarProductos: vi.fn(),
        invalidarLotes: vi.fn(),
    }),
}));

import inventarioApi from '../Servicios/inventarioApi';
import KardexInventario from './KardexInventario';

const kardexVacio = { data: [] };

const kardexConMovimientos = {
    data: [
        {
            id: 1,
            tipo: 'entrada',
            fecha_movimiento: '2026-06-01',
            producto_id: 1,
            bodega_id: 1,
            producto: { nombre: 'Producto Test' },
            bodega: { nombre: 'Bodega Central' },
            cantidad: 100,
            entrada: 100,
            salida: null,
            saldo: 100,
            referencia: 'FACT-001',
            motivo: 'compra_proveedor',
        },
        {
            id: 2,
            tipo: 'salida',
            fecha_movimiento: '2026-06-10',
            producto_id: 1,
            bodega_id: 1,
            producto: { nombre: 'Producto Test' },
            bodega: { nombre: 'Bodega Central' },
            cantidad: 30,
            entrada: null,
            salida: 30,
            saldo: 70,
            referencia: 'GD-001',
            motivo: 'venta_cliente',
        },
        {
            id: 3,
            tipo: 'ajuste_positivo',
            fecha_movimiento: '2026-06-20',
            producto_id: 2,
            bodega_id: 1,
            producto: { nombre: 'Producto Beta' },
            bodega: { nombre: 'Bodega Central' },
            cantidad: 10,
            entrada: 10,
            salida: null,
            saldo: 10,
            referencia: 'AJ-001',
            motivo: 'ajuste_toma_fisica',
        },
    ],
};

afterEach(cleanup);

describe('KardexInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        inventarioApi.kardex.listar.mockResolvedValue(kardexVacio);
    });

    it('muestra el estado de carga al montar', () => {
        render(<KardexInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText('Cargando Kardex de inventario...')).toBeTruthy();
    });

    it('renderiza el título "Kardex de Inventario" tras la carga', async () => {
        render(<KardexInventario />);
        await waitFor(() => {
            expect(screen.getByText('Kardex de Inventario')).toBeTruthy();
        });
    });

    it('llama a kardex.listar al montar el componente', async () => {
        render(<KardexInventario />);
        await waitFor(() => {
            expect(inventarioApi.kardex.listar).toHaveBeenCalledTimes(1);
        });
    });

    it('muestra estado vacío cuando no hay movimientos en el Kardex', async () => {
        render(<KardexInventario />);
        await waitFor(() => {
            expect(screen.getByText('Sin movimientos en Kardex')).toBeTruthy();
        });
    });

    it('muestra movimientos en tabla cuando la API devuelve datos', async () => {
        inventarioApi.kardex.listar.mockResolvedValue(kardexConMovimientos);
        render(<KardexInventario />);
        await waitFor(() => {
            expect(screen.getAllByText('Producto Test').length).toBeGreaterThan(0);
            expect(screen.getByText('Producto Beta')).toBeTruthy();
        });
    });

    it('muestra las referencias de movimientos', async () => {
        inventarioApi.kardex.listar.mockResolvedValue(kardexConMovimientos);
        render(<KardexInventario />);
        await waitFor(() => {
            expect(screen.getByText('FACT-001')).toBeTruthy();
            expect(screen.getByText('GD-001')).toBeTruthy();
        });
    });

    it('el selector de producto muestra las opciones disponibles', async () => {
        render(<KardexInventario />);
        await waitFor(() => expect(screen.getByText('Kardex de Inventario')).toBeTruthy());

        // Hay múltiples selects con "Todos": producto (index 0), lote, tipo
        const selectores = screen.getAllByDisplayValue('Todos');
        expect(selectores.length).toBeGreaterThanOrEqual(3);

        // El primer select (producto) debe tener al menos 3 opciones: "Todos" + 2 productos
        const opciones = selectores[0].querySelectorAll('option');
        expect(opciones.length).toBeGreaterThanOrEqual(3);
    });

    it('el selector de bodega muestra las opciones disponibles', async () => {
        render(<KardexInventario />);
        await waitFor(() => expect(screen.getByText('Kardex de Inventario')).toBeTruthy());

        // El selector de bodega usa "Todas" como opción por defecto (distinto a los demás que usan "Todos")
        const selectBodega = screen.getByDisplayValue('Todas');
        expect(selectBodega).toBeTruthy();

        const opciones = selectBodega.querySelectorAll('option');
        // "Todas" + 1 bodega
        expect(opciones.length).toBeGreaterThanOrEqual(2);
    });

    it('llama a kardex.listar con los filtros aplicados al consultar', async () => {
        render(<KardexInventario />);
        await waitFor(() => expect(screen.getByText('Consultar Kardex')).toBeTruthy());

        // El primer select con "Todos" es el de producto_id (orden DOM)
        const selectProducto = screen.getAllByDisplayValue('Todos')[0];
        fireEvent.change(selectProducto, { target: { value: '1' } });

        await act(async () => {
            fireEvent.click(screen.getByText('Consultar Kardex'));
        });

        await waitFor(() => {
            // Primera llamada al montar + segunda al consultar
            expect(inventarioApi.kardex.listar).toHaveBeenCalledTimes(2);
            expect(inventarioApi.kardex.listar).toHaveBeenLastCalledWith(
                expect.objectContaining({ producto_id: '1' }),
            );
        });
    });

    it('limpia los filtros y los selectores vuelven a su estado inicial', async () => {
        render(<KardexInventario />);
        await waitFor(() => expect(screen.getByText('Limpiar')).toBeTruthy());

        // Cambia el filtro de producto: se reduce la cantidad de selects con "Todos"
        const selectProducto = screen.getAllByDisplayValue('Todos')[0];
        fireEvent.change(selectProducto, { target: { value: '1' } });

        // Ahora el select de producto no muestra "Todos", quedan menos selects con ese valor
        const cantidadAntes = screen.getAllByDisplayValue('Todos').length;
        expect(cantidadAntes).toBe(2); // lote y tipo

        await act(async () => {
            fireEvent.click(screen.getByText('Limpiar'));
        });

        // Tras limpiar, todos los filtros vuelven a "Todos"
        await waitFor(() => {
            expect(screen.getAllByDisplayValue('Todos').length).toBeGreaterThanOrEqual(3);
        });

        // La API fue invocada al menos 2 veces (montaje + limpiar)
        expect(inventarioApi.kardex.listar.mock.calls.length).toBeGreaterThanOrEqual(2);
    });

    it('muestra el panel de filtros con campos Desde y Hasta', async () => {
        render(<KardexInventario />);
        await waitFor(() => expect(screen.getByText('Filtros de consulta')).toBeTruthy());
        expect(screen.getByText('Desde')).toBeTruthy();
        expect(screen.getByText('Hasta')).toBeTruthy();
    });

    it('filtra los lotes mostrados según el producto seleccionado', async () => {
        render(<KardexInventario />);
        await waitFor(() => expect(screen.getByText('Kardex de Inventario')).toBeTruthy());

        // El primer select con "Todos" es producto_id (orden DOM)
        const selectProducto = screen.getAllByDisplayValue('Todos')[0];
        fireEvent.change(selectProducto, { target: { value: '1' } });

        await waitFor(() => {
            // Tras cambiar el producto, el select de producto muestra el producto seleccionado.
            // Los selectores con "Todos" restantes son: lote (índice 0) y tipo (índice 1)
            const selectoresActualizados = screen.getAllByDisplayValue('Todos');
            // El select de lote es ahora el primero con "Todos"
            const opcionesLote = selectoresActualizados[0]?.querySelectorAll('option') || [];
            const textos = Array.from(opcionesLote).map((o) => o.textContent);
            // LOTE-001 pertenece al producto 1, debe estar disponible
            expect(textos.some((t) => t.includes('LOTE-001'))).toBe(true);
        });
    });

    it('muestra el panel de resultado con cabeceras de la tabla', async () => {
        inventarioApi.kardex.listar.mockResolvedValue(kardexConMovimientos);
        render(<KardexInventario />);
        await waitFor(() => {
            expect(screen.getByText('Resultado Kardex')).toBeTruthy();
            // "Fecha" solo aparece como cabecera de tabla
            expect(screen.getByText('Fecha')).toBeTruthy();
            // "Saldo" y "Referencia" son únicos en la tabla
            expect(screen.getByText('Saldo')).toBeTruthy();
            expect(screen.getByText('Referencia')).toBeTruthy();
            // "Entrada" y "Salida" también aparecen como opciones del filtro de tipo
            expect(screen.getAllByText('Entrada').length).toBeGreaterThanOrEqual(2);
            expect(screen.getAllByText('Salida').length).toBeGreaterThanOrEqual(2);
            // "Tipo", "Producto", "Bodega" aparecen como etiqueta de filtro Y cabecera de tabla
            expect(screen.getAllByText('Tipo').length).toBeGreaterThanOrEqual(2);
        });
    });
});
