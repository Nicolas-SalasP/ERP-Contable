import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}));

import { api } from '../../../Configuracion/api';
import { honorarios } from './comercialApi';

describe('honorarios', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('listar llama a GET /honorarios', async () => {
        api.get.mockResolvedValue({ data: [] });
        const result = await honorarios.listar();
        expect(api.get).toHaveBeenCalledWith('/honorarios', { params: undefined });
        expect(result).toEqual([]);
    });

    it('listar pasa params a GET /honorarios', async () => {
        api.get.mockResolvedValue({ data: [{ id: 1 }] });
        const result = await honorarios.listar({ anio: 2024 });
        expect(api.get).toHaveBeenCalledWith('/honorarios', { params: { anio: 2024 } });
        expect(result).toEqual([{ id: 1 }]);
    });

    it('registrar llama a POST /honorarios con datos', async () => {
        const payload = { rut: '12345678-9', monto: 1000 };
        api.post.mockResolvedValue({ data: { id: 10, ...payload } });
        const result = await honorarios.registrar(payload);
        expect(api.post).toHaveBeenCalledWith('/honorarios', payload);
        expect(result).toEqual({ id: 10, ...payload });
    });

    it('eliminar llama a DELETE /honorarios/5', async () => {
        api.delete.mockResolvedValue({ data: { ok: true } });
        const result = await honorarios.eliminar(5);
        expect(api.delete).toHaveBeenCalledWith('/honorarios/5');
        expect(result).toEqual({ ok: true });
    });
});
