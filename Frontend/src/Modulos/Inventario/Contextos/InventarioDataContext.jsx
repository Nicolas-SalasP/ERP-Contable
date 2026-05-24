import React, { createContext, useCallback, useEffect, useMemo, useState } from 'react';
import inventarioApi from '../Servicios/inventarioApi';
import { suscribirInventarioEmpresa } from '../Servicios/inventarioRealtime';

const CACHE_TTL_MS = 5 * 60 * 1000;

const emptyCatalogos = {
    unidades_medida: [],
    bodegas: [],
    tipos_producto: [],
    metodos_valorizacion: [],
};

const createResource = (data) => ({
    data,
    loadedAt: null,
    loading: false,
    error: null,
});

const createInitialStore = () => ({
    scopeKey: null,
    productos: createResource([]),
    bodegas: createResource([]),
    ubicaciones: createResource([]),
    catalogos: createResource(emptyCatalogos),
    lotes: createResource([]),
});

const inventarioCacheStore = createInitialStore();
const pendingRequests = {};

const getStorageValue = (key) => {
    if (typeof window === 'undefined') return null;

    return window.localStorage.getItem(key)
        || window.sessionStorage.getItem(key);
};

const getCacheScopeKey = () => {
    const rawUser = getStorageValue('erp_user');

    if (!rawUser) {
        return 'anonymous';
    }

    try {
        const user = JSON.parse(rawUser);
        return `${user?.empresa_id || 'sin_empresa'}:${user?.id || 'sin_usuario'}`;
    } catch {
        return 'anonymous';
    }
};

const ensureScope = () => {
    const currentScope = getCacheScopeKey();

    if (inventarioCacheStore.scopeKey === currentScope) {
        return;
    }

    const fresh = createInitialStore();
    fresh.scopeKey = currentScope;

    Object.assign(inventarioCacheStore, fresh);

    Object.keys(pendingRequests).forEach((key) => {
        delete pendingRequests[key];
    });
};

const hasValidCache = (resource) => {
    if (!resource?.loadedAt) return false;

    return Date.now() - resource.loadedAt < CACHE_TTL_MS;
};

const normalizeResponse = (resourceName, response) => {
    const data = resourceName === 'catalogos'
        ? (response?.data || response || emptyCatalogos)
        : (response?.data || []);

    return {
        success: response?.success ?? true,
        data,
        pagination: response?.pagination ?? null,
        resumen: response?.resumen ?? null,
        message: response?.message ?? null,
    };
};

export const InventarioDataContext = createContext(null);

export const InventarioDataProvider = ({ children }) => {
    ensureScope();

    const [store, setStore] = useState(() => ({ ...inventarioCacheStore }));

    const syncResource = useCallback((resourceName, patch) => {
        inventarioCacheStore[resourceName] = {
            ...inventarioCacheStore[resourceName],
            ...patch,
        };

        setStore((current) => ({
            ...current,
            scopeKey: inventarioCacheStore.scopeKey,
            [resourceName]: inventarioCacheStore[resourceName],
        }));
    }, []);

    const cargarRecurso = useCallback(async (resourceName, loader, { force = false } = {}) => {
        ensureScope();

        const cachedResource = inventarioCacheStore[resourceName];

        if (!force && hasValidCache(cachedResource)) {
            setStore((current) => ({
                ...current,
                scopeKey: inventarioCacheStore.scopeKey,
                [resourceName]: cachedResource,
            }));

            return normalizeResponse(resourceName, { data: cachedResource.data });
        }

        if (!force && pendingRequests[resourceName]) {
            return pendingRequests[resourceName];
        }

        syncResource(resourceName, {
            loading: true,
            error: null,
        });

        pendingRequests[resourceName] = loader()
            .then((response) => {
                const normalized = normalizeResponse(resourceName, response);

                syncResource(resourceName, {
                    data: normalized.data,
                    loadedAt: Date.now(),
                    loading: false,
                    error: null,
                });

                return normalized;
            })
            .catch((error) => {
                syncResource(resourceName, {
                    loading: false,
                    error,
                });

                throw error;
            })
            .finally(() => {
                delete pendingRequests[resourceName];
            });

        return pendingRequests[resourceName];
    }, [syncResource]);

    const cargarProductos = useCallback((options = {}) => {
        return cargarRecurso('productos', () => inventarioApi.productos.listar({ limit: 100 }), options);
    }, [cargarRecurso]);

    const cargarBodegas = useCallback((options = {}) => {
        return cargarRecurso('bodegas', () => inventarioApi.bodegas.listar(), options);
    }, [cargarRecurso]);

    const cargarCatalogos = useCallback((options = {}) => {
        return cargarRecurso('catalogos', () => inventarioApi.catalogos(), options);
    }, [cargarRecurso]);

    const cargarUbicaciones = useCallback((options = {}) => {
        return cargarRecurso('ubicaciones', () => inventarioApi.ubicaciones.listar({ per_page: 200 }), options);
    }, [cargarRecurso]);

    const cargarLotes = useCallback((options = {}) => {
        return cargarRecurso('lotes', () => inventarioApi.lotes.listar({ per_page: 100 }), options);
    }, [cargarRecurso]);

    const cargarDatosBase = useCallback((options = {}) => {
        return Promise.all([
            cargarProductos(options),
            cargarBodegas(options),
            cargarCatalogos(options),
            cargarUbicaciones(options),
            cargarLotes(options),
        ]);
    }, [cargarBodegas, cargarCatalogos, cargarLotes, cargarProductos, cargarUbicaciones]);

    const invalidarRecurso = useCallback((resourceName) => {
        const fallbackData = resourceName === 'catalogos' ? emptyCatalogos : [];

        syncResource(resourceName, {
            data: inventarioCacheStore[resourceName]?.data || fallbackData,
            loadedAt: null,
            loading: false,
            error: null,
        });
    }, [syncResource]);

    const invalidarProductos = useCallback(() => invalidarRecurso('productos'), [invalidarRecurso]);
    const invalidarBodegas = useCallback(() => invalidarRecurso('bodegas'), [invalidarRecurso]);
    const invalidarCatalogos = useCallback(() => invalidarRecurso('catalogos'), [invalidarRecurso]);
    const invalidarUbicaciones = useCallback(() => invalidarRecurso('ubicaciones'), [invalidarRecurso]);
    const invalidarLotes = useCallback(() => invalidarRecurso('lotes'), [invalidarRecurso]);

    const invalidarTodoInventario = useCallback(() => {
        invalidarProductos();
        invalidarBodegas();
        invalidarCatalogos();
        invalidarUbicaciones();
        invalidarLotes();
    }, [invalidarBodegas, invalidarCatalogos, invalidarLotes, invalidarProductos, invalidarUbicaciones]);


    useEffect(() => {
        let unsubscribe = () => {};
        let cancelled = false;

        const rawUser = getStorageValue('erp_user');
        let empresaId = 0;

        try {
            empresaId = rawUser ? Number(JSON.parse(rawUser)?.empresa_id || 0) : 0;
        } catch {
            empresaId = 0;
        }

        if (!empresaId) {
            return unsubscribe;
        }

        suscribirInventarioEmpresa(empresaId, {
            onAlertasActualizadas: (event) => {
                invalidarTodoInventario();
                window.dispatchEvent(new CustomEvent('inventario:actualizado', { detail: event }));
            },
            onStockCritico: (event) => {
                invalidarProductos();
                window.dispatchEvent(new CustomEvent('inventario:stock-critico', { detail: event }));
            },
        }).then((cleanup) => {
            if (cancelled) {
                cleanup?.();
                return;
            }

            unsubscribe = cleanup;
        }).catch(() => {
            // El realtime es progresivo: si Reverb/Pusher no está configurado,
            // el módulo sigue funcionando con lectura HTTP tradicional.
        });

        return () => {
            cancelled = true;
            unsubscribe?.();
        };
    }, [invalidarProductos, invalidarTodoInventario]);

    const value = useMemo(() => ({
        productos: store.productos.data,
        bodegas: store.bodegas.data,
        ubicaciones: store.ubicaciones.data,
        catalogos: store.catalogos.data,
        lotes: store.lotes.data,

        loadingProductos: store.productos.loading,
        loadingBodegas: store.bodegas.loading,
        loadingUbicaciones: store.ubicaciones.loading,
        loadingCatalogos: store.catalogos.loading,
        loadingLotes: store.lotes.loading,

        errorProductos: store.productos.error,
        errorBodegas: store.bodegas.error,
        errorUbicaciones: store.ubicaciones.error,
        errorCatalogos: store.catalogos.error,
        errorLotes: store.lotes.error,

        timestamps: {
            productos: store.productos.loadedAt,
            bodegas: store.bodegas.loadedAt,
            ubicaciones: store.ubicaciones.loadedAt,
            catalogos: store.catalogos.loadedAt,
            lotes: store.lotes.loadedAt,
        },

        cargarProductos,
        cargarBodegas,
        cargarUbicaciones,
        cargarCatalogos,
        cargarLotes,
        cargarDatosBase,

        cargarProductosCache: cargarProductos,
        cargarBodegasCache: cargarBodegas,
        cargarUbicacionesCache: cargarUbicaciones,
        cargarCatalogosCache: cargarCatalogos,
        cargarLotesCache: cargarLotes,
        cargarDatosBaseCache: cargarDatosBase,

        invalidarProductos,
        invalidarBodegas,
        invalidarUbicaciones,
        invalidarCatalogos,
        invalidarLotes,
        invalidarTodoInventario,
    }), [
        cargarBodegas,
        cargarCatalogos,
        cargarDatosBase,
        cargarLotes,
        cargarProductos,
        cargarUbicaciones,
        invalidarBodegas,
        invalidarCatalogos,
        invalidarLotes,
        invalidarProductos,
        invalidarUbicaciones,
        invalidarTodoInventario,
        store,
    ]);

    return (
        <InventarioDataContext.Provider value={value}>
            {children}
        </InventarioDataContext.Provider>
    );
};