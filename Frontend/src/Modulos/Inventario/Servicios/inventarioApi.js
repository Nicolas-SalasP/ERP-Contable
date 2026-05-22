import { api } from '../../../Configuracion/api';

const toQuery = (params = {}) => {
    const entries = Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '');

    if (!entries.length) {
        return '';
    }

    const query = new URLSearchParams();

    entries.forEach(([key, value]) => {
        query.append(key, value);
    });

    return `?${query.toString()}`;
};

const normalize = (response) => ({
    success: response?.success ?? true,
    data: response?.data ?? [],
    pagination: response?.pagination ?? null,
    resumen: response?.resumen ?? null,
    message: response?.message ?? null,
});

const get = async (endpoint, params = {}) => {
    return normalize(await api.get(`${endpoint}${toQuery(params)}`));
};

const post = async (endpoint, payload = {}) => {
    return normalize(await api.post(endpoint, payload));
};

const put = async (endpoint, payload = {}) => {
    return normalize(await api.put(endpoint, payload));
};

export const inventarioApi = {
    dashboard: {
        obtener: () => get('/inventario/dashboard'),
    },

    catalogos: () => get('/inventario/catalogos'),

    productos: {
        listar: (params = {}) => get('/inventario/productos', params),
        crear: (payload) => post('/inventario/productos', payload),
        obtener: (id) => get(`/inventario/productos/${id}`),
        actualizar: (id, payload) => put(`/inventario/productos/${id}`, payload),
        valorizacion: (id, params = {}) => get(`/inventario/productos/${id}/valorizacion`, params),
        disponibilidad: (id, params = {}) => get(`/inventario/productos/${id}/disponibilidad`, params),
        lotes: (id, params = {}) => get(`/inventario/productos/${id}/lotes`, params),
        kardex: (id, params = {}) => get(`/inventario/productos/${id}/kardex`, params),
    },

    bodegas: {
        listar: (params = {}) => get('/inventario/bodegas', params),
        crear: (payload) => post('/inventario/bodegas', payload),
    },

    movimientos: {
        listar: (params = {}) => get('/inventario/movimientos', params),
        registrar: (payload) => post('/inventario/movimientos', payload),
    },

    kardex: {
        listar: (params = {}) => get('/inventario/kardex', params),
    },

    valorizacion: {
        listar: (params = {}) => get('/inventario/valorizacion', params),
    },

    lotes: {
        listar: (params = {}) => get('/inventario/lotes', params),
        crear: (payload) => post('/inventario/lotes', payload),
        obtener: (id) => get(`/inventario/lotes/${id}`),
        stock: (id) => get(`/inventario/lotes/${id}/stock`),
        actualizar: (id, payload) => put(`/inventario/lotes/${id}`, payload),
    },

    reservas: {
        listar: (params = {}) => get('/inventario/reservas', params),
        crear: (payload) => post('/inventario/reservas', payload),
        obtener: (id) => get(`/inventario/reservas/${id}`),
        cancelar: (id, payload = {}) => post(`/inventario/reservas/${id}/cancelar`, payload),
        liberar: (id, payload = {}) => post(`/inventario/reservas/${id}/liberar`, payload),
        consumir: (id, payload = {}) => post(`/inventario/reservas/${id}/consumir`, payload),
    },

    disponibilidad: {
        listar: (params = {}) => get('/inventario/disponibilidad', params),
        producto: (id, params = {}) => get(`/inventario/productos/${id}/disponibilidad`, params),
    },

    tomasFisicas: {
        listar: (params = {}) => get('/inventario/tomas-fisicas', params),
        crear: (payload) => post('/inventario/tomas-fisicas', payload),
        obtener: (id) => get(`/inventario/tomas-fisicas/${id}`),
        iniciar: (id) => post(`/inventario/tomas-fisicas/${id}/iniciar`),
        registrarConteos: (id, payload) => post(`/inventario/tomas-fisicas/${id}/conteos`, payload),
        cerrar: (id, payload = {}) => post(`/inventario/tomas-fisicas/${id}/cerrar`, payload),
        ajustar: (id, payload = {}) => post(`/inventario/tomas-fisicas/${id}/ajustar`, payload),
        cancelar: (id, payload = {}) => post(`/inventario/tomas-fisicas/${id}/cancelar`, payload),
    },
};

export default inventarioApi;