import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        upload: vi.fn(),
    },
}));

import { api } from '../../../Configuracion/api';
import siiApi from './siiApi';

describe('siiApi.configuracion', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('obtener llama a GET /sii/configuracion', async () => {
        api.get.mockResolvedValue({ ambiente_sii: 'certificacion' });
        const r = await siiApi.configuracion.obtener();
        expect(api.get).toHaveBeenCalledWith('/sii/configuracion');
        expect(r.ambiente_sii).toBe('certificacion');
    });

    it('actualizar llama a PUT /sii/configuracion con payload', async () => {
        const payload = { ambiente_sii: 'produccion', email_intercambio_sii: 'a@b.cl' };
        api.put.mockResolvedValue(payload);
        await siiApi.configuracion.actualizar(payload);
        expect(api.put).toHaveBeenCalledWith('/sii/configuracion', payload);
    });
});

describe('siiApi.certificado', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('obtener llama a GET /sii/certificado', async () => {
        api.get.mockResolvedValue({ id: 1, estado: 'activo' });
        await siiApi.certificado.obtener();
        expect(api.get).toHaveBeenCalledWith('/sii/certificado');
    });

    it('subir construye FormData con keys "archivo" y "password"', async () => {
        api.upload.mockResolvedValue({ id: 99 });

        const fakeFile = new File(['contenido'], 'cert.pfx', { type: 'application/x-pkcs12' });
        await siiApi.certificado.subir(fakeFile, 'mi_pwd');

        expect(api.upload).toHaveBeenCalledTimes(1);
        const [endpoint, fd] = api.upload.mock.calls[0];
        expect(endpoint).toBe('/sii/certificado');
        expect(fd).toBeInstanceOf(FormData);
        expect(fd.get('archivo')).toBe(fakeFile);
        expect(fd.get('password')).toBe('mi_pwd');
    });

    it('verificar llama a POST /sii/certificado/verificar', async () => {
        api.post.mockResolvedValue({ integridad_ok: true });
        await siiApi.certificado.verificar();
        expect(api.post).toHaveBeenCalledWith('/sii/certificado/verificar');
    });

    it('revocar llama a DELETE /sii/certificado/{id}', async () => {
        api.delete.mockResolvedValue(null);
        await siiApi.certificado.revocar(42);
        expect(api.delete).toHaveBeenCalledWith('/sii/certificado/42');
    });
});

describe('siiApi.caf', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('listar sin filtro llama a GET /sii/caf sin params', async () => {
        api.get.mockResolvedValue({ data: [] });
        await siiApi.caf.listar();
        expect(api.get).toHaveBeenCalledWith('/sii/caf', {});
    });

    it('listar con filtro pasa tipo_dte como param', async () => {
        api.get.mockResolvedValue({ data: [] });
        await siiApi.caf.listar(33);
        expect(api.get).toHaveBeenCalledWith('/sii/caf', { tipo_dte: 33 });
    });

    it('saldos llama a GET /sii/caf/saldos', async () => {
        api.get.mockResolvedValue({ data: {} });
        await siiApi.caf.saldos();
        expect(api.get).toHaveBeenCalledWith('/sii/caf/saldos');
    });

    it('subir construye FormData con key "archivo"', async () => {
        api.upload.mockResolvedValue({ id: 1 });

        const fakeXml = new File(['<AUTORIZACION/>'], 'caf.xml', { type: 'application/xml' });
        await siiApi.caf.subir(fakeXml);

        expect(api.upload).toHaveBeenCalledTimes(1);
        const [endpoint, fd] = api.upload.mock.calls[0];
        expect(endpoint).toBe('/sii/caf');
        expect(fd).toBeInstanceOf(FormData);
        expect(fd.get('archivo')).toBe(fakeXml);
    });

    it('revocar envia motivo en body de DELETE /sii/caf/{id}', async () => {
        api.delete.mockResolvedValue(null);
        await siiApi.caf.revocar(99, 'razon valida de prueba');
        expect(api.delete).toHaveBeenCalledWith('/sii/caf/99', { motivo: 'razon valida de prueba' });
    });

    it('mostrar llama a GET /sii/caf/{id}', async () => {
        api.get.mockResolvedValue({ id: 7 });
        await siiApi.caf.mostrar(7);
        expect(api.get).toHaveBeenCalledWith('/sii/caf/7');
    });
});
