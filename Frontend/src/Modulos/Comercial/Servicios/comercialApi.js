import { api } from '../../../Configuracion/api';

/**
 * Capa de servicio del módulo Comercial.
 * Espeja los endpoints de routes/api.php.
 */

export const honorarios = {
    listar: (params) =>
        api.get('/honorarios', { params }).then(r => r.data),

    registrar: (datos) =>
        api.post('/honorarios', datos).then(r => r.data),

    eliminar: (id) =>
        api.delete(`/honorarios/${id}`).then(r => r.data),
};

export const ordenesCompra = {
    listar: (params) =>
        api.get('/comercial/ordenes-compra', { params }).then(r => r.data),

    crear: (datos) =>
        api.post('/comercial/ordenes-compra', datos).then(r => r.data),

    obtener: (id) =>
        api.get(`/comercial/ordenes-compra/${id}`).then(r => r.data),

    actualizar: (id, datos) =>
        api.put(`/comercial/ordenes-compra/${id}`, datos).then(r => r.data),

    anular: (id) =>
        api.delete(`/comercial/ordenes-compra/${id}`).then(r => r.data),

    recibirMercaderia: (id, datos) =>
        api.post(`/comercial/ordenes-compra/${id}/recibir`, datos).then(r => r.data),
};
