import { api } from '../../../Configuracion/api';

/**
 * Capa de servicio del módulo RRHH y Remuneraciones.
 * Espeja los endpoints de routes/api.php bajo el prefijo /rrhh.
 * Todas las respuestas siguen el envelope { success, data } del backend.
 */
const rrhhApi = {
    // ── R1: Personal ──────────────────────────────────────────────────────────
    empleados: {
        listar: (params = {}) => api.get('/rrhh/empleados', { params }),
        obtener: (id) => api.get(`/rrhh/empleados/${id}`),
        crear: (payload) => api.post('/rrhh/empleados', payload),
        actualizar: (id, payload) => api.put(`/rrhh/empleados/${id}`, payload),
        eliminar: (id) => api.delete(`/rrhh/empleados/${id}`),
    },

    // ── R1: Contratos ─────────────────────────────────────────────────────────
    contratos: {
        listarPorEmpleado: (empleadoId, options = {}) => api.get(`/rrhh/empleados/${empleadoId}/contratos`, options),
        obtener: (id) => api.get(`/rrhh/contratos/${id}`),
        crear: (empleadoId, payload) => api.post(`/rrhh/empleados/${empleadoId}/contratos`, payload),
        terminar: (id, payload) => api.post(`/rrhh/contratos/${id}/terminar`, payload),
        agregarHaber: (id, payload) => api.post(`/rrhh/contratos/${id}/haberes`, payload),
    },

    // ── R3: Liquidaciones ─────────────────────────────────────────────────────
    liquidaciones: {
        listar: (params = {}, options = {}) => api.get('/rrhh/liquidaciones', { params, ...options }),
        obtener: (id) => api.get(`/rrhh/liquidaciones/${id}`),
        calcular: (payload) => api.post('/rrhh/liquidaciones/calcular', payload),
        emitir: (id) => api.post(`/rrhh/liquidaciones/${id}/emitir`),
        anular: (id) => api.post(`/rrhh/liquidaciones/${id}/anular`),
    },

    // ── R4: Finiquitos ────────────────────────────────────────────────────────
    finiquitos: {
        listar: (options = {}) => api.get('/rrhh/finiquitos', options),
        obtener: (id) => api.get(`/rrhh/finiquitos/${id}`),
        calcular: (payload) => api.post('/rrhh/finiquitos/calcular', payload),
        firmar: (id) => api.post(`/rrhh/finiquitos/${id}/firmar`),
    },

    // ── R2: Parametrización legal ─────────────────────────────────────────────
    parametros: {
        listar: () => api.get('/rrhh/parametros'),
        crear: (payload) => api.post('/rrhh/parametros', payload),
    },
    indicadores: {
        listar: () => api.get('/rrhh/indicadores'),
        crear: (payload) => api.post('/rrhh/indicadores', payload),
    },
    tablaImpuesto: {
        listar: (anio) => api.get('/rrhh/tabla-impuesto', anio ? { params: { anio } } : {}),
    },

    // ── R5: Centralización contable ───────────────────────────────────────────
    mapeoContable: {
        listar: () => api.get('/rrhh/mapeo-contable'),
        guardar: (payload) => api.post('/rrhh/mapeo-contable', payload),
        eliminar: (tipo) => api.delete(`/rrhh/mapeo-contable/${tipo}`),
    },
    centralizacion: {
        ejecutar: (anio, mes) => api.post(`/rrhh/centralizacion/${anio}/${mes}`),
    },

    // ── R6: Previred ──────────────────────────────────────────────────────────
    previred: {
        previsualizar: (anio, mes) => api.get(`/rrhh/previred/${anio}/${mes}/preview`),
        descargar: (anio, mes) =>
            api.download(
                `/rrhh/previred/${anio}/${mes}/archivo`,
                `previred_${anio}_${String(mes).padStart(2, '0')}.csv`,
            ),
    },

    // ── R7: LRE — Libro de Remuneraciones Electrónico ────────────────────────
    lre: {
        listar: (params = {}) => api.get('/rrhh/lre', { params }),
        generar: (anio, mes) => api.post('/rrhh/lre/generar', { anio, mes }),
        validar: (id) => api.post(`/rrhh/lre/${id}/validar`),
        confirmarDt: (id, numeroConfirmacion) =>
            api.post(`/rrhh/lre/${id}/confirmar-dt`, { numero_confirmacion: numeroConfirmacion }),
        descargar: (id, anio, mes) =>
            api.download(
                `/rrhh/lre/${id}/descargar`,
                `LRE_${anio}_${String(mes).padStart(2, '0')}.txt`,
            ),
    },

    // ── R9: Libro de Remuneraciones Digital (DFL-1 Art. 62 C.T.) ─────────────
    libroRemuneraciones: {
        simular: (anio, mes) =>
            api.get(`/rrhh/libro-remuneraciones/${anio}/${mes}`),
        descargar: (anio, mes, formato = 'excel') => {
            const ext  = formato === 'pdf' ? 'pdf' : 'xls';
            const name = `libro_remuneraciones_${anio}_${String(mes).padStart(2, '0')}.${ext}`;
            return api.download(
                `/rrhh/libro-remuneraciones/${anio}/${mes}/descargar?formato=${formato}`,
                name,
            );
        },
    },
};

export default rrhhApi;
