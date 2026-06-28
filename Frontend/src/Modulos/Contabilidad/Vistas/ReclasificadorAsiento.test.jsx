import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock('sweetalert2', () => ({
    default: {
        fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
    },
}));

vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), log: vi.fn() },
}));

vi.mock('react-router-dom', () => ({
    useNavigate: () => vi.fn(),
    useParams: () => ({ id: '7' }),
}));

vi.mock('../Componentes/BuscadorCuentaContable', () => ({
    default: ({ cuentas, valor, onChange }) => (
        <select
            data-testid="buscador-cuenta"
            value={valor}
            onChange={(e) => onChange(e.target.value)}
        >
            <option value="">-- Seleccionar --</option>
            {cuentas.map((c) => (
                <option key={c.codigo} value={c.codigo}>
                    {c.nombre}
                </option>
            ))}
        </select>
    ),
}));

import ReclasificadorAsiento from './ReclasificadorAsiento';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';

afterEach(cleanup);

const asientoMock = {
    cabecera: {
        id: 7,
        numero_comprobante: 'C-007',
        fecha: '2024-03-10',
        glosa: 'Compra insumos de oficina',
        estado: 'CONCILIADO',
        tipo_asiento: 'COMPRA',
    },
    detalles: [
        {
            id: 101,
            cuenta_contable: '6101',
            cuenta_nombre: 'Gastos Generales',
            debe: 84034,
            haber: 0,
        },
        {
            id: 102,
            cuenta_contable: '110401',
            cuenta_nombre: 'IVA Crédito Fiscal',
            debe: 15966,
            haber: 0,
        },
        {
            id: 103,
            cuenta_contable: '210101',
            cuenta_nombre: 'Proveedores',
            debe: 0,
            haber: 100000,
        },
    ],
};

const cuentasPlanMock = [
    { codigo: '6101', nombre: 'Gastos Generales', imputable: true },
    { codigo: '6201', nombre: 'Gastos Administrativos', imputable: true },
    { codigo: '1101', nombre: 'Caja', imputable: true },
];

describe('ReclasificadorAsiento (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.get.mockImplementation((url) => {
            if (url.includes('/asiento')) {
                return Promise.resolve({ success: true, data: asientoMock });
            }
            if (url.includes('/plan-cuentas')) {
                return Promise.resolve({ success: true, data: cuentasPlanMock });
            }
            return Promise.resolve({ success: false });
        });
    });

    it('muestra estado de carga mientras se obtienen los datos', async () => {
        let resolverAsiento;
        api.get.mockImplementation((url) => {
            if (url.includes('/asiento')) {
                return new Promise((r) => { resolverAsiento = r; });
            }
            return Promise.resolve({ success: true, data: cuentasPlanMock });
        });

        render(<ReclasificadorAsiento />);

        expect(screen.getByText(/Preparando Reclasificación/)).toBeDefined();

        await act(async () => {
            resolverAsiento({ success: true, data: asientoMock });
        });
    });

    it('renderiza el Panel de Reclasificaciones tras cargar los datos', async () => {
        render(<ReclasificadorAsiento />);

        await waitFor(() => {
            expect(screen.getByText('Panel de Reclasificaciones')).toBeDefined();
        });
    });

    it('muestra el número de comprobante y el id de la factura origen', async () => {
        render(<ReclasificadorAsiento />);

        await waitFor(() => {
            expect(screen.getByText(/Asiento #C-007/)).toBeDefined();
            expect(screen.getByText(/Factura Origen ID 7/)).toBeDefined();
        });
    });

    it('pre-rellena la glosa de auditoría con el número de comprobante', async () => {
        render(<ReclasificadorAsiento />);

        await waitFor(() => {
            const textarea = screen.getByRole('textbox');
            expect(textarea.value).toContain('C-007');
        });
    });

    it('muestra las cuentas de las líneas de detalle del asiento', async () => {
        render(<ReclasificadorAsiento />);

        await waitFor(() => {
            // Pueden aparecer múltiples veces (en lista y en selector)
            expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0);
            expect(screen.getAllByText('IVA Crédito Fiscal').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Proveedores').length).toBeGreaterThan(0);
        });
    });

    it('muestra Línea Protegida en cuentas de IVA y Proveedores', async () => {
        render(<ReclasificadorAsiento />);

        await waitFor(() => {
            const lineasProtegidas = screen.getAllByText('Línea Protegida');
            expect(lineasProtegidas.length).toBeGreaterThanOrEqual(2);
        });
    });

    it('muestra el BuscadorCuentaContable solo en líneas editables', async () => {
        render(<ReclasificadorAsiento />);

        await waitFor(() => {
            const buscadores = screen.getAllByTestId('buscador-cuenta');
            // Solo la línea 6101 (Gastos Generales) no está protegida
            expect(buscadores.length).toBe(1);
        });
    });

    it('llama a Swal info cuando se guarda sin haber modificado ninguna cuenta', async () => {
        render(<ReclasificadorAsiento />);

        await waitFor(() => {
            expect(screen.getByText('Panel de Reclasificaciones')).toBeDefined();
        });

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Guardar Cambios/i }));
        });

        expect(Swal.fire).toHaveBeenCalledWith('Sin cambios', 'No has modificado ninguna cuenta.', 'info');
    });

    it('ejecuta la reclasificación al seleccionar cuenta nueva y confirmar', async () => {
        api.post.mockResolvedValueOnce({ success: true });

        render(<ReclasificadorAsiento />);

        await waitFor(() => {
            expect(screen.getAllByTestId('buscador-cuenta').length).toBe(1);
        });

        const selector = screen.getByTestId('buscador-cuenta');
        fireEvent.change(selector, { target: { value: '6201' } });

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Guardar Cambios/i }));
        });

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith(
                expect.objectContaining({ title: '¿Confirmar Reclasificación?' })
            );
        });

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/facturas/7/reclasificar', expect.objectContaining({
                cambios: expect.objectContaining({ 101: '6201' }),
            }));
        });
    });
});
