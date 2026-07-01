import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn() },
}));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn() },
}));

import { api } from '../../../Configuracion/api';
import useRegistroFactura from './useRegistroFactura';

const PROVEEDOR_ACME   = { id: 1, razon_social: 'ACME S.A.', rut: '76.123.456-7', codigo_interno: 1 };
const PROVEEDOR_TENRI  = { id: 2, razon_social: 'TENRI SpA',  rut: '77.999.888-6', codigo_interno: 2 };

describe('useRegistroFactura', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('carga proveedores en el mount', async () => {
        api.get.mockResolvedValue({ success: true, data: [PROVEEDOR_ACME] });

        const { result } = renderHook(() => useRegistroFactura());

        await waitFor(() => {
            expect(result.current.listaProveedores).toHaveLength(1);
        });
        expect(result.current.listaProveedores[0].razon_social).toBe('ACME S.A.');
    });

    it('inicia con busqueda vacía y sin sugerencias', () => {
        api.get.mockResolvedValue({ success: true, data: [] });

        const { result } = renderHook(() => useRegistroFactura());

        expect(result.current.busqueda).toBe('');
        expect(result.current.sugerencias).toHaveLength(0);
    });

    it('handleBusquedaChange filtra proveedores por razon_social', async () => {
        api.get.mockResolvedValue({ success: true, data: [PROVEEDOR_ACME, PROVEEDOR_TENRI] });

        const { result } = renderHook(() => useRegistroFactura());

        await waitFor(() => {
            expect(result.current.listaProveedores).toHaveLength(2);
        });

        act(() => {
            result.current.handleBusquedaChange({ target: { value: 'acm' } });
        });

        expect(result.current.sugerencias).toHaveLength(1);
        expect(result.current.sugerencias[0].razon_social).toBe('ACME S.A.');
    });

    it('handleBusquedaChange filtra proveedores por rut', async () => {
        api.get.mockResolvedValue({ success: true, data: [PROVEEDOR_ACME, PROVEEDOR_TENRI] });

        const { result } = renderHook(() => useRegistroFactura());

        await waitFor(() => {
            expect(result.current.listaProveedores).toHaveLength(2);
        });

        act(() => {
            result.current.handleBusquedaChange({ target: { value: '76.123' } });
        });

        expect(result.current.sugerencias).toHaveLength(1);
        expect(result.current.sugerencias[0].rut).toBe('76.123.456-7');
    });

    it('busqueda vacía limpia sugerencias y oculta el panel', async () => {
        api.get.mockResolvedValue({ success: true, data: [PROVEEDOR_ACME, PROVEEDOR_TENRI] });

        const { result } = renderHook(() => useRegistroFactura());

        await waitFor(() => {
            expect(result.current.listaProveedores).toHaveLength(2);
        });

        act(() => {
            result.current.handleBusquedaChange({ target: { value: 'acm' } });
        });
        expect(result.current.sugerencias).toHaveLength(1);

        act(() => {
            result.current.handleBusquedaChange({ target: { value: '' } });
        });

        expect(result.current.sugerencias).toHaveLength(0);
        expect(result.current.mostrarSugerencias).toBe(false);
    });

    it('carga cuentas bancarias en paso 2 con proveedorId', async () => {
        api.get.mockImplementation((url) => {
            if (url === '/proveedores') {
                return Promise.resolve({ success: true, data: [PROVEEDOR_ACME] });
            }
            if (url.includes('/cuentas-bancarias')) {
                return Promise.resolve({ success: true, data: [{ id: 99, banco: 'BCI' }] });
            }
            return Promise.resolve({ success: false, data: [] });
        });

        const { result } = renderHook(() =>
            useRegistroFactura({ currentStep: 2, proveedorId: 1 })
        );

        await waitFor(() => {
            expect(result.current.cuentasDisponibles).toHaveLength(1);
        });
        expect(result.current.cuentasDisponibles[0].banco).toBe('BCI');
    });
});
