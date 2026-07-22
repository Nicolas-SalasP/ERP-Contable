import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../Configuracion/api', () => ({
    api: {
        get: vi.fn(() => Promise.resolve({ success: true, data: {} })),
        post: vi.fn(() => Promise.resolve({ success: true })),
        delete: vi.fn(() => Promise.resolve({ success: true })),
    },
}));

import privacidadApi from './privacidadApi';
import { api } from '../../Configuracion/api';

beforeEach(() => {
    vi.clearAllMocks();
});

describe('privacidadApi', () => {
    it('obtenerPolitica pega GET silencioso a /privacidad/politica', () => {
        privacidadApi.obtenerPolitica();
        expect(api.get).toHaveBeenCalledWith('/privacidad/politica', { silent: true });
    });

    it('miConsentimiento pega GET silencioso a /privacidad/mi-consentimiento', () => {
        privacidadApi.miConsentimiento();
        expect(api.get).toHaveBeenCalledWith('/privacidad/mi-consentimiento', { silent: true });
    });

    it('aceptar pega POST silencioso a /privacidad/consentimiento sin payload', () => {
        privacidadApi.aceptar();
        expect(api.post).toHaveBeenCalledWith('/privacidad/consentimiento', {}, { silent: true });
    });

    it('revocar pega DELETE silencioso a /privacidad/consentimiento', () => {
        privacidadApi.revocar();
        expect(api.delete).toHaveBeenCalledWith('/privacidad/consentimiento', { silent: true });
    });
});
