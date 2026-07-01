import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

// ── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        dashboard: {
            obtener: vi.fn(),
        },
    },
}));

vi.mock('../Componentes/InventarioUI', () => ({
    AlertBox: ({ children, tone }) => (
        <div data-testid="alert-box" data-tone={tone}>{children}</div>
    ),
    EmptyState: ({ title, description }) => (
        <div data-testid="empty-state">
            <p>{title}</p>
            {description && <p>{description}</p>}
        </div>
    ),
    EstadoBadge: ({ value }) => (
        <span data-testid="estado-badge">{String(value || '-').replace(/_/g, ' ')}</span>
    ),
    formatCurrency: (v) => `$${Number(v ?? 0)}`,
    formatDate: (d) => d || '-',
    formatNumber: (v) => String(v ?? 0),
    getBodegaNombre: (item) =>
        item?.bodega?.nombre || item?.nombre_bodega || `Bodega #${item?.bodega_id ?? '-'}`,
    getProductoNombre: (item) =>
        item?.producto?.nombre ||
        item?.producto_nombre ||
        `Producto #${item?.producto_id ?? '-'}`,
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
            {actions && <div>{actions}</div>}
            {children}
        </div>
    ),
    SecondaryButton: ({ onClick, children, type }) => (
        <button type={type || 'button'} onClick={onClick}>
            {children}
        </button>
    ),
    StatCard: ({ title, value, helper }) => (
        <div data-testid="stat-card">
            <span>{title}</span>
            <span data-testid={`kpi-val-${title}`}>{value}</span>
            {helper && <span>{helper}</span>}
        </div>
    ),
    TableShell: ({ children }) => <table>{children}</table>,
    Td: ({ children, align }) => <td style={{ textAlign: align }}>{children}</td>,
    Th: ({ children, align }) => <th style={{ textAlign: align }}>{children}</th>,
}));

// ── Imports bajo test ──────────────────────────────────────────────────────

import inventarioApi from '../Servicios/inventarioApi';
import InventarioDashboard from './InventarioDashboard';

// ── Fixtures ───────────────────────────────────────────────────────────────

const DASHBOARD_VACIO = {
    resumen: {
        productos: 0,
        productos_activos: 0,
        bodegas: 0,
        stock_total: 0,
        stock_valorizado: 0,
        valor_total_inventario: 0,
        productos_bajo_minimo: 0,
        productos_sin_stock: 0,
        productos_sin_movimiento: 0,
        lotes_vencidos: 0,
        lotes_por_vencer: 0,
        reservas_activas: 0,
        tomas_abiertas: 0,
        tomas_pendientes: 0,
        alertas_criticas: 0,
        alertas_total: 0,
        exactitud_toma_fisica: 0,
        rotacion_simple: 0,
    },
    kpis: {},
    stock_por_bodega: [],
    stock_por_lote: [],
    alertas_criticas: [],
    sugerencias_reposicion: [],
    ultimos_movimientos: [],
    ajustes_criticos_recientes: [],
    tomas_recientes: [],
    metadata: null,
};

const DASHBOARD_CON_DATOS = {
    ...DASHBOARD_VACIO,
    resumen: {
        ...DASHBOARD_VACIO.resumen,
        productos: 15,
        productos_activos: 12,
        bodegas: 3,
        stock_total: 450,
        valor_total_inventario: 2500000,
        productos_bajo_minimo: 2,
        alertas_criticas: 1,
        alertas_total: 5,
    },
    stock_por_bodega: [
        { bodega_id: 1, bodega_nombre: 'Bodega Central', stock_total: 200, valor_total: 1000000 },
    ],
    alertas_criticas: [
        {
            tipo: 'STOCK_BAJO',
            severidad: 'critica',
            titulo: 'Stock crítico en Tornillo',
            descripcion: 'Cantidad cero.',
            producto_nombre: 'Tornillo Acero',
            bodega_nombre: 'Bodega Central',
            referencia: 'REF-001',
        },
    ],
    ultimos_movimientos: [
        {
            id: 1,
            tipo: 'entrada',
            producto_nombre: 'Tornillo Acero',
            bodega_destino_id: 1,
            cantidad: 100,
            fecha_movimiento: '2026-06-01',
        },
    ],
    tomas_recientes: [
        {
            id: 1,
            codigo_toma: 'TF-2026-001',
            tipo: 'total',
            estado: 'BORRADOR',
            detalles_count: 10,
            detalles_con_diferencia_count: 2,
        },
    ],
};

// ── Helpers ────────────────────────────────────────────────────────────────

const esperarCarga = () =>
    waitFor(() => expect(screen.queryByTestId('loading-state')).toBeNull());

afterEach(cleanup);

// ── Tests ──────────────────────────────────────────────────────────────────

describe('InventarioDashboard (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        inventarioApi.dashboard.obtener.mockResolvedValue({ data: DASHBOARD_VACIO });
    });

    it('muestra el estado de carga inicial antes de que la API responda', () => {
        inventarioApi.dashboard.obtener.mockImplementation(() => new Promise(() => {}));
        render(<InventarioDashboard />);
        expect(screen.getByTestId('loading-state')).toBeDefined();
        expect(screen.getByText(/Cargando dashboard/i)).toBeDefined();
    });

    it('renderiza el título "Dashboard de Inventario" tras cargar', async () => {
        render(<InventarioDashboard />);
        await esperarCarga();
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Dashboard de Inventario',
        );
    });

    it('muestra estado vacío cuando no hay datos operativos en el dashboard', async () => {
        inventarioApi.dashboard.obtener.mockResolvedValue({ data: DASHBOARD_VACIO });
        render(<InventarioDashboard />);
        await esperarCarga();
        // Varios paneles muestran EmptyState simultáneamente; verificar por el texto principal
        expect(screen.getByText('Dashboard sin datos operativos')).toBeDefined();
    });

    it('renderiza las stat cards de KPIs principales con datos reales', async () => {
        inventarioApi.dashboard.obtener.mockResolvedValue({ data: DASHBOARD_CON_DATOS });
        render(<InventarioDashboard />);
        await esperarCarga();
        const cards = screen.getAllByTestId('stat-card');
        expect(cards.length).toBeGreaterThanOrEqual(4);
    });

    it('muestra el botón "Actualizar" en el encabezado de la página', async () => {
        render(<InventarioDashboard />);
        await esperarCarga();
        expect(screen.getByRole('button', { name: /Actualizar/i })).toBeDefined();
    });

    it('vuelve a llamar a dashboard.obtener al clickear "Actualizar"', async () => {
        render(<InventarioDashboard />);
        await esperarCarga();

        fireEvent.click(screen.getByRole('button', { name: /Actualizar/i }));

        await waitFor(() => {
            expect(inventarioApi.dashboard.obtener).toHaveBeenCalledTimes(2);
        });
    });

    it('muestra el panel "Stock por bodega" con filas cuando hay stock', async () => {
        inventarioApi.dashboard.obtener.mockResolvedValue({ data: DASHBOARD_CON_DATOS });
        render(<InventarioDashboard />);
        await esperarCarga();

        expect(screen.getByText('Stock por bodega')).toBeDefined();
        expect(screen.getByText('Bodega Central')).toBeDefined();
    });

    it('muestra alerta crítica en el panel "Alertas críticas" cuando hay datos', async () => {
        inventarioApi.dashboard.obtener.mockResolvedValue({ data: DASHBOARD_CON_DATOS });
        render(<InventarioDashboard />);
        await esperarCarga();

        // "Alertas críticas" aparece como StatCard title y como Panel title; verificar ambos existen
        expect(screen.getAllByText('Alertas críticas').length).toBeGreaterThanOrEqual(2);
        // El título de la alerta del fixture es único en el DOM
        expect(screen.getByText('Stock crítico en Tornillo')).toBeDefined();
    });

    it('muestra el panel de tomas físicas con el código de la toma reciente', async () => {
        inventarioApi.dashboard.obtener.mockResolvedValue({ data: DASHBOARD_CON_DATOS });
        render(<InventarioDashboard />);
        await esperarCarga();

        expect(screen.getByText('Tomas físicas recientes')).toBeDefined();
        expect(screen.getByText('TF-2026-001')).toBeDefined();
    });
});
