import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../Configuracion/api', () => ({
    api: {
        get: vi.fn(() => Promise.resolve({ success: true, data: [] })),
        post: vi.fn(() => Promise.resolve({ success: true })),
        put: vi.fn(() => Promise.resolve({ success: true })),
    },
}));

import cumplimientoApi from './cumplimientoApi';
import { api } from '../../Configuracion/api';

beforeEach(() => {
    vi.clearAllMocks();
});

describe('cumplimientoApi', () => {
    it('auditoria.listar pega a /auditoria con params', () => {
        cumplimientoApi.auditoria.listar({ desde: '2026-01-01' });
        expect(api.get).toHaveBeenCalledWith('/auditoria', { params: { desde: '2026-01-01' } });
    });

    it('auditoria.listar sin argumentos usa params vacio', () => {
        cumplimientoApi.auditoria.listar();
        expect(api.get).toHaveBeenCalledWith('/auditoria', { params: {} });
    });

    it('incidentes.listar pega a /incidentes con params', () => {
        cumplimientoApi.incidentes.listar({ estado: 'ABIERTO' });
        expect(api.get).toHaveBeenCalledWith('/incidentes', { params: { estado: 'ABIERTO' } });
    });

    it('incidentes.crear pega POST a /incidentes con el payload', () => {
        const payload = { tipo: 'FUGA_DATOS', descripcion: 'x' };
        cumplimientoApi.incidentes.crear(payload);
        expect(api.post).toHaveBeenCalledWith('/incidentes', payload);
    });

    it('incidentes.actualizar pega PUT a /incidentes/:id con el payload', () => {
        const payload = { estado: 'CERRADO' };
        cumplimientoApi.incidentes.actualizar(3, payload);
        expect(api.put).toHaveBeenCalledWith('/incidentes/3', payload);
    });
});
