import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../Configuracion/api', () => ({
    api: {
        get: vi.fn(() => Promise.resolve({ success: true, data: [] })),
        patch: vi.fn(() => Promise.resolve({ success: true })),
    },
}));

import alertasApi from './alertasApi';
import { api } from '../../Configuracion/api';

beforeEach(() => {
    vi.clearAllMocks();
});

describe('alertasApi', () => {
    it('listar pega a /alertas con params y signal', () => {
        const signal = new AbortController().signal;
        alertasApi.listar({ estado: 'PENDIENTE' }, signal);
        expect(api.get).toHaveBeenCalledWith('/alertas', { params: { estado: 'PENDIENTE' }, signal });
    });

    it('listar sin argumentos usa params vacio', () => {
        alertasApi.listar();
        expect(api.get).toHaveBeenCalledWith('/alertas', { params: {}, signal: undefined });
    });

    it('resolver pega PATCH a /alertas/:id con el nuevo estado', () => {
        alertasApi.resolver(7, 'RESUELTA');
        expect(api.patch).toHaveBeenCalledWith('/alertas/7', { estado: 'RESUELTA' });
    });
});
