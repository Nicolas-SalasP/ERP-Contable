import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    AlertBox: ({ children, tone }) => <div data-tone={tone}>{children}</div>,
    EmptyState: ({ title, description }) => <div>{title || description || 'Sin datos'}</div>,
    EstadoBadge: ({ value }) => <span data-testid="estado-badge">{value}</span>,
    ErrorNotice: ({ error }) => error ? <div data-testid="error-notice">{error?.message || String(error)}</div> : null,
    Field: ({ label, children }) => <div><label>{label}</label>{children}</div>,
    formatCurrency: (v) => `$${Number(v ?? 0)}`,
    formatDate: (d) => d ? String(d) : '-',
    formatNumber: (v) => String(Number(v ?? 0)),
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
    PrimaryButton: ({ onClick, children, disabled }) => (
        <button onClick={onClick} disabled={disabled}>{children}</button>
    ),
    SecondaryButton: ({ onClick, children, disabled }) => (
        <button onClick={onClick} disabled={disabled}>{children}</button>
    ),
    TableShell: ({ children }) => <table><tbody>{children}</tbody></table>,
    Td: ({ children }) => <td>{children}</td>,
    Th: ({ children }) => <th>{children}</th>,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        reportes: {
            stock: vi.fn(),
            movimientos: vi.fn(),
            valorizacion: vi.fn(),
            lotes: vi.fn(),
            reservas: vi.fn(),
            tomasFisicas: vi.fn(),
            ajustes: vi.fn(),
            reposicionAlertas: vi.fn(),
            exportarCsv: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: () => ({
        productos: [{ id: 1, nombre: 'Producto Test', sku: 'SKU-001' }],
        bodegas: [{ id: 1, nombre: 'Bodega Central', codigo: 'BC' }],
        lotes: [{ id: 1, codigo_lote: 'LOTE-001', producto_id: 1 }],
        cargarProductosCache: vi.fn().mockResolvedValue([]),
        cargarBodegasCache: vi.fn().mockResolvedValue([]),
        cargarLotesCache: vi.fn().mockResolvedValue([]),
        invalidarProductos: vi.fn(),
        invalidarLotes: vi.fn(),
    }),
}));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({
        tienePermiso: vi.fn().mockReturnValue(true),
    }),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import inventarioApi from '../Servicios/inventarioApi';
import ReportesInventario from './ReportesInventario';

const respuestaVacia = { data: [], resumen: {}, metadata: null, message: null };

const respuestaConDatos = {
    data: [
        {
            id: 1,
            producto_nombre: 'Producto Test',
            bodega_nombre: 'Bodega Central',
            stock_actual: 100,
            stock_comprometido: 10,
            stock_disponible: 90,
            valor_total: 500000,
            estado_stock: 'ok',
        },
    ],
    resumen: { total_productos: 1, valor_total: 500000 },
    metadata: { generado_en: '2026-06-28T10:00:00', limit: 100 },
    message: null,
};

afterEach(cleanup);

describe('ReportesInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        inventarioApi.reportes.stock.mockResolvedValue(respuestaVacia);
        inventarioApi.reportes.movimientos.mockResolvedValue(respuestaVacia);
        inventarioApi.reportes.valorizacion.mockResolvedValue(respuestaVacia);
        inventarioApi.reportes.lotes.mockResolvedValue(respuestaVacia);
        inventarioApi.reportes.reservas.mockResolvedValue(respuestaVacia);
        inventarioApi.reportes.tomasFisicas.mockResolvedValue(respuestaVacia);
        inventarioApi.reportes.ajustes.mockResolvedValue(respuestaVacia);
        inventarioApi.reportes.reposicionAlertas.mockResolvedValue(respuestaVacia);
        inventarioApi.reportes.exportarCsv.mockResolvedValue(undefined);
    });

    it('renderiza el título principal "Reportes de Inventario"', async () => {
        render(<ReportesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Reportes de Inventario')).toBeTruthy();
        });
    });

    it('muestra los 8 botones de tipo de reporte', async () => {
        render(<ReportesInventario />);
        await waitFor(() => {
            // Cada etiqueta puede aparecer en el botón de pestaña y en el título del panel activo
            expect(screen.getAllByText('Stock').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('Movimientos').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('Valorización').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('Lotes').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('Reservas').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('Tomas físicas').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('Ajustes').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('Reposición y alertas').length).toBeGreaterThanOrEqual(1);
        });
    });

    it('llama a reportes.stock al montar (reporte por defecto)', async () => {
        render(<ReportesInventario />);
        await waitFor(() => {
            expect(inventarioApi.reportes.stock).toHaveBeenCalledTimes(1);
        });
    });

    it('muestra estado vacío cuando el reporte no devuelve filas', async () => {
        inventarioApi.reportes.stock.mockResolvedValue(respuestaVacia);
        render(<ReportesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Sin datos para mostrar')).toBeTruthy();
        });
    });

    it('muestra datos en la tabla cuando el reporte devuelve resultados', async () => {
        inventarioApi.reportes.stock.mockResolvedValue(respuestaConDatos);
        render(<ReportesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Producto Test')).toBeTruthy();
        });
    });

    it('cambia al reporte de movimientos al hacer clic en la pestaña', async () => {
        render(<ReportesInventario />);
        await waitFor(() => expect(screen.getByText('Movimientos')).toBeTruthy());

        await act(async () => {
            fireEvent.click(screen.getByText('Movimientos'));
        });

        await waitFor(() => {
            expect(inventarioApi.reportes.movimientos).toHaveBeenCalledTimes(1);
        });
    });

    it('muestra el botón "Exportar CSV" cuando el usuario tiene permiso', async () => {
        render(<ReportesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Exportar CSV')).toBeTruthy();
        });
    });

    it('llama a exportarCsv al hacer clic en el botón de exportación', async () => {
        render(<ReportesInventario />);
        await waitFor(() => expect(screen.getByText('Exportar CSV')).toBeTruthy());

        await act(async () => {
            fireEvent.click(screen.getByText('Exportar CSV'));
        });

        await waitFor(() => {
            expect(inventarioApi.reportes.exportarCsv).toHaveBeenCalledTimes(1);
        });
    });

    it('muestra el resumen de KPIs cuando el reporte incluye datos de resumen', async () => {
        inventarioApi.reportes.stock.mockResolvedValue(respuestaConDatos);
        render(<ReportesInventario />);
        await waitFor(() => {
            expect(screen.getByText('Total Productos')).toBeTruthy();
        });
    });

    it('llama nuevamente a la API al enviar el formulario con "Aplicar filtros"', async () => {
        inventarioApi.reportes.stock.mockResolvedValue(respuestaVacia);
        render(<ReportesInventario />);
        await waitFor(() => expect(screen.getByText('Aplicar filtros')).toBeTruthy());

        await act(async () => {
            fireEvent.click(screen.getByText('Aplicar filtros'));
        });

        await waitFor(() => {
            expect(inventarioApi.reportes.stock).toHaveBeenCalledTimes(2);
        });
    });

    it('muestra alerta de metadatos cuando el reporte incluye fecha de generación', async () => {
        inventarioApi.reportes.stock.mockResolvedValue(respuestaConDatos);
        render(<ReportesInventario />);
        await waitFor(() => {
            expect(screen.getByText(/Reporte generado el/)).toBeTruthy();
        });
    });

    it('muestra el selector de bodega en el panel de filtros', async () => {
        render(<ReportesInventario />);
        await waitFor(() => {
            // "Bodega" puede aparecer en label del campo y en opciones del select
            expect(screen.getAllByText('Bodega').length).toBeGreaterThanOrEqual(1);
        });
    });

    it('recarga el reporte al hacer clic en "Recargar"', async () => {
        render(<ReportesInventario />);
        await waitFor(() => expect(screen.getByText('Recargar')).toBeTruthy());

        await act(async () => {
            fireEvent.click(screen.getByText('Recargar'));
        });

        await waitFor(() => {
            expect(inventarioApi.reportes.stock).toHaveBeenCalledTimes(2);
        });
    });
});
