import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';
import * as jestDomMatchers from '@testing-library/jest-dom/matchers';
expect.extend(jestDomMatchers);

// vi.mock ANTES de imports del módulo bajo test
vi.mock('react-router-dom', () => ({ useNavigate: () => vi.fn() }));
vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn().mockResolvedValue({ success: true, data: [] }),
        post: vi.fn(),
    },
}));
vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: false }) },
}));
vi.mock('../../../Configuracion/logger', () => ({
    logger: { log: vi.fn(), error: vi.fn(), warn: vi.fn() },
}));
vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));
vi.mock('../../../Componentes/EstadoCarga', () => ({
    default: ({ mensajeVacio }) => <div>{mensajeVacio}</div>,
}));
vi.mock('../Componentes/ModalAsiento', () => ({ default: () => null }));
vi.mock('../Componentes/HistorialFiltros', () => ({
    default: (props) => <button onClick={props.onEjecutarBusqueda}>Buscar</button>,
}));
vi.mock('../Componentes/WorkbenchReclasificacion', () => ({
    default: ({ onCancelar }) => <button onClick={onCancelar}>Cancelar Reclasificación</button>,
}));
vi.mock('../Hooks/useFacturasHistorial', () => ({
    useFacturasHistorial: vi.fn(),
    default: vi.fn(),
}));

import { useFacturasHistorial } from '../Hooks/useFacturasHistorial';
import HistorialFacturas from './HistorialFacturas';

afterEach(cleanup);

const hookBase = {
    busqueda: '',
    setBusqueda: vi.fn(),
    filtroNumero: '',
    setFiltroNumero: vi.fn(),
    filtroEstado: '',
    setFiltroEstado: vi.fn(),
    facturas: [],
    loading: false,
    searched: false,
    pagination: { page: 1, limit: 10, total: 0, totalPages: 0 },
    setPagination: vi.fn(),
    sugerencias: [],
    mostrarSugerencias: false,
    setMostrarSugerencias: vi.fn(),
    searchRef: { current: null },
    handleBusquedaChange: vi.fn(),
    ejecutarBusqueda: vi.fn(),
    seleccionarProveedor: vi.fn(),
};

const facturaBase = {
    id: 1,
    tipo_documento: 'FACTURA',
    numero_factura: '123',
    fecha_emision: '2024-01-15',
    proveedor: { razon_social: 'Proveedor SA', rut: '12.345.678-9' },
    monto_bruto: 119000,
    monto_neto: 100000,
    monto_iva: 19000,
    estado: 'REGISTRADA',
};

describe('HistorialFacturas (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        useFacturasHistorial.mockReturnValue(hookBase);
    });

    it('renderiza el título Historial de Compras', () => {
        render(<HistorialFacturas />);
        expect(screen.getByText('Historial de Compras')).toBeInTheDocument();
    });

    it("muestra 'Usa los filtros para buscar documentos.' cuando no se ha buscado y la lista está vacía", () => {
        useFacturasHistorial.mockReturnValue({ ...hookBase, searched: false, facturas: [] });
        render(<HistorialFacturas />);
        expect(screen.getByText('Usa los filtros para buscar documentos.')).toBeInTheDocument();
    });

    it("muestra 'No se encontraron documentos.' cuando se ha buscado y la lista está vacía", () => {
        useFacturasHistorial.mockReturnValue({ ...hookBase, searched: true, facturas: [] });
        render(<HistorialFacturas />);
        expect(screen.getByText('No se encontraron documentos.')).toBeInTheDocument();
    });

    it('renderiza el nombre del proveedor cuando hay una factura en la lista', () => {
        useFacturasHistorial.mockReturnValue({ ...hookBase, searched: true, facturas: [facturaBase] });
        render(<HistorialFacturas />);
        expect(screen.getAllByText('Proveedor SA').length).toBeGreaterThan(0);
    });

    it("muestra badge 'Pendiente' para una factura REGISTRADA que no es nota de crédito", () => {
        useFacturasHistorial.mockReturnValue({ ...hookBase, searched: true, facturas: [facturaBase] });
        render(<HistorialFacturas />);
        expect(screen.getAllByText('Pendiente').length).toBeGreaterThan(0);
    });

    it("muestra 'NC N° 123' en la columna de documento para una nota de crédito", () => {
        const notaCredito = { ...facturaBase, tipo_documento: 'NOTA_CREDITO' };
        useFacturasHistorial.mockReturnValue({ ...hookBase, searched: true, facturas: [notaCredito] });
        render(<HistorialFacturas />);
        expect(screen.getAllByText(/NC N° 123/).length).toBeGreaterThan(0);
    });

    it("muestra badge 'Pagada' para factura con estado PAGADA", () => {
        const facturaPagada = { ...facturaBase, estado: 'PAGADA' };
        useFacturasHistorial.mockReturnValue({ ...hookBase, searched: true, facturas: [facturaPagada] });
        render(<HistorialFacturas />);
        expect(screen.getAllByText('Pagada').length).toBeGreaterThan(0);
    });

    it("muestra badge 'Anulada' para factura con estado ANULADA", () => {
        const facturaAnulada = { ...facturaBase, estado: 'ANULADA' };
        useFacturasHistorial.mockReturnValue({ ...hookBase, searched: true, facturas: [facturaAnulada] });
        render(<HistorialFacturas />);
        expect(screen.getAllByText('Anulada').length).toBeGreaterThan(0);
    });

    it('muestra los botones de pestaña Detallada y Contable', () => {
        render(<HistorialFacturas />);
        expect(screen.getByRole('button', { name: /Detallada/ })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Contable/ })).toBeInTheDocument();
    });

    it('la vista inicial muestra Historial de Compras y no el Workbench de Reclasificación (vistaActual = 1)', () => {
        render(<HistorialFacturas />);
        expect(screen.getByText('Historial de Compras')).toBeInTheDocument();
        expect(screen.queryByText('Cancelar Reclasificación')).not.toBeInTheDocument();
    });
});
