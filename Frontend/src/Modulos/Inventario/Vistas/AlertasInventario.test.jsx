import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

// ── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

const permisosMock = vi.hoisted(() => ({
    tienePermiso: vi.fn().mockReturnValue(true),
}));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => permisosMock,
}));

const contextMock = vi.hoisted(() => ({
    productos: [{ id: 1, nombre: 'Producto Test', sku: 'SKU-001' }],
    bodegas: [{ id: 1, nombre: 'Bodega Central' }],
    lotes: [],
    cargarProductosCache: vi.fn().mockResolvedValue([]),
    cargarBodegasCache: vi.fn().mockResolvedValue([]),
    invalidarProductos: vi.fn(),
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: () => contextMock,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        alertas: {
            listar: vi.fn().mockResolvedValue({ data: [], resumen: null }),
        },
        reposicion: {
            sugerencias: vi.fn().mockResolvedValue({ data: [] }),
        },
        reglasReposicion: {
            listar: vi.fn().mockResolvedValue({ data: [] }),
            crear: vi.fn().mockResolvedValue({ success: true }),
            actualizar: vi.fn().mockResolvedValue({ success: true }),
            eliminar: vi.fn().mockResolvedValue({ success: true }),
        },
    },
}));

vi.mock('../Componentes/InventarioUI', () => ({
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
    formatDate: (d) => d || '-',
    formatNumber: (v) => String(v ?? 0),
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
    StatCard: ({ title, value, helper }) => (
        <div data-testid="stat-card">
            <span>{title}</span>
            <span>{value}</span>
            {helper && <span>{helper}</span>}
        </div>
    ),
    TableShell: ({ children }) => <table>{children}</table>,
    Td: ({ children, align }) => <td style={{ textAlign: align }}>{children}</td>,
    Th: ({ children, align }) => <th style={{ textAlign: align }}>{children}</th>,
}));

// ── Imports bajo test ──────────────────────────────────────────────────────

import inventarioApi from '../Servicios/inventarioApi';
import AlertasInventario from './AlertasInventario';

// ── Fixtures ───────────────────────────────────────────────────────────────

const ALERTA_MOCK = {
    tipo: 'STOCK_BAJO',
    severidad: 'alta',
    titulo: 'Stock bajo en Tornillo Acero',
    descripcion: 'Cantidad actual por debajo del mínimo configurado.',
    producto_nombre: 'Tornillo Acero',
    bodega_nombre: 'Bodega Central',
    cantidad_actual: 2,
    cantidad_sugerida: 50,
    fecha_referencia: '2026-06-01',
    referencia: 'REF-001',
};

const REGLA_MOCK = {
    id: 7,
    producto_id: 1,
    producto: { nombre: 'Tornillo Acero', sku: 'SKU-001' },
    bodega_id: null,
    bodega: null,
    stock_minimo: 10,
    stock_objetivo: 100,
    punto_reorden: 20,
    dias_alerta_vencimiento: 30,
    activo: true,
};

// ── Helpers ────────────────────────────────────────────────────────────────

const esperarCarga = () =>
    waitFor(() => expect(screen.queryByTestId('loading-state')).toBeNull());

afterEach(cleanup);

// ── Tests ──────────────────────────────────────────────────────────────────

describe('AlertasInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        permisosMock.tienePermiso.mockReturnValue(true);
        inventarioApi.alertas.listar.mockResolvedValue({ data: [], resumen: null });
        inventarioApi.reposicion.sugerencias.mockResolvedValue({ data: [] });
        inventarioApi.reglasReposicion.listar.mockResolvedValue({ data: [] });
        inventarioApi.reglasReposicion.crear.mockResolvedValue({ success: true });
        inventarioApi.reglasReposicion.eliminar.mockResolvedValue({ success: true });
    });

    it('muestra el estado de carga inicial antes de que las APIs respondan', () => {
        inventarioApi.alertas.listar.mockImplementation(() => new Promise(() => {}));
        render(<AlertasInventario />);
        expect(screen.getByTestId('loading-state')).toBeDefined();
        expect(screen.getByText(/Cargando alertas/i)).toBeDefined();
    });

    it('renderiza el título "Alertas y Reposición" tras cargar', async () => {
        render(<AlertasInventario />);
        await esperarCarga();
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Alertas y Reposición');
    });

    it('muestra estado vacío de alertas cuando la API retorna lista vacía', async () => {
        inventarioApi.alertas.listar.mockResolvedValue({ data: [], resumen: null });
        render(<AlertasInventario />);
        await esperarCarga();
        expect(screen.getByText('Sin alertas')).toBeDefined();
    });

    it('renderiza filas de alertas cuando la API retorna datos', async () => {
        inventarioApi.alertas.listar.mockResolvedValue({ data: [ALERTA_MOCK], resumen: null });
        render(<AlertasInventario />);
        await esperarCarga();
        expect(screen.getByText('Stock bajo en Tornillo Acero')).toBeDefined();
        expect(screen.getByText('Tornillo Acero')).toBeDefined();
    });

    it('muestra las stat cards de métricas: Alertas, Críticas, Altas', async () => {
        render(<AlertasInventario />);
        await esperarCarga();
        const cards = screen.getAllByTestId('stat-card');
        const textos = cards.map((c) => c.textContent);
        expect(textos.some((t) => /Alertas/.test(t))).toBe(true);
        expect(textos.some((t) => /Críticas/.test(t))).toBe(true);
        expect(textos.some((t) => /Altas/.test(t))).toBe(true);
    });

    it('muestra el botón "Nueva regla" cuando el usuario tiene permiso de crear', async () => {
        permisosMock.tienePermiso.mockReturnValue(true);
        render(<AlertasInventario />);
        await esperarCarga();
        expect(screen.getByText('Nueva regla')).toBeDefined();
    });

    it('oculta el botón "Nueva regla" cuando el usuario no tiene el permiso', async () => {
        permisosMock.tienePermiso.mockReturnValue(false);
        render(<AlertasInventario />);
        await esperarCarga();
        expect(screen.queryByText('Nueva regla')).toBeNull();
    });

    it('abre el formulario de nueva regla al clickear el botón (con permisos)', async () => {
        permisosMock.tienePermiso.mockReturnValue(true);
        render(<AlertasInventario />);
        await esperarCarga();

        fireEvent.click(screen.getByText('Nueva regla'));

        await waitFor(() => {
            expect(screen.getByText(/nueva regla de reposición/i)).toBeDefined();
        });
        expect(screen.getByText('Stock mínimo')).toBeDefined();
        expect(screen.getByText('Stock objetivo')).toBeDefined();
        expect(screen.getByText('Punto de reorden')).toBeDefined();
    });

    it('llama a reglasReposicion.eliminar cuando el usuario confirma la acción', async () => {
        permisosMock.tienePermiso.mockReturnValue(true);
        inventarioApi.reglasReposicion.listar.mockResolvedValue({ data: [REGLA_MOCK] });

        render(<AlertasInventario />);
        await esperarCarga();

        const btnEliminar = screen.getByRole('button', { name: /Eliminar/i });
        fireEvent.click(btnEliminar);

        await waitFor(() => {
            expect(inventarioApi.reglasReposicion.eliminar).toHaveBeenCalledWith(REGLA_MOCK.id);
        });
    });
});
