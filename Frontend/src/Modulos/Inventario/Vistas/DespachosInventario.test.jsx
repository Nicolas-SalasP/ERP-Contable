import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

vi.mock('../Componentes/InventarioUI', () => ({
    AlertBox: ({ children }) => <div data-testid="alert-box">{children}</div>,
    EmptyState: ({ title }) => <div>{title || 'Sin datos'}</div>,
    ErrorNotice: ({ error }) => (error ? <div data-testid="error-notice">Error</div> : null),
    EstadoBadge: ({ value }) => <span>{value}</span>,
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
    SecondaryButton: ({ onClick, children, disabled, type }) => (
        <button onClick={onClick} disabled={disabled} type={type || 'button'}>{children}</button>
    ),
    StatCard: ({ title, value }) => <div data-testid="stat-card"><span>{title}</span><span>{value}</span></div>,
    TableShell: ({ children }) => <table>{children}</table>,
    Td: ({ children }) => <td>{children}</td>,
    Th: ({ children }) => <th>{children}</th>,
}));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        despachos: {
            listar: vi.fn(),
            crear: vi.fn(),
            iniciar: vi.fn(),
            confirmar: vi.fn(),
            cancelar: vi.fn(),
        },
        packing: {
            listar: vi.fn(),
        },
    },
}));

vi.mock('../Hooks/useInventarioData', () => ({
    useInventarioData: vi.fn(),
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import DespachosInventario from './DespachosInventario';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';

const mockInvalidarTodo = vi.fn();
const hookBase = { invalidarTodoInventario: mockInvalidarTodo };

afterEach(cleanup);

describe('DespachosInventario (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(inventarioApi.despachos.listar).mockResolvedValue({ data: [] });
        vi.mocked(inventarioApi.packing.listar).mockResolvedValue({ data: [] });
        vi.mocked(useInventarioData).mockReturnValue({ ...hookBase });
    });

    it('muestra el estado de carga mientras se obtienen los datos', () => {
        vi.mocked(inventarioApi.despachos.listar).mockReturnValue(new Promise(() => {}));
        vi.mocked(inventarioApi.packing.listar).mockReturnValue(new Promise(() => {}));
        render(<DespachosInventario />);
        expect(screen.getByTestId('loading-state')).toBeTruthy();
        expect(screen.getByText(/cargando despachos internos/i)).toBeTruthy();
    });

    it('renderiza el título "Despacho interno" tras cargar', async () => {
        render(<DespachosInventario />);
        await waitFor(() => expect(screen.getByText('Despacho interno')).toBeTruthy());
    });

    it('llama a despachos.listar y packing.listar al montar el componente', async () => {
        render(<DespachosInventario />);
        await waitFor(() => {
            expect(vi.mocked(inventarioApi.despachos.listar)).toHaveBeenCalledWith({ per_page: 100 });
            expect(vi.mocked(inventarioApi.packing.listar)).toHaveBeenCalledWith({ per_page: 100 });
        });
    });

    it('muestra el estado vacío cuando no existen despachos', async () => {
        render(<DespachosInventario />);
        await waitFor(() => expect(screen.getByText('Sin despachos')).toBeTruthy());
    });

    it('muestra despachos en tabla cuando existen registros', async () => {
        vi.mocked(inventarioApi.despachos.listar).mockResolvedValue({
            data: [
                { id: 1, codigo: 'DESP-001', estado: 'PENDIENTE', detalles: [], packing_orden_id: 1 },
                { id: 2, codigo: 'DESP-002', estado: 'EN_DESPACHO', detalles: [], packing_orden_id: 2 },
            ],
        });
        render(<DespachosInventario />);
        await waitFor(() => {
            expect(screen.getByText('DESP-001')).toBeTruthy();
            expect(screen.getByText('DESP-002')).toBeTruthy();
        });
    });

    it('abre el formulario al hacer clic en "Generar despacho"', async () => {
        render(<DespachosInventario />);
        await waitFor(() => screen.getByText('Generar despacho'));
        fireEvent.click(screen.getByText('Generar despacho'));
        await waitFor(() =>
            expect(screen.getByText('Generar despacho desde packing empacado')).toBeTruthy(),
        );
    });

    it('el botón "Crear despacho" está deshabilitado cuando no hay packing disponible', async () => {
        render(<DespachosInventario />);
        await waitFor(() => screen.getByText('Generar despacho'));
        fireEvent.click(screen.getByText('Generar despacho'));
        await waitFor(() => {
            const btn = screen.getByText('Crear despacho');
            expect(btn.disabled).toBe(true);
        });
    });

    it('muestra las tarjetas de resumen tras cargar', async () => {
        render(<DespachosInventario />);
        await waitFor(() => {
            const tarjetas = screen.getAllByTestId('stat-card');
            expect(tarjetas.length).toBeGreaterThanOrEqual(4);
        });
    });
});
