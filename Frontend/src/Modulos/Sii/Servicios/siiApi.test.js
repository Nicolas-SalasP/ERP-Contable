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
        expect(api.get).toHaveBeenCalledWith('/sii/certificado', { silent: true });
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

    expect(api.get).toHaveBeenCalledWith('/sii/caf', {
        params: {
            tipo_dte: 33,
        },
    });
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

describe('siiApi.dte', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('reintentar llama POST /sii/dte/{id}/reintentar con payload y silent', () => {
        api.post.mockResolvedValue({ data: { encolado: true } });
        siiApi.dte.reintentar(42, { razon: 'Reintento manual' });
        expect(api.post).toHaveBeenCalledWith(
            '/sii/dte/42/reintentar',
            { razon: 'Reintento manual' },
            { silent: true }
        );
    });

    it('reintentar sin payload usa objeto vacío por defecto', () => {
        api.post.mockResolvedValue({ data: {} });
        siiApi.dte.reintentar(7);
        expect(api.post).toHaveBeenCalledWith('/sii/dte/7/reintentar', {}, { silent: true });
    });

    it('reintentar desenvuelve .data correctamente', async () => {
        const respuesta = { data: { estado: 'ENCOLADO' } };
        api.post.mockResolvedValue(respuesta);
        const resultado = await siiApi.dte.reintentar(1, {}).then((r) => r.data);
        expect(resultado).toEqual({ estado: 'ENCOLADO' });
    });
});

describe('siiApi.facturas', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('listar llama GET /sii/facturas con los parámetros dados', () => {
        api.get.mockResolvedValue({ data: [] });
        siiApi.facturas.listar({ por_pagina: 10, pagina: 2 });
        expect(api.get).toHaveBeenCalledWith(
            '/sii/facturas',
            expect.objectContaining({ params: { por_pagina: 10, pagina: 2 } })
        );
    });

    it('obtenerEstado llama GET /sii/facturas/{id}/estado', () => {
        api.get.mockResolvedValue({ data: { estado: 'ACEPTADO' } });
        siiApi.facturas.obtenerEstado(5);
        expect(api.get).toHaveBeenCalledWith('/sii/facturas/5/estado');
    });

    it('obtener llama GET /sii/facturas/{id}', () => {
        api.get.mockResolvedValue({ data: {} });
        siiApi.facturas.obtener(10);
        expect(api.get).toHaveBeenCalledWith('/sii/facturas/10');
    });

    it('reintentar llama POST /sii/facturas/{id}/reintentar con silent', () => {
        api.post.mockResolvedValue({ data: {} });
        siiApi.facturas.reintentar(99, { razon: 'Error previo' });
        expect(api.post).toHaveBeenCalledWith(
            '/sii/facturas/99/reintentar',
            { razon: 'Error previo' },
            { silent: true }
        );
    });
});
