import { api } from '../../Configuracion/api';

const privacidadApi = {
    // GET /privacidad/politica — returns the active policy
    obtenerPolitica: () => api.get('/privacidad/politica', { silent: true }),

    // GET /privacidad/mi-consentimiento — returns { aceptada: bool, version: string|null }
    miConsentimiento: () => api.get('/privacidad/mi-consentimiento', { silent: true }),

    // POST /privacidad/consentimiento — records acceptance
    aceptar: () => api.post('/privacidad/consentimiento', {}, { silent: true }),

    // DELETE /privacidad/consentimiento — revokes consent
    revocar: () => api.delete('/privacidad/consentimiento', { silent: true }),
};

export default privacidadApi;
