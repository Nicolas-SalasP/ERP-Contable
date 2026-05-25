import { api } from '../../../Configuracion/api';

/**
 * @typedef {Object} ConfiguracionSii
 * @property {string|null} giro_emisor
 * @property {number|null} codigo_actividad_sii
 * @property {string|null} comuna
 * @property {string|null} ciudad
 * @property {number|null} resolucion_sii_numero
 * @property {string|null} resolucion_sii_fecha   - ISO date YYYY-MM-DD
 * @property {'certificacion'|'produccion'} ambiente_sii
 * @property {string|null} email_intercambio_sii
 * @property {string|null} rut_representante_legal
 */

/**
 * @typedef {Object} CertificadoActivo
 * @property {number} id
 * @property {number} empresa_id
 * @property {string|null} subject_rut
 * @property {string|null} subject_common_name
 * @property {string|null} issuer_common_name
 * @property {string} valido_desde      - ISO datetime
 * @property {string} valido_hasta      - ISO datetime
 * @property {string|null} fingerprint_sha256
 * @property {'activo'|'cuarentena'|'revocado'} estado
 */

/**
 * @typedef {Object} CafResumen
 * @property {number} id
 * @property {number} tipo_dte
 * @property {number} folio_desde
 * @property {number} folio_hasta
 * @property {number} folio_actual
 * @property {number} folios_usados
 * @property {number} folios_huerfanos
 * @property {string} fecha_autorizacion   - ISO date
 * @property {string|null} fecha_vencimiento
 * @property {string} rut_empresa_caf
 * @property {string} razon_social_caf
 * @property {string} sii_idk
 * @property {'activo'|'agotado'|'vencido'|'revocado'} estado
 */

/**
 * @typedef {Object} SaldoPorTipo
 * @property {number} tipo_dte
 * @property {string} nombre               - nombre humano del tipo (backend lo provee)
 * @property {number} total_autorizado
 * @property {number} disponibles
 * @property {number} usados
 * @property {number} huerfanos
 * @property {number} cafs_activos
 * @property {number} cafs_agotados
 */

const siiApi = {
    configuracion: {
        /** @returns {Promise<ConfiguracionSii>} */
        obtener: () => api.get('/sii/configuracion'),

        /**
         * @param {Partial<ConfiguracionSii>} payload
         * @returns {Promise<ConfiguracionSii>}
         */
        actualizar: (payload) => api.put('/sii/configuracion', payload),
    },

    certificado: {
        /** @returns {Promise<CertificadoActivo>} */
        obtener: () => api.get('/sii/certificado'),

        /**
         * @param {File} archivo - .pfx o .p12
         * @param {string} password
         * @returns {Promise<CertificadoActivo>}
         */
        subir: (archivo, password) => {
            const fd = new FormData();
            fd.append('archivo', archivo);
            fd.append('password', password);
            return api.upload('/sii/certificado', fd);
        },

        /** @returns {Promise<{integridad_ok: boolean, mensaje: string}>} */
        verificar: () => api.post('/sii/certificado/verificar'),

        /**
         * @param {number} id
         * @returns {Promise<void>}
         */
        revocar: (id) => api.delete(`/sii/certificado/${id}`),
    },

    facturas: {
        /**
         * F6.3 - Listado paginado de facturas con estado SII basico.
         * @param {{ por_pagina?: number, pagina?: number }} params
         */
        listar: (params = {}) => api.get('/sii/facturas', params),

        /**
         * F6.3 - Payload liviano para polling de estado.
         * Shape data: { factura_id, tiene_dte, dte_id?, estado?, estado_glosa_humana?,
         *   es_terminal, es_pollable, tipo_dte?, folio?, track_id?, fecha_emision?,
         *   fecha_envio_sii?, ambiente?, glosa_sii?, ultimo_evento? }
         */
        obtenerEstado: (facturaId) => api.get(`/sii/facturas/${facturaId}/estado`),

        /** F6.3 - Vista completa: cliente, detalles, DTE con eventos+envios. */
        obtener: (facturaId) => api.get(`/sii/facturas/${facturaId}`),
    },

    caf: {
        /**
         * @param {number|null} tipoDte filtro opcional
         * @returns {Promise<{data: CafResumen[]}>}
         */
        listar: (tipoDte = null) =>
            api.get('/sii/caf', tipoDte ? { tipo_dte: tipoDte } : {}),

        /** @returns {Promise<{data: Record<string, SaldoPorTipo>}>} */
        saldos: () => api.get('/sii/caf/saldos'),

        /**
         * @param {number} id
         * @returns {Promise<CafResumen>}
         */
        mostrar: (id) => api.get(`/sii/caf/${id}`),

        /**
         * @param {File} archivo XML del CAF
         * @returns {Promise<CafResumen>}
         */
        subir: (archivo) => {
            const fd = new FormData();
            fd.append('archivo', archivo);
            return api.upload('/sii/caf', fd);
        },

        /**
         * @param {number} id
         * @param {string} motivo min 5 max 200 chars
         * @returns {Promise<void>}
         */
        revocar: (id, motivo) => api.delete(`/sii/caf/${id}`, { motivo }),
    },
};

export default siiApi;
