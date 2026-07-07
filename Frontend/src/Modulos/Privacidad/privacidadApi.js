import { api } from '../../Configuracion/api';

const privacidadApi = {
    obtenerPolitica: () => api.get('/privacidad/politica', { silent: true }),

    miConsentimiento: () => api.get('/privacidad/mi-consentimiento', { silent: true }),

    aceptar: () => api.post('/privacidad/consentimiento', {}, { silent: true }),

    revocar: () => api.delete('/privacidad/consentimiento', { silent: true }),
};

export default privacidadApi;
