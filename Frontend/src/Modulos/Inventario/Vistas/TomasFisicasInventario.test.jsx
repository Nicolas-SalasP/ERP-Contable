import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    AlertBox: ({ children, tone }) => <div data-tone={tone}>{children}</div>,
    EmptyState: ({ title, description }) => <div>{title || description || 'Sin datos'}</div>,
    EstadoBadge: ({ value }) => <span data-testid="estado-badge">{value}</span>,
    ErrorNotice: ({ error }) => error ? <div data-testid="error-notice">{error?.message || String(error)}</div> : null,
    Field: ({ label, children }) => <div><label>{label}</label>{children}</div>,
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
    Panel: ({ children, title, subtitle, actions }) => (
        <div>
            <h2>{title}</h2>
            {subtitle && <p>{subtitle}</p>}
            {actions && <div>{actions}</div>}
            {children}
        </div>
    ),
    PrimaryButton: ({ onClick, children, disabled }) => (
        <button onClick={onClick} disabled={disabled}>{children}</button>
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
        tomasFisicas: {
            listar: vi.fn(),
            crear: vi.fn(),
            obtener: vi.fn(),
            iniciar: vi.fn(),
            cerrar: vi.fn(),
            cancelar: vi.fn(),
            registrarConteos: vi.fn(),
            ajustar: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: () => ({
        bodegas: [
            { id: 1, nombre: 'Bodega Central', codigo: 'BC' },
            { id: 2, nombre: 'Bodega Norte', codigo: 'BN' },
        ],
        cargarBodegasCache: vi.fn().mockResolvedValue([]),
        invalidarProductos: vi.fn(),
        invalidarLotes: vi.fn(),
    }),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import inventarioApi from '../Servicios/inventarioApi';
import Swal from 'sweetalert2';
import TomasFisicasInventario from './TomasFisicasInventario';

const tomasVacio = { data: [] };

const tomasConDatos = {
    data: [
        {
            id: 1,
            codigo_toma: 'TF-001',
            tipo: 'BODEGA',
            estado: 'BORRADOR',
            bodega_id: 1,
            referencia: 'REF-001',
            motivo: 'inventario_ciclico',
        },
        {
            id: 2,
            codigo_toma: 'TF-002',
            tipo: 'GENERAL',
            estado: 'EN_CONTEO',
            bodega_id: null,
            referencia: null,
            motivo: 'auditoria',
        },
        {
            id: 3,
            codigo_toma: 'TF-003',
            tipo: 'BODEGA',
            estado: 'CERRADA',
            bodega_id: 2,
            referencia: 'REF-003',
            motivo: 'cierre_anual',
        },
    ],
};

afterEach(cleanup);

describe('TomasFisicasInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasVacio);
        inventarioApi.tomasFisicas.crear.mockResolvedValue({ data: {} });
        inventarioApi.tomasFisicas.iniciar.mockResolvedValue({ data: {} });
        inventarioApi.tomasFisicas.cerrar.mockResolvedValue({ data: {} });
        inventarioApi.tomasFisicas.cancelar.mockResolvedValue({ data: {} });
        inventarioApi.tomasFisicas.ajustar.mockResolvedValue({ data: {} });
        inventarioApi.tomasFisicas.registrarConteos.mockResolvedValue({ data: {} });
        inventarioApi.tomasFisicas.obtener.mockResolvedValue({ data: { id: 1, estado: 'BORRADOR', detalles: [] } });
    });

    it('muestra el estado de carga al montar', () => {
        render(<TomasFisicasInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText('Cargando tomas físicas...')).toBeTruthy();
    });

    it('renderiza el título "Tomas Físicas" tras la carga', async () => {
        render(<TomasFisicasInventario />);
        await waitFor(() => {
            expect(screen.getByText('Tomas Físicas')).toBeTruthy();
        });
    });

    it('muestra el estado vacío cuando no hay tomas físicas', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasVacio);
        render(<TomasFisicasInventario />);
        await waitFor(() => {
            expect(screen.getByText('Sin tomas físicas')).toBeTruthy();
        });
    });

    it('muestra la lista de tomas cuando la API devuelve datos', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasConDatos);
        render(<TomasFisicasInventario />);
        await waitFor(() => {
            expect(screen.getByText('TF-001')).toBeTruthy();
            expect(screen.getByText('TF-002')).toBeTruthy();
            expect(screen.getByText('TF-003')).toBeTruthy();
        });
    });

    it('muestra el formulario de creación al hacer clic en "Nueva toma física"', async () => {
        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getByText('Nueva toma física')).toBeTruthy());

        fireEvent.click(screen.getByText('Nueva toma física'));

        await waitFor(() => {
            expect(screen.getByText('Crear toma física')).toBeTruthy();
        });
    });

    it('cierra el formulario al hacer clic en "Cerrar formulario"', async () => {
        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getByText('Nueva toma física')).toBeTruthy());

        fireEvent.click(screen.getByText('Nueva toma física'));
        await waitFor(() => expect(screen.getByText('Cerrar formulario')).toBeTruthy());

        fireEvent.click(screen.getByText('Cerrar formulario'));
        await waitFor(() => {
            expect(screen.queryByText('Crear toma física')).toBeNull();
        });
    });

    it('llama a tomasFisicas.crear al enviar el formulario con tipo GENERAL', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasVacio);
        Swal.fire.mockResolvedValue({ isConfirmed: true });

        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getByText('Nueva toma física')).toBeTruthy());

        fireEvent.click(screen.getByText('Nueva toma física'));
        await waitFor(() => expect(screen.getByText('Crear toma')).toBeTruthy());

        // Cambiar tipo a GENERAL para no requerir bodega
        const selectTipo = screen.getByDisplayValue('BODEGA');
        fireEvent.change(selectTipo, { target: { value: 'GENERAL' } });

        await act(async () => {
            fireEvent.click(screen.getByText('Crear toma'));
        });

        await waitFor(() => {
            expect(inventarioApi.tomasFisicas.crear).toHaveBeenCalledTimes(1);
        });
    });

    it('el botón "Iniciar" está deshabilitado si la toma no está en BORRADOR', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasConDatos);
        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getAllByText('Iniciar').length).toBeGreaterThan(0));

        const botonesIniciar = screen.getAllByText('Iniciar');
        // TF-002 está en EN_CONTEO y TF-003 en CERRADA — sus botones deben estar deshabilitados
        const deshabilitados = botonesIniciar.filter((btn) => btn.disabled);
        expect(deshabilitados.length).toBeGreaterThan(0);
    });

    it('el botón "Cerrar" está habilitado solo para tomas en EN_CONTEO', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasConDatos);
        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getAllByText('Cerrar').length).toBeGreaterThan(0));

        const botonesCerrar = screen.getAllByText('Cerrar');
        const habilitados = botonesCerrar.filter((btn) => !btn.disabled);
        // Solo TF-002 (EN_CONTEO) debe tener el botón Cerrar habilitado
        expect(habilitados.length).toBe(1);
    });

    it('el botón "Ajustar" está habilitado solo para tomas en CERRADA', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasConDatos);
        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getAllByText('Ajustar').length).toBeGreaterThan(0));

        const botonesAjustar = screen.getAllByText('Ajustar');
        const habilitados = botonesAjustar.filter((btn) => !btn.disabled);
        // Solo TF-003 (CERRADA) debe tener el botón Ajustar habilitado
        expect(habilitados.length).toBe(1);
    });

    it('llama a tomasFisicas.iniciar al confirmar la acción en una toma BORRADOR', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasConDatos);
        Swal.fire.mockResolvedValue({ isConfirmed: true });

        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getAllByText('Iniciar').length).toBeGreaterThan(0));

        const botonesIniciar = screen.getAllByText('Iniciar');
        const habilitado = botonesIniciar.find((btn) => !btn.disabled);

        await act(async () => {
            fireEvent.click(habilitado);
        });

        await waitFor(() => {
            expect(inventarioApi.tomasFisicas.iniciar).toHaveBeenCalledWith(1);
        });
    });

    it('filtra tomas por estado usando el selector de filtro', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasConDatos);
        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getByText('TF-001')).toBeTruthy());

        const selectEstado = screen.getByDisplayValue('Todos los estados');
        fireEvent.change(selectEstado, { target: { value: 'BORRADOR' } });

        await waitFor(() => {
            expect(screen.getByText('TF-001')).toBeTruthy();
            expect(screen.queryByText('TF-002')).toBeNull();
            expect(screen.queryByText('TF-003')).toBeNull();
        });
    });

    it('muestra el detalle de la toma al hacer clic en "Ver"', async () => {
        inventarioApi.tomasFisicas.listar.mockResolvedValue(tomasConDatos);
        inventarioApi.tomasFisicas.obtener.mockResolvedValue({
            data: {
                id: 1,
                codigo_toma: 'TF-001',
                estado: 'BORRADOR',
                tipo: 'BODEGA',
                referencia: 'REF-001',
                detalles: [],
            },
        });

        render(<TomasFisicasInventario />);
        await waitFor(() => expect(screen.getAllByText('Ver').length).toBeGreaterThan(0));

        await act(async () => {
            fireEvent.click(screen.getAllByText('Ver')[0]);
        });

        await waitFor(() => {
            expect(inventarioApi.tomasFisicas.obtener).toHaveBeenCalledWith(1);
        });
    });
});
