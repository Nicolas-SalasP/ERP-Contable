import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn() },
}));
vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), log: vi.fn(), warn: vi.fn() },
}));

import { api } from '../../../Configuracion/api';
import { useFacturasHistorial } from './useFacturasHistorial';

afterEach(cleanup);

// Respuesta estándar para ambos endpoints
const mockApiBase = () => {
    api.get.mockImplementation((url) => {
        if (url.includes('proveedores/catalogo')) {
            return Promise.resolve({
                success: true,
                data: [{ id: 1, razon_social: 'Proveedor SA', rut: '12.345.678-9' }],
            });
        }
        if (url.includes('historial')) {
            return Promise.resolve({
                success: true,
                data: [{ id: 1, numero_factura: '100' }],
                pagination: { total: 1, totalPages: 1 },
            });
        }
        return Promise.resolve({ success: true, data: [] });
    });
};

describe('useFacturasHistorial (hook)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockApiBase();
    });

    it('retorna valores iniciales correctos', async () => {
        let result;
        await act(async () => {
            ({ result } = renderHook(() => useFacturasHistorial()));
        });
        expect(result.current.busqueda).toBe('');
        expect(Array.isArray(result.current.facturas)).toBe(true);
        expect(result.current.searched).toBe(true); // el useEffect inicial dispara ejecutarBusqueda
        expect(result.current.pagination).toMatchObject({ page: 1 });
    });

    it('ejecutarBusqueda llama a api.get con los filtros correctos', async () => {
        let result;
        await act(async () => {
            ({ result } = renderHook(() => useFacturasHistorial()));
        });

        // Limpiar las llamadas del montaje
        api.get.mockClear();
        mockApiBase();

        await act(async () => {
            result.current.setBusqueda('Proveedor SA');
        });

        await act(async () => {
            await result.current.ejecutarBusqueda(true);
        });

        const llamadas = api.get.mock.calls.map(([url]) => url);
        const llamadaHistorial = llamadas.find((u) => u.includes('historial'));
        expect(llamadaHistorial).toBeDefined();
        expect(llamadaHistorial).toContain('search=Proveedor+SA');
    });

    it('ejecutarBusqueda actualiza facturas cuando la respuesta es exitosa', async () => {
        let result;
        await act(async () => {
            ({ result } = renderHook(() => useFacturasHistorial()));
        });
        await waitForFacturas(result);
        expect(result.current.facturas).toHaveLength(1);
        expect(result.current.facturas[0]).toMatchObject({ id: 1, numero_factura: '100' });
    });

    it('ejecutarBusqueda setea facturas en [] cuando success es false', async () => {
        api.get.mockImplementation((url) => {
            if (url.includes('proveedores/catalogo')) {
                return Promise.resolve({ success: true, data: [] });
            }
            return Promise.resolve({ success: false, data: [] });
        });

        let result;
        await act(async () => {
            ({ result } = renderHook(() => useFacturasHistorial()));
        });

        await act(async () => {
            await result.current.ejecutarBusqueda();
        });

        expect(result.current.facturas).toHaveLength(0);
    });

    it('handleBusquedaChange actualiza busqueda y genera sugerencias', async () => {
        let result;
        await act(async () => {
            ({ result } = renderHook(() => useFacturasHistorial()));
        });
        // Esperar que el catálogo de proveedores se cargue
        await waitForProveedores(result);

        act(() => {
            result.current.handleBusquedaChange({ target: { value: 'Proveedor' } });
        });

        expect(result.current.busqueda).toBe('Proveedor');
        expect(result.current.sugerencias).toHaveLength(1);
        expect(result.current.mostrarSugerencias).toBe(true);
    });

    it('seleccionarProveedor actualiza busqueda con razon_social del proveedor', async () => {
        let result;
        await act(async () => {
            ({ result } = renderHook(() => useFacturasHistorial()));
        });
        await waitForProveedores(result);

        act(() => {
            result.current.seleccionarProveedor({ id: 1, razon_social: 'Proveedor SA', rut: '12.345.678-9' });
        });

        expect(result.current.busqueda).toBe('Proveedor SA');
        expect(result.current.mostrarSugerencias).toBe(false);
    });

    it('al cambiar pagination.page vuelve a ejecutar la búsqueda', async () => {
        let result;
        await act(async () => {
            ({ result } = renderHook(() => useFacturasHistorial()));
        });

        // Contar llamadas tras el montaje
        const llamadasIniciales = api.get.mock.calls.filter(([url]) =>
            url.includes('historial'),
        ).length;

        await act(async () => {
            result.current.setPagination((prev) => ({ ...prev, page: 2 }));
        });

        await act(async () => {
            // Dar tiempo para que el useEffect reaccione al cambio de page
        });

        const llamadasFinales = api.get.mock.calls.filter(([url]) =>
            url.includes('historial'),
        ).length;

        expect(llamadasFinales).toBeGreaterThan(llamadasIniciales);
    });

    it('carga el catálogo de proveedores al montar', async () => {
        await act(async () => {
            renderHook(() => useFacturasHistorial());
        });
        expect(api.get).toHaveBeenCalledWith(
            expect.stringContaining('proveedores/catalogo'),
        );
    });
});

// Helpers de espera para evitar repetición

async function waitForFacturas(result) {
    // Retorna cuando facturas se haya populado o timeout
    for (let i = 0; i < 20; i++) {
        if (result.current.facturas.length > 0) return;
        await act(async () => {
            await new Promise((r) => setTimeout(r, 10));
        });
    }
}

async function waitForProveedores(result) {
    for (let i = 0; i < 20; i++) {
        if (result.current.listaProveedores.length > 0) return;
        await act(async () => {
            await new Promise((r) => setTimeout(r, 10));
        });
    }
}
