import { api } from '../../../Configuracion/api';

/**
 * Capa de servicio del módulo Tributario.
 * Espeja los endpoints de routes/api.php bajo el prefijo /dj.
 */

export const dj1879 = {
    listar: () =>
        api.get('/dj/1879').then(r => r.data),

    generar: (anio) =>
        api.post('/dj/1879/generar', { anio }).then(r => r.data),

    validar: (id) =>
        api.post(`/dj/1879/${id}/validar`).then(r => r.data),

    descargar: (id) =>
        api.get(`/dj/1879/${id}/descargar`, { responseType: 'blob' }).then(r => r.data),

    confirmarPresentacion: (id, folio) =>
        api.post(`/dj/1879/${id}/confirmar-presentacion`, { folio_presentacion: folio }).then(r => r.data),
};

export const dj1947 = {
    listar: () =>
        api.get('/dj/1947').then(r => r.data),

    generar: (anio) =>
        api.post('/dj/1947/generar', { anio }).then(r => r.data),

    validar: (id) =>
        api.post(`/dj/1947/${id}/validar`).then(r => r.data),

    descargar: (id) =>
        api.get(`/dj/1947/${id}/descargar`, { responseType: 'blob' }).then(r => r.data),

    confirmarPresentacion: (id, folio) =>
        api.post(`/dj/1947/${id}/confirmar-presentacion`, { folio_presentacion: folio }).then(r => r.data),
};

export const dj1887 = {
    listar: () =>
        api.get('/dj/1887').then(r => r.data),

    generar: (anio, anio40Horas) =>
        api.post('/dj/1887/generar', { anio, anio_40_horas: anio40Horas }).then(r => r.data),

    validar: (id) =>
        api.post(`/dj/1887/${id}/validar`).then(r => r.data),

    descargar: (id) =>
        api.get(`/dj/1887/${id}/descargar`, { responseType: 'blob' }).then(r => r.data),

    confirmarPresentacion: (id, folioPresentacion) =>
        api.post(`/dj/1887/${id}/confirmar-presentacion`, { folio_presentacion: folioPresentacion }).then(r => r.data),
};

export const dj1926 = {
    listar: () =>
        api.get('/dj/1926').then(r => r.data),

    generar: (anio) =>
        api.post('/dj/1926/generar', { anio }).then(r => r.data),

    validar: (id) =>
        api.post(`/dj/1926/${id}/validar`).then(r => r.data),

    descargar: (id) =>
        api.get(`/dj/1926/${id}/descargar`, { responseType: 'blob' }).then(r => r.data),

    confirmarPresentacion: (id, folio) =>
        api.post(`/dj/1926/${id}/confirmar-presentacion`, { folio_presentacion: folio }).then(r => r.data),
};

export const dj1837 = {
    listar: () =>
        api.get('/dj/1837').then(r => r.data),

    generar: (anio) =>
        api.post('/dj/1837/generar', { anio }).then(r => r.data),

    validar: (id) =>
        api.post(`/dj/1837/${id}/validar`).then(r => r.data),

    descargar: (id) =>
        api.get(`/dj/1837/${id}/descargar`, { responseType: 'blob' }).then(r => r.data),

    confirmarPresentacion: (id, folio) =>
        api.post(`/dj/1837/${id}/confirmar-presentacion`, { folio_presentacion: folio }).then(r => r.data),
};

export const dj1835 = {
    listar: () =>
        api.get('/dj/1835').then(r => r.data),

    generar: (anio) =>
        api.post('/dj/1835/generar', { anio }).then(r => r.data),

    validar: (id) =>
        api.post(`/dj/1835/${id}/validar`).then(r => r.data),

    descargar: (id) =>
        api.get(`/dj/1835/${id}/descargar`, { responseType: 'blob' }).then(r => r.data),

    confirmarPresentacion: (id, folio) =>
        api.post(`/dj/1835/${id}/confirmar-presentacion`, { folio_presentacion: folio }).then(r => r.data),
};

export const lcv = {
    ventas:  (mes, anio) =>
        api.get(`/impuestos/lcv/ventas/${mes}/${anio}`).then(r => r.data),

    compras: (mes, anio) =>
        api.get(`/impuestos/lcv/compras/${mes}/${anio}`).then(r => r.data),

    descargarVentas: (mes, anio, formato) =>
        api.get(`/impuestos/lcv/ventas/${mes}/${anio}/descargar?formato=${formato}`, { responseType: 'blob' }),

    descargarCompras: (mes, anio, formato) =>
        api.get(`/impuestos/lcv/compras/${mes}/${anio}/descargar?formato=${formato}`, { responseType: 'blob' }),
};
