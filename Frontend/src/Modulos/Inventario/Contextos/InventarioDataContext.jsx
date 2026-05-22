import React, { createContext, useCallback, useMemo, useState } from 'react';
import inventarioApi from '../Servicios/inventarioApi';

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

    const cargarLotes = useCallback((options = {}) => {
        return cargarRecurso('lotes', () => inventarioApi.lotes.listar({ per_page: 100 }), options);
    }, [cargarRecurso]);

    const cargarDatosBase = useCallback((options = {}) => {
        return Promise.all([
            cargarProductos(options),
            cargarBodegas(options),
            cargarCatalogos(options),
            cargarLotes(options),
        ]);
    }, [cargarBodegas, cargarCatalogos, cargarLotes, cargarProductos]);

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
    const invalidarLotes = useCallback(() => invalidarRecurso('lotes'), [invalidarRecurso]);

    const invalidarTodoInventario = useCallback(() => {
        invalidarProductos();
        invalidarBodegas();
        invalidarCatalogos();
        invalidarLotes();
    }, [invalidarBodegas, invalidarCatalogos, invalidarLotes, invalidarProductos]);

    const value = useMemo(() => ({
        productos: store.productos.data,
        bodegas: store.bodegas.data,
        catalogos: store.catalogos.data,
        lotes: store.lotes.data,

        loadingProductos: store.productos.loading,
        loadingBodegas: store.bodegas.loading,
        loadingCatalogos: store.catalogos.loading,
        loadingLotes: store.lotes.loading,

        errorProductos: store.productos.error,
        errorBodegas: store.bodegas.error,
        errorCatalogos: store.catalogos.error,
        errorLotes: store.lotes.error,

        timestamps: {
            productos: store.productos.loadedAt,
            bodegas: store.bodegas.loadedAt,
            catalogos: store.catalogos.loadedAt,
            lotes: store.lotes.loadedAt,
        },

        cargarProductos,
        cargarBodegas,
        cargarCatalogos,
        cargarLotes,
        cargarDatosBase,

        cargarProductosCache: cargarProductos,
        cargarBodegasCache: cargarBodegas,
        cargarCatalogosCache: cargarCatalogos,
        cargarLotesCache: cargarLotes,
        cargarDatosBaseCache: cargarDatosBase,

        invalidarProductos,
        invalidarBodegas,
        invalidarCatalogos,
        invalidarLotes,
        invalidarTodoInventario,
    }), [
        cargarBodegas,
        cargarCatalogos,
        cargarDatosBase,
        cargarLotes,
        cargarProductos,
        invalidarBodegas,
        invalidarCatalogos,
        invalidarLotes,
        invalidarProductos,
        invalidarTodoInventario,
        store,
    ]);

    return (
        <InventarioDataContext.Provider value={value}>
            {children}
        </InventarioDataContext.Provider>
    );
};