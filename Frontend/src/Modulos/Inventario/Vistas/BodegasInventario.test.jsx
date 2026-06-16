import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

// ── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../Servicios/inventarioRealtime', () => ({
    suscribirInventarioEmpresa: vi.fn().mockResolvedValue(() => {}),
    default: { suscribirInventarioEmpresa: vi.fn().mockResolvedValue(() => {}) },
}));

const contextMock = vi.hoisted(() => ({
    bodegas: [],
    productos: [],
    catalogos: { unidades_medida: [] },
    ubicaciones: [],
    lotes: [],
    cargarBodegasCache: vi.fn().mockResolvedValue({ data: [] }),
    cargarProductosCache: vi.fn().mockResolvedValue({ data: [] }),
    cargarCatalogosCache: vi.fn().mockResolvedValue({ data: {} }),
    cargarUbicacionesCache: vi.fn().mockResolvedValue({ data: [] }),
    cargarLotesCache: vi.fn().mockResolvedValue({ data: [] }),
    invalidarBodegas: vi.fn(),
    invalidarCatalogos: vi.fn(),
    invalidarProductos: vi.fn(),
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
        bodegas: {
            listar: vi.fn().mockResolvedValue({ data: [] }),
            crear: vi.fn().mockResolvedValue({ success: true, data: {} }),
        },
    },
    default: {
        bodegas: {
            listar: vi.fn().mockResolvedValue({ data: [] }),
            crear: vi.fn().mockResolvedValue({ success: true, data: {} }),
        },
    },
}));

import BodegasInventario from './BodegasInventario';
import inventarioApi from '../Servicios/inventarioApi';

// ── Fixtures ───────────────────────────────────────────────────────────────

const bodegasMock = [
    {
        id: 1,
        codigo: 'BOD-001',
        nombre: 'Bodega Central',
        ubicacion: 'Santiago, Piso 1',
        descripcion: 'Bodega principal de operaciones',
        activa: true,
    },
    {
        id: 2,
        codigo: 'BOD-002',
        nombre: 'Bodega Norte',
        ubicacion: null,
        descripcion: null,
        activa: false,
    },
];

// ── Setup ──────────────────────────────────────────────────────────────────

beforeEach(() => {
    vi.clearAllMocks();
    contextMock.bodegas = bodegasMock;
    contextMock.cargarBodegasCache.mockResolvedValue({ data: bodegasMock });
});

afterEach(cleanup);

// ── Tests ──────────────────────────────────────────────────────────────────

describe('BodegasInventario — render y listado', () => {
    it('smoke test: renderiza sin crash', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('Bodegas')).toBeDefined();
    });

    it('lista las bodegas que provee el contexto', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('Bodega Central')).toBeDefined();
        expect(screen.getByText('Bodega Norte')).toBeDefined();
    });

    it('muestra el código de cada bodega', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('BOD-001')).toBeDefined();
        expect(screen.getByText('BOD-002')).toBeDefined();
    });

    it('muestra badge Activa / Inactiva correctamente', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getAllByText('Activa').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Inactiva').length).toBeGreaterThan(0);
    });

    it('muestra guión "-" en Ubicación para bodegas sin ubicación', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        // BOD-002 no tiene ubicación ni descripción → debería aparecer al menos un "-"
        const dashes = screen.getAllByText('-');
        expect(dashes.length).toBeGreaterThan(0);
    });

    it('estado vacío: muestra "Sin bodegas" cuando no hay datos', async () => {
        contextMock.bodegas = [];
        contextMock.cargarBodegasCache.mockResolvedValue({ data: [] });

        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('Sin bodegas')).toBeDefined();
    });

    it('muestra el botón "Nueva bodega"', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());
        expect(screen.getByText('Nueva bodega')).toBeDefined();
    });
});

describe('BodegasInventario — búsqueda', () => {
    it('filtra bodegas por nombre al escribir en el buscador', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        const input = screen.getByPlaceholderText(/buscar por código/i);
        fireEvent.change(input, { target: { value: 'central' } });

        await waitFor(() => {
            expect(screen.getByText('Bodega Central')).toBeDefined();
            expect(screen.queryByText('Bodega Norte')).toBeNull();
        });
    });

    it('filtra por código', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        const input = screen.getByPlaceholderText(/buscar por código/i);
        fireEvent.change(input, { target: { value: 'BOD-002' } });

        await waitFor(() => {
            expect(screen.getByText('Bodega Norte')).toBeDefined();
            expect(screen.queryByText('Bodega Central')).toBeNull();
        });
    });
});

describe('BodegasInventario — formulario de alta', () => {
    it('abre el formulario al pulsar "Nueva bodega"', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getByText('Nueva bodega'));
        expect(screen.getByText('Crear bodega')).toBeDefined();
    });

    it('cierra el formulario al pulsar "Cerrar formulario"', async () => {
        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getByText('Nueva bodega'));
        fireEvent.click(screen.getByText('Cerrar formulario'));
        expect(screen.queryByText('Crear bodega')).toBeNull();
    });

    it('llama a inventarioApi.bodegas.crear al enviar el formulario', async () => {
        inventarioApi.bodegas.crear.mockResolvedValue({ success: true, data: { id: 10 } });

        render(<BodegasInventario />);
        await waitFor(() => expect(screen.queryByText(/cargando/i)).toBeNull());

        fireEvent.click(screen.getByText('Nueva bodega'));

        fireEvent.change(screen.getByPlaceholderText('BOD-001'), { target: { value: 'BOD-TEST' } });
        fireEvent.change(screen.getByPlaceholderText('Bodega central'), { target: { value: 'Bodega Test' } });

        fireEvent.click(screen.getByText('Guardar bodega'));

        await waitFor(() => {
            expect(inventarioApi.bodegas.crear).toHaveBeenCalledTimes(1);
        });
    });
});
