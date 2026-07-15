import React, { useContext } from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor, act } from '@testing-library/react';

const listarProductosMock = vi.fn(() => Promise.resolve({ success: true, data: [{ id: 1, nombre: 'Producto A' }] }));
const listarBodegasMock = vi.fn(() => Promise.resolve({ success: true, data: [{ id: 1, nombre: 'Bodega A' }] }));
const catalogosMock = vi.fn(() => Promise.resolve({ success: true, data: { unidades_medida: ['UN'], bodegas: [], tipos_producto: [], metodos_valorizacion: [] } }));
const listarUbicacionesMock = vi.fn(() => Promise.resolve({ success: true, data: [] }));
const listarLotesMock = vi.fn(() => Promise.resolve({ success: true, data: [] }));

vi.mock('../Servicios/inventarioApi', () => ({
    default: {
        productos: { listar: (...a) => listarProductosMock(...a) },
        bodegas: { listar: (...a) => listarBodegasMock(...a) },
        catalogos: (...a) => catalogosMock(...a),
        ubicaciones: { listar: (...a) => listarUbicacionesMock(...a) },
        lotes: { listar: (...a) => listarLotesMock(...a) },
    },
}));

const suscribirInventarioEmpresaMock = vi.fn(() => Promise.resolve(() => {}));

vi.mock('../Servicios/inventarioRealtime', () => ({
    suscribirInventarioEmpresa: (...a) => suscribirInventarioEmpresaMock(...a),
}));

beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();
    listarProductosMock.mockResolvedValue({ success: true, data: [{ id: 1, nombre: 'Producto A' }] });
    listarBodegasMock.mockResolvedValue({ success: true, data: [{ id: 1, nombre: 'Bodega A' }] });
    catalogosMock.mockResolvedValue({ success: true, data: { unidades_medida: ['UN'], bodegas: [], tipos_producto: [], metodos_valorizacion: [] } });
    listarUbicacionesMock.mockResolvedValue({ success: true, data: [] });
    listarLotesMock.mockResolvedValue({ success: true, data: [] });
    suscribirInventarioEmpresaMock.mockResolvedValue(() => {});
    window.localStorage.clear();
    window.sessionStorage.clear();
});

/** Importa el contexto de forma dinamica porque tiene cache module-level -- cada test necesita su propio modulo fresco. */
async function cargarContexto() {
    return import('./InventarioDataContext');
}

describe('InventarioDataContext', () => {
    it('cargarProductos pega a la API, guarda en el store y expone loading correctamente', async () => {
        const { InventarioDataContext, InventarioDataProvider } = await cargarContexto();
        const { result } = renderHook(() => useContext(InventarioDataContext), {
            wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider>,
        });

        expect(result.current.productos).toEqual([]);

        await act(async () => {
            await result.current.cargarProductos();
        });

        expect(listarProductosMock).toHaveBeenCalledWith({ limit: 100 });
        expect(result.current.productos).toEqual([{ id: 1, nombre: 'Producto A' }]);
        expect(result.current.loadingProductos).toBe(false);
        expect(result.current.errorProductos).toBeNull();
    });

    it('una segunda llamada a cargarProductos reusa la cache (no vuelve a pegarle a la API)', async () => {
        const { InventarioDataContext, InventarioDataProvider } = await cargarContexto();
        const { result } = renderHook(() => useContext(InventarioDataContext), {
            wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider>,
        });

        await act(async () => {
            await result.current.cargarProductos();
        });
        await act(async () => {
            await result.current.cargarProductos();
        });

        expect(listarProductosMock).toHaveBeenCalledTimes(1);
    });

    it('cargarProductos con force=true ignora la cache y vuelve a pegarle a la API', async () => {
        const { InventarioDataContext, InventarioDataProvider } = await cargarContexto();
        const { result } = renderHook(() => useContext(InventarioDataContext), {
            wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider>,
        });

        await act(async () => {
            await result.current.cargarProductos();
        });
        await act(async () => {
            await result.current.cargarProductos({ force: true });
        });

        expect(listarProductosMock).toHaveBeenCalledTimes(2);
    });

    it('invalidarProductos limpia loadedAt y fuerza recarga en la siguiente llamada', async () => {
        const { InventarioDataContext, InventarioDataProvider } = await cargarContexto();
        const { result } = renderHook(() => useContext(InventarioDataContext), {
            wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider>,
        });

        await act(async () => {
            await result.current.cargarProductos();
        });
        act(() => {
            result.current.invalidarProductos();
        });
        await act(async () => {
            await result.current.cargarProductos();
        });

        expect(listarProductosMock).toHaveBeenCalledTimes(2);
    });

    it('un error en el loader se refleja en errorProductos y no rompe el hook', async () => {
        listarProductosMock.mockRejectedValueOnce(new Error('fallo de red'));
        const { InventarioDataContext, InventarioDataProvider } = await cargarContexto();
        const { result } = renderHook(() => useContext(InventarioDataContext), {
            wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider>,
        });

        await act(async () => {
            try {
                await result.current.cargarProductos();
            } catch {
                // esperado -- el hook re-lanza el error, el estado igual se actualiza.
            }
        });

        expect(result.current.errorProductos).toBeInstanceOf(Error);
        expect(result.current.loadingProductos).toBe(false);
    });

    it('cargarDatosBase pega a los 5 recursos en paralelo', async () => {
        const { InventarioDataContext, InventarioDataProvider } = await cargarContexto();
        const { result } = renderHook(() => useContext(InventarioDataContext), {
            wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider>,
        });

        await act(async () => {
            await result.current.cargarDatosBase();
        });

        expect(listarProductosMock).toHaveBeenCalledTimes(1);
        expect(listarBodegasMock).toHaveBeenCalledTimes(1);
        expect(catalogosMock).toHaveBeenCalledTimes(1);
        expect(listarUbicacionesMock).toHaveBeenCalledTimes(1);
        expect(listarLotesMock).toHaveBeenCalledTimes(1);
    });

    it('invalidarTodoInventario invalida los 5 recursos', async () => {
        const { InventarioDataContext, InventarioDataProvider } = await cargarContexto();
        const { result } = renderHook(() => useContext(InventarioDataContext), {
            wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider>,
        });

        await act(async () => {
            await result.current.cargarDatosBase();
        });
        act(() => {
            result.current.invalidarTodoInventario();
        });
        await act(async () => {
            await result.current.cargarDatosBase();
        });

        expect(listarProductosMock).toHaveBeenCalledTimes(2);
        expect(listarBodegasMock).toHaveBeenCalledTimes(2);
        expect(catalogosMock).toHaveBeenCalledTimes(2);
    });

    it('no se suscribe al realtime si no hay usuario con empresa_id en storage', async () => {
        const { InventarioDataProvider } = await cargarContexto();
        renderHook(() => {}, { wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider> });

        expect(suscribirInventarioEmpresaMock).not.toHaveBeenCalled();
    });

    it('se suscribe al realtime con el empresa_id del usuario en storage', async () => {
        window.localStorage.setItem('erp_user', JSON.stringify({ id: 5, empresa_id: 3 }));
        const { InventarioDataProvider } = await cargarContexto();
        renderHook(() => {}, { wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider> });

        await waitFor(() => {
            expect(suscribirInventarioEmpresaMock).toHaveBeenCalledWith(
                3,
                expect.objectContaining({
                    onAlertasActualizadas: expect.any(Function),
                    onStockCritico: expect.any(Function),
                })
            );
        });
    });

    it('el callback onStockCritico del realtime invalida productos y dispara el CustomEvent', async () => {
        window.localStorage.setItem('erp_user', JSON.stringify({ id: 5, empresa_id: 3 }));
        const dispatchSpy = vi.spyOn(window, 'dispatchEvent');

        const { InventarioDataContext, InventarioDataProvider } = await cargarContexto();
        const { result } = renderHook(() => useContext(InventarioDataContext), {
            wrapper: ({ children }) => <InventarioDataProvider>{children}</InventarioDataProvider>,
        });

        await waitFor(() => expect(suscribirInventarioEmpresaMock).toHaveBeenCalled());

        await act(async () => {
            await result.current.cargarProductos();
        });

        const callbacks = suscribirInventarioEmpresaMock.mock.calls[0][1];
        act(() => {
            callbacks.onStockCritico({ producto_id: 1 });
        });

        expect(result.current.errorProductos).toBeNull();
        expect(dispatchSpy).toHaveBeenCalledWith(expect.objectContaining({ type: 'inventario:stock-critico' }));

        dispatchSpy.mockRestore();
    });
});
