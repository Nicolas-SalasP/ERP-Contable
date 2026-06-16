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
