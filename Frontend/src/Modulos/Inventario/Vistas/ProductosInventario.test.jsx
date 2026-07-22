import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

// ── Mocks de dependencias externas ─────────────────────────────────────────

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../Servicios/inventarioRealtime', () => ({
    suscribirInventarioEmpresa: vi.fn().mockResolvedValue(() => {}),
    default: { suscribirInventarioEmpresa: vi.fn().mockResolvedValue(() => {}) },
}));

// Mock del contexto: exponemos estado controlable por los tests
const contextMock = vi.hoisted(() => ({
    productos: [],
    catalogos: { unidades_medida: [] },
    bodegas: [],
    ubicaciones: [],
    lotes: [],
    cargarProductosCache: vi.fn().mockResolvedValue({ data: [] }),
    cargarCatalogosCache: vi.fn().mockResolvedValue({ data: {} }),
    cargarBodegasCache: vi.fn().mockResolvedValue({ data: [] }),
    cargarUbicacionesCache: vi.fn().mockResolvedValue({ data: [] }),
    cargarLotesCache: vi.fn().mockResolvedValue({ data: [] }),
    invalidarProductos: vi.fn(),
    invalidarBodegas: vi.fn(),
    invalidarCatalogos: vi.fn(),
    invalidarUbicaciones: vi.fn(),
    invalidarLotes: vi.fn(),
    invalidarTodoInventario: vi.fn(),
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: () => contextMock,
    default: () => contextMock,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    inventarioApi: {
        productos: {
            listar: vi.fn().mockResolvedValue({ data: [] }),
            crear: vi.fn().mockResolvedValue({ success: true, data: {} }),
            actualizar: vi.fn().mockResolvedValue({ success: true, data: {} }),
        },
        catalogos: vi.fn().mockResolvedValue({ data: {} }),
    },
    default: {
        productos: {
            listar: vi.fn().mockResolvedValue({ data: [] }),
            crear: vi.fn().mockResolvedValue({ success: true, data: {} }),
            actualizar: vi.fn().mockResolvedValue({ success: true, data: {} }),
        },
        catalogos: vi.fn().mockResolvedValue({ data: {} }),
    },
}));

import ProductosInventario from './ProductosInventario';
import inventarioApi from '../Servicios/inventarioApi';

// ── Fixtures ───────────────────────────────────────────────────────────────

const productosMock = [
    {
        id: 1,
        sku: 'SKU-001',
        nombre: 'Tornillo Hex 1/4',
        descripcion: 'Tornillo hexagonal galvanizado',
        unidad_medida: { id: 1, nombre: 'Unidad' },
        costo_promedio: 150,
        stock_minimo: 100,
        maneja_lotes: false,
        requiere_fecha_vencimiento: false,
        activo: true,
    },
    {
        id: 2,
        sku: 'SKU-002',
        nombre: 'Pintura Blanca 1L',
        descripcion: null,
        unidad_medida: { id: 2, nombre: 'Litro' },
        costo_promedio: 4500,
        stock_minimo: 50,
        maneja_lotes: true,
        requiere_fecha_vencimiento: true,
        activo: false,
    },
];

const catalogosMock = {
    unidades_medida: [
        { id: 1, nombre: 'Unidad' },
        { id: 2, nombre: 'Litro' },
    ],
};

// ── Setup ──────────────────────────────────────────────────────────────────

beforeEach(() => {
    vi.clearAllMocks();
    // Estado base del contexto con productos listos
    contextMock.productos = productosMock;
    contextMock.catalogos = catalogosMock;
    contextMock.cargarProductosCache.mockResolvedValue({ data: productosMock });
    contextMock.cargarCatalogosCache.mockResolvedValue({ data: catalogosMock });
});

afterEach(cleanup);

// ── Tests ──────────────────────────────────────────────────────────────────

describe('ProductosInventario — render y listado', () => {
    it('smoke test: renderiza sin crash', async () => {
        render(<ProductosInventario />);
        await waitFor(() => {
            expect(screen.queryByText(/cargando/i)).toBeNull();
        });
        expect(screen.getByText('Productos de Inventario')).toBeDefined();
    });

    it('muestra los productos que devuelve el contexto', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('Tornillo Hex 1/4')).toBeDefined();
        expect(screen.getByText('Pintura Blanca 1L')).toBeDefined();
    });

    it('muestra el SKU de cada producto en la tabla', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('SKU-001')).toBeDefined();
        expect(screen.getByText('SKU-002')).toBeDefined();
    });

    it('muestra badge Activo / Inactivo correctamente', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getAllByText('Activo').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Inactivo').length).toBeGreaterThan(0);
    });

    it('estado vacío: muestra mensaje "Sin productos" cuando no hay datos', async () => {
        contextMock.productos = [];
        contextMock.cargarProductosCache.mockResolvedValue({ data: [] });

        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('Sin productos')).toBeDefined();
    });

    it('muestra el botón "Nuevo producto"', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('Nuevo producto')).toBeDefined();
    });
});

describe('ProductosInventario — búsqueda/filtro', () => {
    it('filtra productos por SKU al escribir en el buscador', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        const input = screen.getByPlaceholderText(/buscar por sku/i);
        fireEvent.change(input, { target: { value: 'SKU-001' } });

        await waitFor(() => {
            expect(screen.getByText('Tornillo Hex 1/4')).toBeDefined();
            expect(screen.queryByText('Pintura Blanca 1L')).toBeNull();
        });
    });

    it('filtra productos por nombre parcial', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        const input = screen.getByPlaceholderText(/buscar por sku/i);
        fireEvent.change(input, { target: { value: 'pintura' } });

        await waitFor(() => {
            expect(screen.getByText('Pintura Blanca 1L')).toBeDefined();
            expect(screen.queryByText('Tornillo Hex 1/4')).toBeNull();
        });
    });

    it('muestra "Sin productos" si el filtro no coincide con nada', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        const input = screen.getByPlaceholderText(/buscar por sku/i);
        fireEvent.change(input, { target: { value: 'INEXISTENTE_XYZ' } });

        await waitFor(() => {
            expect(screen.getByText('Sin productos')).toBeDefined();
        });
    });
});

describe('ProductosInventario — formulario de alta', () => {
    it('muestra el formulario al pulsar "Nuevo producto"', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getByText('Nuevo producto'));

        expect(screen.getByText('Crear producto')).toBeDefined();
    });

    it('oculta el formulario al pulsar "Cerrar formulario"', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getByText('Nuevo producto'));
        expect(screen.getByText('Crear producto')).toBeDefined();

        fireEvent.click(screen.getByText('Cerrar formulario'));
        expect(screen.queryByText('Crear producto')).toBeNull();
    });

    it('muestra las unidades de medida del catálogo en el select', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getByText('Nuevo producto'));

        // Verificamos que el select de unidad de medida contiene las opciones del catálogo
        // Usamos getAllByText porque "Unidad" puede aparecer también en la columna de la tabla
        expect(screen.getAllByText('Unidad').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Litro').length).toBeGreaterThan(0);
    });

    it('llama a inventarioApi.productos.crear al enviar el formulario válido', async () => {
        inventarioApi.productos.crear.mockResolvedValue({ success: true, data: { id: 99 } });

        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getByText('Nuevo producto'));

        fireEvent.change(screen.getByPlaceholderText('SKU-001'), { target: { value: 'SKU-TEST' } });
        fireEvent.change(screen.getByPlaceholderText('Producto demo'), { target: { value: 'Producto Test' } });

        // Seleccionar unidad de medida
        const selectUnidad = screen.getByRole('combobox');
        fireEvent.change(selectUnidad, { target: { value: '1' } });

        fireEvent.click(screen.getByText('Guardar producto'));

        await waitFor(() => {
            expect(inventarioApi.productos.crear).toHaveBeenCalledTimes(1);
        });
    });

    it('el payload de creación incluye visible_web', async () => {
        inventarioApi.productos.crear.mockResolvedValue({ success: true, data: { id: 99 } });

        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getByText('Nuevo producto'));
        fireEvent.change(screen.getByPlaceholderText('SKU-001'), { target: { value: 'SKU-TEST' } });
        fireEvent.change(screen.getByPlaceholderText('Producto demo'), { target: { value: 'Producto Test' } });
        fireEvent.change(screen.getByRole('combobox'), { target: { value: '1' } });
        fireEvent.click(screen.getByText('Publicar en la web'));
        fireEvent.click(screen.getByText('Guardar producto'));

        await waitFor(() => {
            expect(inventarioApi.productos.crear).toHaveBeenCalledWith(
                expect.objectContaining({ visible_web: true }),
            );
        });
    });
});

describe('ProductosInventario — edición', () => {
    it('muestra el botón "Editar" por cada producto', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        expect(screen.getAllByText('Editar').length).toBe(productosMock.length);
    });

    it('abre el formulario precargado con los datos del producto al pulsar "Editar"', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getAllByText('Editar')[0]);

        expect(screen.getByText('Editar producto')).toBeDefined();
        expect(screen.getByDisplayValue('SKU-001')).toBeDefined();
        expect(screen.getByDisplayValue('Tornillo Hex 1/4')).toBeDefined();
    });

    it('llama a inventarioApi.productos.actualizar (no crear) al guardar una edición', async () => {
        inventarioApi.productos.actualizar.mockResolvedValue({ success: true, data: {} });

        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getAllByText('Editar')[0]);
        fireEvent.click(screen.getByText('Guardar cambios'));

        await waitFor(() => {
            expect(inventarioApi.productos.actualizar).toHaveBeenCalledTimes(1);
            expect(inventarioApi.productos.actualizar).toHaveBeenCalledWith(1, expect.any(Object));
        });
        expect(inventarioApi.productos.crear).not.toHaveBeenCalled();
    });

    it('"Cancelar edición" limpia el formulario y vuelve a modo creación', async () => {
        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getAllByText('Editar')[0]);
        expect(screen.getByText('Editar producto')).toBeDefined();

        fireEvent.click(screen.getByText('Cancelar edición'));
        expect(screen.queryByDisplayValue('SKU-001')).toBeNull();
    });

    it('muestra badge Publicado / Oculto según visible_web', async () => {
        contextMock.productos = [
            { ...productosMock[0], visible_web: true },
            { ...productosMock[1], visible_web: false },
        ];
        contextMock.cargarProductosCache.mockResolvedValue({ data: contextMock.productos });

        render(<ProductosInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        expect(screen.getByText('Publicado')).toBeDefined();
        expect(screen.getByText('Oculto')).toBeDefined();
    });
});
