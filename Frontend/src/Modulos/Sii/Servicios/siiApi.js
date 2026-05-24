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
};

export default siiApi;
