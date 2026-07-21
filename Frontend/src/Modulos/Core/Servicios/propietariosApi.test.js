import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(() => Promise.resolve({ data: [{ id: 1 }] })),
        post: vi.fn(() => Promise.resolve({ data: { id: 2 } })),
        put: vi.fn(() => Promise.resolve({ data: { id: 3 } })),
        delete: vi.fn(() => Promise.resolve({ data: { ok: true } })),
    },
}));

import { propietariosApi } from './propietariosApi';
import { api } from '../../../Configuracion/api';

beforeEach(() => {
    vi.clearAllMocks();
});

describe('propietariosApi', () => {
    it('listar pega GET a /empresa/propietarios y devuelve response.data', async () => {
        const resultado = await propietariosApi.listar();
        expect(api.get).toHaveBeenCalledWith('/empresa/propietarios');
        expect(resultado).toEqual([{ id: 1 }]);
    });

    it('crear pega POST con el payload y devuelve response.data', async () => {
        const datos = { nombre: 'Juan' };
        const resultado = await propietariosApi.crear(datos);
        expect(api.post).toHaveBeenCalledWith('/empresa/propietarios', datos);
        expect(resultado).toEqual({ id: 2 });
    });

    it('actualizar pega PUT a /empresa/propietarios/:id con el payload', async () => {
        const datos = { nombre: 'Pedro' };
        const resultado = await propietariosApi.actualizar(3, datos);
        expect(api.put).toHaveBeenCalledWith('/empresa/propietarios/3', datos);
        expect(resultado).toEqual({ id: 3 });
    });

    it('eliminar pega DELETE a /empresa/propietarios/:id', async () => {
        const resultado = await propietariosApi.eliminar(5);
        expect(api.delete).toHaveBeenCalledWith('/empresa/propietarios/5');
        expect(resultado).toEqual({ ok: true });
    });
});
