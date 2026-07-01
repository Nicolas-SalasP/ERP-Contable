import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    EmptyState: ({ title, description }) => <div>{title || description || 'Sin datos'}</div>,
    EstadoBadge: ({ value }) => <span data-testid="estado-badge">{value}</span>,
    ErrorNotice: ({ error }) => error ? <div data-testid="error-notice">{error?.message || String(error)}</div> : null,
    Field: ({ label, children }) => <div><label>{label}</label>{children}</div>,
    formatNumber: (v, d) => Number(v ?? 0).toFixed(d ?? 0),
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
    StatCard: ({ title, value, _icon, tone }) => (
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
        ubicaciones: {
            listar: vi.fn(),
            crear: vi.fn(),
        },
        stockUbicaciones: {
            listar: vi.fn(),
            mover: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: () => ({
        bodegas: [
            { id: 1, nombre: 'Bodega Central', codigo: 'BC' },
            { id: 2, nombre: 'Bodega Norte', codigo: 'BN' },
        ],
        productos: [
            { id: 1, nombre: 'Producto Test', sku: 'SKU-001' },
        ],
        ubicaciones: [],
        cargarBodegasCache: vi.fn().mockResolvedValue([]),
        cargarProductosCache: vi.fn().mockResolvedValue([]),
        cargarUbicacionesCache: vi.fn().mockResolvedValue([]),
        invalidarUbicaciones: vi.fn(),
        invalidarTodoInventario: vi.fn(),
    }),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import inventarioApi from '../Servicios/inventarioApi';
import UbicacionesInventario from './UbicacionesInventario';

const stockVacio = { data: [] };

const stockConDatos = {
    data: [
        {
            id: 1,
            producto_id: 1,
            bodega_id: 1,
            ubicacion_id: 1,
            stock_actual: 50,
            stock_disponible: 45,
            stock_reservado: 5,
            stock_cuarentena: 0,
            stock_bloqueado: 0,
            stock_en_transito: 0,
            producto: { nombre: 'Producto Test' },
            bodega: { nombre: 'Bodega Central' },
            ubicacion: { codigo: 'PAS-A', nombre: 'Pasillo A' },
        },
    ],
};

afterEach(cleanup);

describe('UbicacionesInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        inventarioApi.stockUbicaciones.listar.mockResolvedValue(stockVacio);
        inventarioApi.ubicaciones.crear.mockResolvedValue({ data: {} });
        inventarioApi.stockUbicaciones.mover.mockResolvedValue({ data: {} });
    });

    it('muestra el estado de carga al montar', () => {
        render(<UbicacionesInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
    });

    it('renderiza el título "Ubicaciones y disponibilidad avanzada" tras la carga', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Ubicaciones y disponibilidad avanzada')).toBeTruthy();
        });
    });

    it('muestra las 6 tarjetas de resumen de stock', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => {
            const tarjetas = screen.getAllByTestId('stat-card');
            expect(tarjetas.length).toBe(6);
        });
    });

    it('muestra los títulos de las tarjetas: Físico, Disponible, Reservado, Cuarentena, Bloqueado, Tránsito', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Físico')).toBeTruthy();
            expect(screen.getByText('Disponible')).toBeTruthy();
            expect(screen.getByText('Reservado')).toBeTruthy();
            expect(screen.getByText('Cuarentena')).toBeTruthy();
            expect(screen.getByText('Bloqueado')).toBeTruthy();
            expect(screen.getByText('Tránsito')).toBeTruthy();
        });
    });

    it('muestra estado vacío para ubicaciones cuando no hay datos', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Sin ubicaciones')).toBeTruthy();
        });
    });

    it('muestra estado vacío para stock por ubicación cuando no hay datos', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Sin stock por ubicación')).toBeTruthy();
        });
    });

    it('muestra datos de stock cuando la API devuelve stock por ubicación', async () => {
        inventarioApi.stockUbicaciones.listar.mockResolvedValue(stockConDatos);
        render(<UbicacionesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Producto Test')).toBeTruthy();
        });
    });

    it('muestra el formulario de nueva ubicación al hacer clic en "Nueva ubicación"', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => expect(screen.getByText('Nueva ubicación')).toBeTruthy());

        fireEvent.click(screen.getByText('Nueva ubicación'));

        await waitFor(() => {
            expect(screen.getByText('Crear ubicación física')).toBeTruthy();
        });
    });

    it('llama a ubicaciones.crear al enviar el formulario de nueva ubicación', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => expect(screen.getByText('Nueva ubicación')).toBeTruthy());

        fireEvent.click(screen.getByText('Nueva ubicación'));
        await waitFor(() => expect(screen.getByText('Guardar ubicación')).toBeTruthy());

        const inputCodigo = screen.getByPlaceholderText('PAS-A-EST-01');
        const inputNombre = screen.getByPlaceholderText('Pasillo A / Estante 01');
        const selectBodega = screen.getByDisplayValue('Seleccionar...');

        fireEvent.change(selectBodega, { target: { value: '1' } });
        fireEvent.change(inputCodigo, { target: { value: 'PAS-A-01' } });
        fireEvent.change(inputNombre, { target: { value: 'Pasillo A Estante 1' } });

        await act(async () => {
            fireEvent.click(screen.getByText('Guardar ubicación'));
        });

        await waitFor(() => {
            expect(inventarioApi.ubicaciones.crear).toHaveBeenCalledTimes(1);
        });
    });

    it('muestra el formulario de movimiento de stock al hacer clic en "Mover stock"', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => expect(screen.getByText('Mover stock')).toBeTruthy());

        fireEvent.click(screen.getByText('Mover stock'));

        await waitFor(() => {
            expect(screen.getByText('Movimiento interno / putaway')).toBeTruthy();
        });
    });

    it('llama a stockUbicaciones.mover al enviar el formulario de movimiento', async () => {
        render(<UbicacionesInventario />);
        await waitFor(() => expect(screen.getByText('Mover stock')).toBeTruthy());

        fireEvent.click(screen.getByText('Mover stock'));
        await waitFor(() => expect(screen.getByText('Confirmar movimiento')).toBeTruthy());

        // Submiteamos el form directamente para evitar la validación HTML5 de jsdom en campos required
        const botonConfirmar = screen.getByText('Confirmar movimiento');
        const form = botonConfirmar.closest('form');

        await act(async () => {
            fireEvent.submit(form);
        });

        await waitFor(() => {
            expect(inventarioApi.stockUbicaciones.mover).toHaveBeenCalledTimes(1);
        });
    });

    it('filtra ubicaciones por texto de búsqueda', async () => {
        await import('../Hooks/useInventarioData');
        render(<UbicacionesInventario />);
        await waitFor(() => expect(screen.getByText('Filtros')).toBeTruthy());

        const inputBusqueda = screen.getByPlaceholderText('Código, nombre, pasillo, estante...');
        fireEvent.change(inputBusqueda, { target: { value: 'algo-que-no-existe' } });

        await waitFor(() => {
            expect(screen.getByText('Sin ubicaciones')).toBeTruthy();
        });
    });
});
