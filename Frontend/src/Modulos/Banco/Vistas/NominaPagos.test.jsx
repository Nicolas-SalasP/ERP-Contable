import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

const mockSwal = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: false }),
    showLoading: vi.fn(),
    close: vi.fn(),
}));
vi.mock('sweetalert2', () => ({ default: mockSwal }));

vi.mock('../../../Componentes/AyudaModulo', () => ({ default: () => null }));
vi.mock('../../../Componentes/EstadoCarga', () => ({ default: () => null }));
vi.mock('../../../Componentes/Skeleton', () => ({ TablaSkeleton: () => null }));
vi.mock('../../../Componentes/EstadoVacio', () => ({ EstadoVacio: () => null }));

vi.mock('@e965/xlsx', () => ({
    utils: {
        json_to_sheet: vi.fn(),
        book_new: vi.fn(() => ({})),
        book_append_sheet: vi.fn(),
    },
    writeFile: vi.fn(),
}));

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn(), post: vi.fn() },
}));

import { api } from '../../../Configuracion/api';
import NominaPagos from './NominaPagos';

// Factura con vencimiento pasado → diasRestantes << 0 → pasa el filtro (<= 7)
const FACTURA_VENCIDA = {
    id: 1,
    numero_factura: 'F-001',
    monto_bruto: 150000,
    fecha_emision: '2024-01-01',
    fecha_vencimiento: '2024-01-15',
    proveedor_id: 10,
    proveedor: {
        razon_social: 'Proveedor Test SA',
        rut: '12345678-9',
        email_contacto: 'contacto@proveedor.cl',
    },
};

const CUENTA_BANCARIA = { id: 1, banco: 'Banco Chile', numero_cuenta: '000-111-222' };

function setupApi({ facturas = [], cuentas = [] } = {}) {
    api.get.mockImplementation((url) => {
        if (url.includes('/facturas/historial')) {
            return Promise.resolve({ success: true, data: facturas });
        }
        if (url === '/banco/cuentas') {
            return Promise.resolve(cuentas);
        }
        return Promise.resolve({ data: [] });
    });
}

afterEach(cleanup);

describe('NominaPagos (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockSwal.fire.mockResolvedValue({ isConfirmed: false });
    });

    it('renderiza el título Nómina de Pagos', async () => {
        setupApi();
        render(<NominaPagos />);
        await waitFor(() => {
            expect(screen.getByText(/Nómina de Pagos/i)).toBeTruthy();
        });
    });

    it('llama a api.get para cargar facturas y cuentas bancarias al montar', async () => {
        setupApi();
        render(<NominaPagos />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/facturas/historial'));
            expect(api.get).toHaveBeenCalledWith('/banco/cuentas');
        });
    });

    it('muestra el nombre del proveedor en la tabla cuando hay facturas vencidas', async () => {
        setupApi({ facturas: [FACTURA_VENCIDA], cuentas: [CUENTA_BANCARIA] });
        render(<NominaPagos />);
        await waitFor(() => {
            expect(screen.getByText('Proveedor Test SA')).toBeTruthy();
        });
    });

    it('muestra columnas de la tabla: Proveedor, Documento, Total a Pagar', async () => {
        setupApi({ facturas: [FACTURA_VENCIDA] });
        render(<NominaPagos />);
        await waitFor(() => {
            // Hay múltiples elementos con "Proveedor", se verifica al menos uno con getAllByText
            expect(screen.getAllByText(/Proveedor/i).length).toBeGreaterThan(0);
            expect(screen.getByText('Documento')).toBeTruthy();
            expect(screen.getByText('Total a Pagar')).toBeTruthy();
        });
    });

    it('muestra los pasos del flujo: Seleccionar Facturas y Resumen y Banco', async () => {
        setupApi();
        render(<NominaPagos />);
        await waitFor(() => {
            expect(screen.getByText(/Seleccionar Facturas/i)).toBeTruthy();
            expect(screen.getByText(/Resumen y Banco/i)).toBeTruthy();
        });
    });

    it('muestra "Seleccionar Todas (0)" cuando no hay facturas por vencer en los próximos 7 días', async () => {
        // Vencimiento muy lejano → diasRestantes >> 7 → no pasa el filtro
        const facturaFutura = {
            ...FACTURA_VENCIDA,
            id: 99,
            fecha_vencimiento: '2030-12-31',
        };
        setupApi({ facturas: [facturaFutura] });
        render(<NominaPagos />);
        await waitFor(() => {
            expect(screen.getByText(/Seleccionar Todas \(0\)/i)).toBeTruthy();
        });
    });

    it('llama a Swal.fire con "Error" cuando la api falla al cargar datos', async () => {
        api.get.mockRejectedValue(new Error('Sin conexión'));
        render(<NominaPagos />);
        await waitFor(() => {
            expect(mockSwal.fire).toHaveBeenCalledWith('Error', expect.any(String), 'error');
        });
    });

    it('muestra el badge de módulo Tesorería y Finanzas', async () => {
        setupApi();
        render(<NominaPagos />);
        await waitFor(() => {
            expect(screen.getByText(/Tesorería y Finanzas/i)).toBeTruthy();
        });
    });

    it('seleccionar una factura activa el checkbox y acumula el total seleccionado', async () => {
        setupApi({ facturas: [FACTURA_VENCIDA], cuentas: [CUENTA_BANCARIA] });
        render(<NominaPagos />);
        await waitFor(() => {
            expect(screen.getByText('Proveedor Test SA')).toBeTruthy();
        });
        // Hacer click sobre la fila para seleccionar la factura
        const fila = screen.getByText('Proveedor Test SA').closest('tr');
        await act(async () => { fireEvent.click(fila); });
        // Tras la selección, el total seleccionado debe mostrarse en el encabezado
        await waitFor(() => {
            expect(screen.getByText(/Total Seleccionado/i)).toBeTruthy();
        });
    });
});
